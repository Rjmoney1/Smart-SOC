#!/usr/bin/env python3
"""
Traffic Simulator — Generates realistic security events for live dashboard demo.
Simulates: SSH attacks, port scans, web attacks, anomalous traffic, blocked IPs.
Runs continuously, injecting events at realistic intervals.
"""

import os
import time
import random
import logging
import pymysql
from datetime import datetime
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

DB_CONFIG = {
    'host':     os.getenv('DB_HOST', 'db'),
    'user':     os.getenv('DB_USER', 'cyberai'),
    'password': os.getenv('DB_PASS', 'CyberAI_Secure_2024!'),
    'db':       os.getenv('DB_NAME', 'cyber_ai_platform'),
    'charset':  'utf8mb4',
}

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] TrafficSim — %(message)s'
)
logger = logging.getLogger('TrafficSimulator')

# ─── Realistic attacker IPs and targets ──────────────────────────────────────
ATTACKER_IPS = [
    '185.220.101.34', '45.33.32.156', '198.51.100.87', '203.0.113.42',
    '91.108.56.77',   '103.21.244.0', '162.243.10.111','185.107.80.202',
    '212.102.63.30',  '5.188.87.194', '80.82.77.139',  '64.62.197.182',
    '141.98.80.138',  '193.32.126.76','194.165.16.11',
]
INTERNAL_IPS = ['10.0.0.5', '10.0.0.10', '10.0.0.15', '10.0.0.22', '192.168.1.100']
USERNAMES    = ['root', 'admin', 'ubuntu', 'pi', 'deploy', 'git', 'postgres', 'user']

# ─── Attack scenarios ─────────────────────────────────────────────────────────
ATTACK_SCENARIOS = [
    {
        'name': 'SSH Brute Force',
        'weight': 30,
        'fn': lambda: {
            'title':       f"SSH Brute Force Attack — {random.choice(ATTACKER_IPS)}",
            'description': f"{random.randint(5, 150)} failed SSH login attempts from {random.choice(ATTACKER_IPS)} targeting users: {', '.join(random.sample(USERNAMES, 3))}",
            'severity':    random.choice(['critical', 'critical', 'high']),
            'attack_type': 'Brute Force',
            'source_ip':   random.choice(ATTACKER_IPS),
            'target_port': 22,
            'protocol':    'TCP',
            'risk_score':  random.randint(75, 95),
        }
    },
    {
        'name': 'Port Scan',
        'weight': 20,
        'fn': lambda: {
            'title':       f"Port Scan Detected — {random.choice(ATTACKER_IPS)}",
            'description': f"Sequential port scanning detected. {random.randint(100, 1024)} ports scanned in rapid succession from {random.choice(ATTACKER_IPS)}",
            'severity':    'high',
            'attack_type': 'Port Scan',
            'source_ip':   random.choice(ATTACKER_IPS),
            'target_port': None,
            'protocol':    'TCP',
            'risk_score':  random.randint(60, 80),
        }
    },
    {
        'name': 'SQL Injection',
        'weight': 15,
        'fn': lambda: {
            'title':       f"SQL Injection Attempt — {random.choice(ATTACKER_IPS)}",
            'description': f"SQL injection payload detected in HTTP request: UNION SELECT, DROP TABLE patterns found. Target: /api/users?id=1 OR 1=1--",
            'severity':    'high',
            'attack_type': 'SQL Injection',
            'source_ip':   random.choice(ATTACKER_IPS),
            'target_port': 80,
            'protocol':    'HTTP',
            'risk_score':  random.randint(65, 80),
        }
    },
    {
        'name': 'DoS Attack',
        'weight': 15,
        'fn': lambda: {
            'title':       f"ICMP Flood Attack — {random.choice(ATTACKER_IPS)}",
            'description': f"ICMP flood attack detected: {random.randint(5000, 50000)} packets/sec from {random.choice(ATTACKER_IPS)} targeting {random.choice(INTERNAL_IPS)}",
            'severity':    random.choice(['medium', 'high']),
            'attack_type': 'DoS Attack',
            'source_ip':   random.choice(ATTACKER_IPS),
            'target_port': None,
            'protocol':    'ICMP',
            'risk_score':  random.randint(55, 75),
        }
    },
    {
        'name': 'Data Exfiltration',
        'weight': 8,
        'fn': lambda: {
            'title':       f"Suspicious Data Transfer — {random.choice(INTERNAL_IPS)}",
            'description': f"Anomalous outbound traffic: {random.randint(1, 10)}.{random.randint(1,9)}GB transferred to external IP {random.choice(ATTACKER_IPS)} on port {random.choice([4444, 8080, 443, 1337])}",
            'severity':    'critical',
            'attack_type': 'Data Exfiltration',
            'source_ip':   random.choice(INTERNAL_IPS),
            'target_port': random.choice([4444, 8080, 443, 1337]),
            'protocol':    'TCP',
            'risk_score':  random.randint(88, 99),
        }
    },
    {
        'name': 'RCE Exploit',
        'weight': 5,
        'fn': lambda: {
            'title':       f"Remote Code Execution Attempt — {random.choice(ATTACKER_IPS)}",
            'description': f"Shellshock/RCE exploit attempt via HTTP headers. CVE-2014-6271 pattern detected from {random.choice(ATTACKER_IPS)}",
            'severity':    'critical',
            'attack_type': 'Remote Code Execution',
            'source_ip':   random.choice(ATTACKER_IPS),
            'target_port': 80,
            'protocol':    'HTTP',
            'risk_score':  random.randint(92, 99),
        }
    },
    {
        'name': 'Privilege Escalation',
        'weight': 7,
        'fn': lambda: {
            'title':       f"Sudo Authentication Failure — {random.choice(USERNAMES)}",
            'description': f"User '{random.choice(USERNAMES)}' failed sudo authentication {random.randint(2, 8)} times. Possible privilege escalation attempt.",
            'severity':    'high',
            'attack_type': 'Privilege Escalation',
            'source_ip':   random.choice(INTERNAL_IPS),
            'target_port': None,
            'protocol':    'System',
            'risk_score':  random.randint(68, 82),
        }
    },
]

