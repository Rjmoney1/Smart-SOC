#!/usr/bin/env python3
"""
Packet Sniffer — Network packet analysis using Scapy
Detects: port scans, suspicious traffic, multiple connection attempts
"""

import os
import time
import json
import logging
import pymysql
from datetime import datetime
from collections import defaultdict
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

try:
    from scapy.all import sniff, IP, TCP, UDP, ICMP, get_if_list
    SCAPY_AVAILABLE = True
except ImportError:
    SCAPY_AVAILABLE = False
    logging.warning("Scapy not available. Install with: pip install scapy")

DB_CONFIG = {
    'host':    os.getenv('DB_HOST', 'localhost'),
    'user':    os.getenv('DB_USER', 'root'),
    'password':os.getenv('DB_PASS', ''),
    'db':      os.getenv('DB_NAME', 'cyber_ai_platform'),
    'charset': 'utf8mb4',
}

INTERFACE        = os.getenv('PYTHON_PACKET_SNIFF_IFACE', 'eth0')
PORT_SCAN_THRESH = 15   # ports scanned from same IP in window
CONN_THRESH      = 100  # connections from same IP in window
WINDOW_SECONDS   = 60

logger = logging.getLogger(__name__)

class PacketSniffer:
    def __init__(self):
        self.port_tracker: dict  = defaultdict(set)        # {ip: {ports}}
        self.conn_tracker: dict  = defaultdict(int)        # {ip: count}
        self.icmp_tracker: dict  = defaultdict(int)        # {ip: count}
        self.alert_cooldown: dict = {}                     # {ip: timestamp}
        self.last_reset = time.time()

    def get_db(self):
        return pymysql.connect(**DB_CONFIG)

    def insert_alert(self, title, description, severity, attack_type, source_ip, target_port=None, protocol=None):
        # Cooldown: don't alert same IP more than once per 5 min
        now = time.time()
        key = f"{source_ip}:{attack_type}"
        if key in self.alert_cooldown and (now - self.alert_cooldown[key]) < 300:
            return
        self.alert_cooldown[key] = now

        try:
            conn = self.get_db()
            risk = {'critical':90,'high':70,'medium':50,'low':25}.get(severity,50)
            with conn.cursor() as cur:
                cur.execute("""
                    INSERT INTO alerts (title, description, severity, status, attack_type, source_ip, target_port, protocol, risk_score, created_at)
                    VALUES (%s, %s, %s, 'open', %s, %s, %s, %s, %s, %s)
                """, (title, description, severity, attack_type, source_ip, target_port, protocol, risk, datetime.now()))
            conn.commit()
            conn.close()
            logger.warning(f"ALERT: {title} from {source_ip}")
        except Exception as e:
            logger.error(f"Failed to insert alert: {e}")

    def reset_trackers(self):
        now = time.time()
        if now - self.last_reset > WINDOW_SECONDS:
            self.port_tracker.clear()
            self.conn_tracker.clear()
            self.icmp_tracker.clear()
            self.last_reset = now

    def process_packet(self, packet):
        self.reset_trackers()

        if not packet.haslayer(IP):
            return

        src_ip  = packet[IP].src
        dst_ip  = packet[IP].dst

        # Skip private/loopback for external threat detection
        if src_ip.startswith(('127.', '::1')):
            return

        # TCP Analysis
        if packet.haslayer(TCP):
            dst_port = packet[TCP].dport
            flags    = packet[TCP].flags

            self.port_tracker[src_ip].add(dst_port)
            self.conn_tracker[src_ip] += 1

            # Port scan detection: SYN packets to many ports
            if len(self.port_tracker[src_ip]) >= PORT_SCAN_THRESH and (flags & 0x02):  # SYN
                ports_str = ', '.join(str(p) for p in sorted(list(self.port_tracker[src_ip])[:10]))
                self.insert_alert(
                    f"Port Scan Detected — {src_ip}",
                    f"IP {src_ip} scanned {len(self.port_tracker[src_ip])} ports on {dst_ip}. Ports: {ports_str}...",
                    'high', 'Port Scan', src_ip, None, 'TCP'
                )
                self.port_tracker[src_ip].clear()

            # Connection flood detection
            if self.conn_tracker[src_ip] >= CONN_THRESH:
                self.insert_alert(
                    f"TCP Connection Flood — {src_ip}",
                    f"Excessive TCP connections ({self.conn_tracker[src_ip]}) from {src_ip} to {dst_ip}",
                    'high', 'DoS Attack', src_ip, dst_port, 'TCP'
                )
                self.conn_tracker[src_ip] = 0

            # Suspicious ports: common attack targets
            SUSPICIOUS_PORTS = {22:'SSH',23:'Telnet',3389:'RDP',4444:'Metasploit',5900:'VNC',6667:'IRC/Botnet'}
            if dst_port in SUSPICIOUS_PORTS and (flags & 0x02):
                service = SUSPICIOUS_PORTS[dst_port]
                logger.info(f"Suspicious {service} connection attempt from {src_ip}")

        # UDP Analysis
        elif packet.haslayer(UDP):
            dst_port = packet[UDP].dport
            self.port_tracker[src_ip].add(dst_port)

        # ICMP Flood Detection
        elif packet.haslayer(ICMP):
            self.icmp_tracker[src_ip] += 1
            if self.icmp_tracker[src_ip] >= 100:  # 100 ICMP/min
                self.insert_alert(
                    f"ICMP Flood Attack — {src_ip}",
                    f"ICMP flood detected: {self.icmp_tracker[src_ip]} packets from {src_ip}",
                    'medium', 'DoS Attack', src_ip, None, 'ICMP'
                )
                self.icmp_tracker[src_ip] = 0

    def run(self):
        if not SCAPY_AVAILABLE:
            logger.error("Scapy is not installed. Cannot start packet sniffer.")
            return

        logger.info(f"Packet Sniffer started on interface: {INTERFACE}")
        try:
            sniff(
                iface=INTERFACE,
                prn=self.process_packet,
                store=False,
                filter="ip"
            )
        except PermissionError:
            logger.error("Permission denied. Run packet sniffer as root: sudo python3 packet_sniffer.py")
        except Exception as e:
            logger.error(f"Packet sniffer error: {e}")

if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
    sniffer = PacketSniffer()
    sniffer.run()
