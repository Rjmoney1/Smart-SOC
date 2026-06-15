#!/usr/bin/env python3
"""
Real Packet Capture Engine
============================
Replaces the fake traffic simulator with REAL network monitoring:
  - Live packet sniffing via Scapy (TCP/UDP/ICMP)
  - SSH brute-force detection from auth logs
  - Port scan detection (SYN flood tracking)
  - ICMP flood detection
  - Connection rate limiting / DoS detection
  - HTTP suspicious path detection
  - DNS anomaly detection
  - Auto IP blocking on threshold breach
  - 5-minute alert cooldown per IP+type
"""

import os
import re
import time
import logging
import threading
import pymysql
from datetime import datetime
from collections import defaultdict
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

# ── Logging ────────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] PacketCapture — %(message)s'
)
logger = logging.getLogger('PacketCapture')

# ── DB Config ──────────────────────────────────────────────────────────────────
DB_CONFIG = {
    'host':    os.getenv('DB_HOST', 'db'),
    'user':    os.getenv('DB_USER', 'cyberai'),
    'password':os.getenv('DB_PASS', 'CyberAI_Secure_2024!'),
    'db':      os.getenv('DB_NAME', 'cyber_ai_platform'),
    'charset': 'utf8mb4',
}

# ── Thresholds (overridden by DB settings table at runtime) ────────────────────
PORT_SCAN_THRESH    = int(os.getenv('PORT_SCAN_THRESHOLD',    '15'))   # unique ports in window
CONN_FLOOD_THRESH   = int(os.getenv('CONN_FLOOD_THRESHOLD',  '200'))  # TCP conns/min
ICMP_FLOOD_THRESH   = int(os.getenv('ICMP_FLOOD_THRESHOLD',  '100'))  # ICMP pkts/min
SSH_FAIL_THRESH     = int(os.getenv('SSH_FAIL_THRESHOLD',    '5'))    # failed SSH in window
WINDOW_SECONDS      = int(os.getenv('DETECTION_WINDOW_SEC',  '60'))
ALERT_COOLDOWN_SEC  = int(os.getenv('ALERT_COOLDOWN_SEC',    '300'))  # 5 min
AUTO_BLOCK_THRESH   = int(os.getenv('AUTO_BLOCK_THRESHOLD',  '3'))    # alerts before block
INTERFACE           = os.getenv('PYTHON_PACKET_SNIFF_IFACE', 'eth0')

# ── Suspicious HTTP paths ──────────────────────────────────────────────────────
SUSPICIOUS_PATHS = [
    r'/etc/passwd', r'/etc/shadow', r'\.\./', r'union.*select', r'<script',
    r'eval\(', r'base64_decode', r'/wp-admin', r'/phpmyadmin', r'cmd=',
    r'exec=', r'/shell', r'\.php\?.*=http', r'UNION.*SELECT', r'DROP.*TABLE',
]
SUSPICIOUS_RE = re.compile('|'.join(SUSPICIOUS_PATHS), re.IGNORECASE)

# ── Suspicious DNS domains ─────────────────────────────────────────────────────
SUSPICIOUS_TLD = ['.ru', '.cn', '.tk', '.ml', '.ga', '.cf', '.bit', '.onion']

# ── Well-known malicious port pairs ───────────────────────────────────────────
MALICIOUS_PORTS = {
    4444: 'Metasploit',
    1337: 'L33t/Hacker',
    6667: 'IRC Botnet',
    6668: 'IRC Botnet',
    1080: 'SOCKS Proxy',
    3128: 'Proxy Server',
    8080: 'HTTP Proxy',
    9001: 'Tor',
    9050: 'Tor',
}

SUSPICIOUS_PORTS = {
    22:   'SSH',
    23:   'Telnet',
    25:   'SMTP',
    3389: 'RDP',
    5900: 'VNC',
    5985: 'WinRM',
    5986: 'WinRM-SSL',
    445:  'SMB',
    139:  'NetBIOS',
}


def get_db():
    return pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)


def wait_for_db(max_retries=30):
    for i in range(max_retries):
        try:
            c = get_db(); c.close()
            logger.info("Database connected.")
            return True
        except Exception as e:
            logger.warning(f"Waiting for DB ({i+1}/{max_retries}): {e}")
            time.sleep(5)
    return False