LOG_SCENARIOS = [
    ('attack', 'ssh_fail',    'high',     lambda: f"Failed SSH login for user '{random.choice(USERNAMES)}' from {random.choice(ATTACKER_IPS)}:22"),
    ('attack', 'portscan',    'high',     lambda: f"Port scan detected from {random.choice(ATTACKER_IPS)} — {random.randint(50,500)} ports"),
    ('auth',   'login',       'info',     lambda: f"User '{random.choice(['admin','analyst','viewer'])}' logged in from {random.choice(INTERNAL_IPS)}"),
    ('auth',   'fail',        'medium',   lambda: f"Failed login attempt for user '{random.choice(USERNAMES)}' from {random.choice(ATTACKER_IPS)}"),
    ('system', 'block_ip',    'high',     lambda: f"IP {random.choice(ATTACKER_IPS)} automatically blocked after repeated attacks"),
    ('attack', 'detect',      'critical', lambda: f"RCE exploit attempt from {random.choice(ATTACKER_IPS)} via HTTP"),
    ('system', 'scan',        'info',     lambda: f"Network scan completed. {random.randint(200, 260)} hosts discovered"),
    ('attack', 'exfiltration','critical', lambda: f"Anomalous data transfer: {random.randint(1,5)}.{random.randint(0,9)}GB to external IP {random.choice(ATTACKER_IPS)}"),
    ('system', 'startup',     'info',     lambda: f"Python monitoring engine heartbeat — all {random.randint(4,5)} threads alive"),
    ('auth',   'sudo_fail',   'high',     lambda: f"Sudo auth failure for user '{random.choice(USERNAMES)}'"),
]


def get_db():
    return pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)