class RealPacketCapture:
    """
    Real packet capture engine using Scapy.
    Tracks per-IP statistics over a sliding time window and raises
    alerts based on genuine network behaviour.
    """

    def __init__(self):
        # Sliding-window trackers  {ip -> set/int}
        self.port_tracker  = defaultdict(set)    # {src_ip: {dst_ports}}
        self.conn_tracker  = defaultdict(int)    # {src_ip: tcp_conn_count}
        self.icmp_tracker  = defaultdict(int)    # {src_ip: icmp_count}
        self.udp_tracker   = defaultdict(set)    # {src_ip: {dst_ports}}
        self.syn_tracker   = defaultdict(int)    # {src_ip: SYN count}
        self.pkt_bytes     = defaultdict(int)    # {src_ip: bytes transferred}
        self.alert_times   = {}                  # {ip:type -> last_alert_ts}
        self.block_counts  = defaultdict(int)    # {ip -> alert_count}
        self.window_start  = time.time()

        # Packet counters for stats
        self.total_packets = 0
        self.total_alerts  = 0

        self._lock = threading.Lock()

    # ── Helpers ────────────────────────────────────────────────────────────────

    def _reset_window(self):
        """Clear all per-window counters every WINDOW_SECONDS."""
        now = time.time()
        if now - self.window_start >= WINDOW_SECONDS:
            with self._lock:
                self.port_tracker.clear()
                self.conn_tracker.clear()
                self.icmp_tracker.clear()
                self.udp_tracker.clear()
                self.syn_tracker.clear()
                self.pkt_bytes.clear()
                self.window_start = now
            logger.debug("Detection window reset.")

    def _cooldown_ok(self, ip: str, attack_type: str) -> bool:
        """Return True if we should fire an alert (not in cooldown)."""
        key = f"{ip}:{attack_type}"
        now = time.time()
        last = self.alert_times.get(key, 0)
        if now - last < ALERT_COOLDOWN_SEC:
            return False
        self.alert_times[key] = now
        return True

    def _load_thresholds(self):
        """Optionally reload thresholds from DB settings table."""
        global PORT_SCAN_THRESH, CONN_FLOOD_THRESH, ICMP_FLOOD_THRESH, SSH_FAIL_THRESH
        try:
            conn = get_db()
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT setting_key, setting_value FROM settings
                    WHERE setting_key IN (
                        'port_scan_threshold','conn_flood_threshold',
                        'icmp_flood_threshold','ssh_fail_threshold'
                    )
                """)
                for row in cur.fetchall():
                    k, v = row['setting_key'], int(row['setting_value'] or 0)
                    if k == 'port_scan_threshold'  and v: PORT_SCAN_THRESH   = v
                    if k == 'conn_flood_threshold' and v: CONN_FLOOD_THRESH  = v
                    if k == 'icmp_flood_threshold' and v: ICMP_FLOOD_THRESH  = v
                    if k == 'ssh_fail_threshold'   and v: SSH_FAIL_THRESH    = v
            conn.close()
        except Exception:
            pass  # Use defaults

    def insert_alert(self, title: str, description: str, severity: str,
                     attack_type: str, source_ip: str,
                     target_port: int = None, protocol: str = None,
                     risk_score: int = None):
        """Write a real alert to the database."""
        if not self._cooldown_ok(source_ip, attack_type):
            return

        sev_risk = {'critical': 92, 'high': 72, 'medium': 52, 'low': 25}
        risk = risk_score or sev_risk.get(severity, 50)

        try:
            conn = get_db()
            with conn.cursor() as cur:
                cur.execute("""
                    INSERT INTO alerts
                      (title, description, severity, status, attack_type,
                       source_ip, target_port, protocol, risk_score, created_at, updated_at)
                    VALUES (%s,%s,%s,'open',%s,%s,%s,%s,%s,%s,%s)
                """, (
                    title, description, severity, attack_type,
                    source_ip, target_port, protocol, risk,
                    datetime.now(), datetime.now()
                ))
                # Also log it
                cur.execute("""
                    INSERT INTO logs (type, action, message, severity, ip_address, created_at)
                    VALUES ('attack', %s, %s, %s, %s, %s)
                """, (attack_type.lower().replace(' ', '_'),
                      f"[REAL] {title}", severity, source_ip, datetime.now()))
            conn.commit()
            conn.close()

            self.total_alerts += 1
            self.block_counts[source_ip] += 1
            logger.warning(f"🚨 REAL ALERT [{severity.upper()}] {title}")

            # Auto-block if threshold reached
            if self.block_counts[source_ip] >= AUTO_BLOCK_THRESH:
                self._auto_block(source_ip, attack_type)

        except Exception as e:
            logger.error(f"insert_alert failed: {e}")

    def _auto_block(self, ip: str, reason: str):
        """Auto-block an IP that keeps triggering alerts."""
        try:
            conn = get_db()
            with conn.cursor() as cur:
                cur.execute("""
                    INSERT INTO blocked_ips (ip_address, reason, is_active, blocked_at)
                    VALUES (%s, %s, 1, %s)
                    ON DUPLICATE KEY UPDATE is_active=1, reason=%s, blocked_at=%s
                """, (ip,
                      f"Auto-blocked: {self.block_counts[ip]} alerts ({reason})",
                      datetime.now(),
                      f"Auto-blocked: {self.block_counts[ip]} alerts ({reason})",
                      datetime.now()))
                cur.execute("""
                    INSERT INTO logs (type, action, message, severity, ip_address, created_at)
                    VALUES ('system','auto_block',%s,'high',%s,%s)
                """, (f"IP {ip} auto-blocked after {self.block_counts[ip]} real alerts",
                      ip, datetime.now()))
            conn.commit()
            conn.close()
            self.block_counts[ip] = 0
            logger.warning(f"🛑 AUTO-BLOCKED: {ip} ({reason})")
        except Exception as e:
            logger.error(f"auto_block failed: {e}")

    # ── Packet Processor ───────────────────────────────────────────────────────

    def process_packet(self, pkt):
        """Main callback — called by Scapy for every captured packet."""
        try:
            from scapy.all import IP, TCP, UDP, ICMP, Raw

            self._reset_window()
            self.total_packets += 1

            if not pkt.haslayer(IP):
                return

            src_ip  = pkt[IP].src
            dst_ip  = pkt[IP].dst
            pkt_len = len(pkt)

            # Skip loopback
            if src_ip.startswith('127.') or src_ip == '::1':
                return

            with self._lock:
                self.pkt_bytes[src_ip] += pkt_len

            # ── TCP ────────────────────────────────────────────────────────────
            if pkt.haslayer(TCP):
                dport = pkt[TCP].dport
                sport = pkt[TCP].sport
                flags = pkt[TCP].flags

                with self._lock:
                    self.port_tracker[src_ip].add(dport)
                    self.conn_tracker[src_ip] += 1
                    if flags & 0x02:  # SYN flag
                        self.syn_tracker[src_ip] += 1

                n_ports = len(self.port_tracker[src_ip])
                n_conns = self.conn_tracker[src_ip]
                n_syns  = self.syn_tracker[src_ip]

                # ── Port Scan (many SYN to different ports) ────────────────────
                if n_ports >= PORT_SCAN_THRESH and (flags & 0x02):
                    ports_list = sorted(self.port_tracker[src_ip])
                    sample = ', '.join(map(str, ports_list[:12]))
                    self.insert_alert(
                        f"Port Scan Detected — {src_ip}",
                        f"REAL: {src_ip} scanned {n_ports} ports on {dst_ip} "
                        f"within {WINDOW_SECONDS}s. Ports include: {sample}...",
                        'high', 'Port Scan', src_ip, None, 'TCP', 75
                    )
                    with self._lock:
                        self.port_tracker[src_ip].clear()
                        self.syn_tracker[src_ip] = 0

                # ── SYN Flood (DoS) ────────────────────────────────────────────
                elif n_syns >= CONN_FLOOD_THRESH:
                    self.insert_alert(
                        f"SYN Flood Attack — {src_ip}",
                        f"REAL: SYN flood from {src_ip} to {dst_ip}:{dport}. "
                        f"{n_syns} SYN packets in {WINDOW_SECONDS}s (no ACK completion).",
                        'critical', 'DoS Attack', src_ip, dport, 'TCP', 90
                    )
                    with self._lock:
                        self.syn_tracker[src_ip] = 0

                # ── Connection flood ───────────────────────────────────────────
                elif n_conns >= CONN_FLOOD_THRESH:
                    self.insert_alert(
                        f"TCP Connection Flood — {src_ip}",
                        f"REAL: {n_conns} TCP connections from {src_ip} to {dst_ip}:{dport} "
                        f"in {WINDOW_SECONDS}s.",
                        'high', 'DoS Attack', src_ip, dport, 'TCP', 78
                    )
                    with self._lock:
                        self.conn_tracker[src_ip] = 0

                # ── Malicious destination port ─────────────────────────────────
                if dport in MALICIOUS_PORTS and (flags & 0x02):
                    svc = MALICIOUS_PORTS[dport]
                    self.insert_alert(
                        f"Malicious Port Connection — {src_ip} → {dport}",
                        f"REAL: {src_ip} connecting to known malicious port "
                        f"{dport} ({svc}) on {dst_ip}. Possible C2 communication.",
                        'critical', 'Command & Control', src_ip, dport, 'TCP', 88
                    )

                # ── Suspicious HTTP payload inspection ─────────────────────────
                if dport in (80, 8080, 443, 8443) and pkt.haslayer(Raw):
                    try:
                        payload = pkt[Raw].load.decode('utf-8', errors='ignore')
                        if SUSPICIOUS_RE.search(payload):
                            snippet = payload[:200].replace('\n', ' ')
                            self.insert_alert(
                                f"Suspicious HTTP Request — {src_ip}",
                                f"REAL: Malicious HTTP payload detected from {src_ip} to "
                                f"{dst_ip}:{dport}. Payload snippet: {snippet}",
                                'high', 'Web Attack', src_ip, dport, 'HTTP', 80
                            )
                    except Exception:
                        pass

            # ── UDP ────────────────────────────────────────────────────────────
            elif pkt.haslayer(UDP):
                dport = pkt[UDP].dport
                with self._lock:
                    self.udp_tracker[src_ip].add(dport)

                # DNS exfiltration check — unusually many DNS queries
                if dport == 53 and pkt.haslayer(Raw):
                    try:
                        from scapy.all import DNS, DNSQR
                        if pkt.haslayer(DNSQR):
                            qname = pkt[DNSQR].qname.decode('utf-8', errors='ignore')
                            for tld in SUSPICIOUS_TLD:
                                if qname.endswith(tld + '.'):
                                    self.insert_alert(
                                        f"Suspicious DNS Query — {src_ip}",
                                        f"REAL: DNS query to suspicious TLD '{tld}': {qname} "
                                        f"from {src_ip}. Possible DNS exfiltration or malware C2.",
                                        'medium', 'DNS Anomaly', src_ip, 53, 'UDP', 58
                                    )
                    except Exception:
                        pass

                # UDP flood
                n_udp_ports = len(self.udp_tracker[src_ip])
                if n_udp_ports >= PORT_SCAN_THRESH:
                    self.insert_alert(
                        f"UDP Port Scan — {src_ip}",
                        f"REAL: {src_ip} sent UDP packets to {n_udp_ports} different ports "
                        f"on {dst_ip} in {WINDOW_SECONDS}s.",
                        'medium', 'Port Scan', src_ip, None, 'UDP', 55
                    )
                    with self._lock:
                        self.udp_tracker[src_ip].clear()

            # ── ICMP ───────────────────────────────────────────────────────────
            elif pkt.haslayer(ICMP):
                with self._lock:
                    self.icmp_tracker[src_ip] += 1
                    count = self.icmp_tracker[src_ip]

                if count >= ICMP_FLOOD_THRESH:
                    self.insert_alert(
                        f"ICMP Flood Attack — {src_ip}",
                        f"REAL: {src_ip} sent {count} ICMP packets to {dst_ip} "
                        f"in {WINDOW_SECONDS}s (rate: {count/WINDOW_SECONDS:.1f} pkt/s).",
                        'medium', 'DoS Attack', src_ip, None, 'ICMP', 62
                    )
                    with self._lock:
                        self.icmp_tracker[src_ip] = 0

        except Exception as e:
            logger.error(f"process_packet error: {e}")

    # ── SSH Auth Log Monitor ───────────────────────────────────────────────────

    def monitor_ssh_logs(self):
        """
        Parse /var/log/auth.log (or /var/log/secure) for real SSH failures.
        Works on Linux hosts — runs in a separate thread.
        """
        SSH_FAIL_RE = re.compile(
            r'Failed (?:password|publickey) for (?:invalid user )?(\S+) from ([\d.]+)'
        )
        SSH_ACCEPT_RE = re.compile(
            r'Accepted (?:password|publickey) for (\S+) from ([\d.]+)'
        )
        log_paths = ['/var/log/auth.log', '/var/log/secure', '/var/log/syslog']

        log_file = None
        for p in log_paths:
            if Path(p).exists():
                log_file = p
                break

        if not log_file:
            logger.info("No SSH auth log found — SSH monitoring disabled in container.")
            logger.info("Mount /var/log/auth.log from host for real SSH monitoring.")
            return

        logger.info(f"SSH log monitoring: {log_file}")
        fail_tracker  = defaultdict(int)   # {ip -> fail_count}
        window_start  = time.time()

        with open(log_file, 'r') as f:
            f.seek(0, 2)  # tail mode
            while True:
                line = f.readline()
                if not line:
                    time.sleep(1)
                    # Reset window
                    if time.time() - window_start > WINDOW_SECONDS:
                        fail_tracker.clear()
                        window_start = time.time()
                    continue

                # Failed login
                m = SSH_FAIL_RE.search(line)
                if m:
                    user, ip = m.group(1), m.group(2)
                    fail_tracker[ip] += 1
                    count = fail_tracker[ip]
                    logger.info(f"SSH fail #{count}: user={user} ip={ip}")

                    if count >= SSH_FAIL_THRESH:
                        self.insert_alert(
                            f"SSH Brute Force — {ip}",
                            f"REAL: {count} failed SSH login attempts from {ip} "
                            f"in {WINDOW_SECONDS}s. Targeted users include: {user}.",
                            'critical' if count >= SSH_FAIL_THRESH * 3 else 'high',
                            'Brute Force', ip, 22, 'SSH',
                            min(95, 70 + count * 2)
                        )
                        fail_tracker[ip] = 0

                # Successful login after fails — suspicious
                m2 = SSH_ACCEPT_RE.search(line)
                if m2:
                    user, ip = m2.group(1), m2.group(2)
                    if fail_tracker.get(ip, 0) > 0:
                        self.insert_alert(
                            f"SSH Login After Failures — {ip}",
                            f"REAL: Successful SSH login for '{user}' from {ip} "
                            f"after {fail_tracker[ip]} failed attempts — possible credential compromise.",
                            'critical', 'Credential Compromise', ip, 22, 'SSH', 95
                        )

    # ── Stats Reporter ────────────────────────────────────────────────────────

    def report_stats(self):
        """Log live capture statistics every 60 seconds."""
        while True:
            time.sleep(60)
            logger.info(
                f"📊 Stats | Packets: {self.total_packets:,} | "
                f"Real Alerts: {self.total_alerts} | "
                f"Tracked IPs: {len(self.conn_tracker)}"
            )
            self._load_thresholds()

    # ── Main Run ───────────────────────────────────────────────────────────────

    def run(self):
        try:
            from scapy.all import sniff, conf
        except ImportError:
            logger.error("Scapy not installed. Run: pip install scapy")
            return

        logger.info("=" * 60)
        logger.info("  CyberAI — REAL Packet Capture Engine")
        logger.info(f"  Interface : {INTERFACE}")
        logger.info(f"  Port Scan : {PORT_SCAN_THRESH} ports/{WINDOW_SECONDS}s")
        logger.info(f"  Conn Flood: {CONN_FLOOD_THRESH} conns/{WINDOW_SECONDS}s")
        logger.info(f"  ICMP Flood: {ICMP_FLOOD_THRESH} pkts/{WINDOW_SECONDS}s")
        logger.info(f"  SSH Fail  : {SSH_FAIL_THRESH} attempts/{WINDOW_SECONDS}s")
        logger.info(f"  Auto-Block: after {AUTO_BLOCK_THRESH} alerts")
        logger.info("=" * 60)

        if not wait_for_db():
            logger.error("DB unavailable. Exiting.")
            return

        self._load_thresholds()

        # SSH log monitor thread
        ssh_thread = threading.Thread(target=self.monitor_ssh_logs,
                                      name='SSHLogMonitor', daemon=True)
        ssh_thread.start()

        # Stats reporter thread
        stats_thread = threading.Thread(target=self.report_stats,
                                        name='StatsReporter', daemon=True)
        stats_thread.start()

        # Log startup
        try:
            conn = get_db()
            with conn.cursor() as cur:
                cur.execute("""
                    INSERT INTO logs (type, action, message, severity, ip_address, created_at)
                    VALUES ('system','startup',
                            %s,'info','127.0.0.1',%s)
                """, (f"Real Packet Capture Engine started on {INTERFACE}", datetime.now()))
            conn.commit(); conn.close()
        except Exception:
            pass

        # Start real capture
        logger.info(f"🔴 LIVE: Sniffing on {INTERFACE} — press Ctrl+C to stop")
        try:
            sniff(
                iface=INTERFACE,
                prn=self.process_packet,
                store=False,
                filter="ip",          # BPF filter — only IP packets
                quiet=True,
            )
        except PermissionError:
            logger.error("Permission denied. Container needs NET_ADMIN + NET_RAW capabilities.")
            logger.error("Add to docker-compose.yml under python_engine:")
            logger.error("  cap_add: [NET_ADMIN, NET_RAW]")
        except OSError as e:
            logger.error(f"Interface '{INTERFACE}' error: {e}")
            logger.error(f"Available interfaces: lo, eth0")
        except KeyboardInterrupt:
            logger.info("Packet capture stopped.")


if __name__ == '__main__':
    engine = RealPacketCapture()
    engine.run()