def wait_for_db(max_retries=30):
    """Wait until DB is ready"""
    for i in range(max_retries):
        try:
            conn = get_db()
            conn.close()
            logger.info("Database connection established.")
            return True
        except Exception as e:
            logger.warning(f"Waiting for DB ({i+1}/{max_retries}): {e}")
            time.sleep(5)
    return False


def insert_alert(conn, data):
    now = datetime.now()
    try:
        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO alerts
                    (title, description, severity, status, attack_type,
                     source_ip, target_port, protocol, risk_score, created_at, updated_at)
                VALUES (%s,%s,%s,'open',%s,%s,%s,%s,%s,%s,%s)
            """, (
                data['title'], data['description'], data['severity'],
                data['attack_type'], data['source_ip'], data.get('target_port'),
                data.get('protocol'), data['risk_score'], now, now
            ))
        conn.commit()
        logger.info(f"[ALERT] {data['severity'].upper()} — {data['title'][:60]}")
    except Exception as e:
        logger.error(f"insert_alert failed: {e}")


def insert_log(conn, log_type, action, severity, message, ip=None):
    now = datetime.now()
    try:
        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO logs (type, action, message, severity, ip_address, created_at)
                VALUES (%s,%s,%s,%s,%s,%s)
            """, (log_type, action, message, severity, ip, now))
        conn.commit()
    except Exception as e:
        logger.error(f"insert_log failed: {e}")


def maybe_block_ip(conn, ip):
    """Auto-block with 20% chance"""
    if random.random() > 0.20:
        return
    now = datetime.now()
    try:
        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO blocked_ips (ip_address, reason, is_active, blocked_at)
                VALUES (%s,%s,1,%s)
                ON DUPLICATE KEY UPDATE is_active=1, blocked_at=%s
            """, (ip, f"Auto-blocked by traffic simulator at {now}", now, now))
        conn.commit()
        logger.info(f"[BLOCK] IP blocked: {ip}")
    except Exception as e:
        logger.error(f"maybe_block_ip failed: {e}")


def pick_scenario():
    """Weighted random scenario selection"""
    total = sum(s['weight'] for s in ATTACK_SCENARIOS)
    r = random.uniform(0, total)
    cumulative = 0
    for s in ATTACK_SCENARIOS:
        cumulative += s['weight']
        if r <= cumulative:
            return s
    return ATTACK_SCENARIOS[0]


def run():
    logger.info("=" * 55)
    logger.info("  CyberAI Traffic Simulator — Starting")
    logger.info("=" * 55)

    if not wait_for_db():
        logger.error("Could not connect to database. Exiting.")
        return

    tick = 0
    while True:
        try:
            conn = get_db()

            # ── Inject 1-3 log entries every tick ──────────────────────
            for _ in range(random.randint(1, 3)):
                scenario = random.choice(LOG_SCENARIOS)
                log_type, action, severity, msg_fn = scenario
                ip = random.choice(ATTACKER_IPS) if 'attack' in log_type else random.choice(INTERNAL_IPS)
                insert_log(conn, log_type, action, severity, msg_fn(), ip)

            # ── Inject an alert every 3-8 ticks ────────────────────────
            if tick % random.randint(3, 8) == 0:
                scenario = pick_scenario()
                data = scenario['fn']()
                insert_alert(conn, data)
                maybe_block_ip(conn, data['source_ip'])

            conn.close()
            tick += 1

            # Sleep 8-20 seconds between ticks for realistic feel
            sleep_time = random.randint(8, 20)
            logger.debug(f"Tick {tick} done. Sleeping {sleep_time}s")
            time.sleep(sleep_time)

        except pymysql.err.OperationalError as e:
            logger.warning(f"DB connection lost, reconnecting: {e}")
            time.sleep(5)
        except KeyboardInterrupt:
            logger.info("Traffic Simulator stopped.")
            break
        except Exception as e:
            logger.error(f"Simulator error: {e}")
            time.sleep(10)


if __name__ == '__main__':
    run()
