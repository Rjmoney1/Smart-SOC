#!/usr/bin/env python3
"""
Log Monitor — Watches Linux auth.log and syslog for security events
"""

import re
import time
import json
import logging
import os
import pymysql
from datetime import datetime
from pathlib import Path
from dotenv import load_dotenv
from storage import storage

load_dotenv(Path(__file__).parent.parent / '.env')

DB_CONFIG = {
    'host':    os.getenv('DB_HOST', 'localhost'),
    'user':    os.getenv('DB_USER', 'root'),
    'password':os.getenv('DB_PASS', ''),
    'db':      os.getenv('DB_NAME', 'cyber_ai_platform'),
    'charset': 'utf8mb4',
}

LOG_FILES = [
    '/var/log/auth.log',
    '/var/log/syslog',
    '/var/log/secure',        # RHEL/CentOS
    '/var/log/messages',      # RHEL/CentOS
]

# Regex patterns
PATTERNS = {
    'ssh_fail':        re.compile(r'Failed password for (?:invalid user )?(\w+) from ([\d.]+) port (\d+)'),
    'ssh_success':     re.compile(r'Accepted (?:password|publickey) for (\w+) from ([\d.]+) port (\d+)'),
    'sudo_usage':      re.compile(r'sudo:\s+(\w+)\s*:\s*.*COMMAND=(.+)'),
    'sudo_fail':       re.compile(r'sudo:\s+(\w+)\s*:.*authentication failure'),
    'invalid_user':    re.compile(r'Invalid user (\w+) from ([\d.]+)'),
    'connection_close':re.compile(r'Received disconnect from ([\d.]+).*Bye Bye'),
    'pam_fail':        re.compile(r'pam_unix.*authentication failure.*user=(\w+)'),
}

BRUTE_FORCE_THRESHOLD = 5    # attempts
BRUTE_FORCE_WINDOW    = 300  # seconds

logger = logging.getLogger(__name__)

class LogMonitor:
    def __init__(self):
        self.failed_attempts: dict = {}   # {ip: [(timestamp, user)]}
        self.file_positions: dict = {}

    def get_db(self):
        return pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)

    def insert_log(self, conn, log_type, action, message, severity='info', ip=None, raw=None):
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """INSERT INTO logs (type, action, message, severity, ip_address, raw_data, created_at)
                       VALUES (%s, %s, %s, %s, %s, %s, %s)""",
                    (log_type, action, message, severity, ip, json.dumps(raw) if raw else None, datetime.now())
                )
            conn.commit()
        except Exception as e:
            logger.error(f"insert_log error: {e}")

    def insert_alert(self, conn, title, description, severity, attack_type, source_ip=None, port=None):
        risk = {'critical':90, 'high':70, 'medium':50, 'low':25, 'info':10}.get(severity, 50)
        with conn.cursor() as cur:
            cur.execute(
                """INSERT INTO alerts (title, description, severity, status, attack_type, source_ip, target_port, risk_score, created_at)
                   VALUES (%s, %s, %s, 'open', %s, %s, %s, %s, %s)""",
                (title, description, severity, attack_type, source_ip, port, risk, datetime.now())
            )
        conn.commit()
        return cur.lastrowid

    def track_brute_force(self, ip, user, conn):
        now = time.time()
        if ip not in self.failed_attempts:
            self.failed_attempts[ip] = []
        # Clean old attempts
        self.failed_attempts[ip] = [(t, u) for t, u in self.failed_attempts[ip] if now - t < BRUTE_FORCE_WINDOW]
        self.failed_attempts[ip].append((now, user))

        count = len(self.failed_attempts[ip])
        if count >= BRUTE_FORCE_THRESHOLD and count % BRUTE_FORCE_THRESHOLD == 0:
            title = f"SSH Brute Force Attack — {ip}"
            desc  = f"{count} failed SSH login attempts from {ip} in {BRUTE_FORCE_WINDOW}s. Targeted users: {', '.join(set(u for _,u in self.failed_attempts[ip]))}"
            self.insert_alert(conn, title, desc, 'critical', 'Brute Force', ip, 22)
            logger.warning(f"BRUTE FORCE: {ip} — {count} attempts")

            # Trigger IP block via DB
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        """INSERT INTO blocked_ips (ip_address, reason, is_active, blocked_at)
                           VALUES (%s, %s, 1, %s)
                           ON DUPLICATE KEY UPDATE is_active=1, blocked_at=%s""",
                        (ip, f"Auto-blocked: {count} SSH brute force attempts", datetime.now(), datetime.now())
                    )
                conn.commit()
            except Exception as e:
                logger.error(f"Failed to block IP {ip}: {e}")

    def process_line(self, line, conn):
        # SSH failed login
        m = PATTERNS['ssh_fail'].search(line)
        if m:
            user, ip, port = m.groups()
            self.insert_log(conn, 'attack', 'ssh_fail',
                f"Failed SSH login for user '{user}' from {ip}:{port}", 'high', ip)
            self.track_brute_force(ip, user, conn)
            return

        # SSH success
        m = PATTERNS['ssh_success'].search(line)
        if m:
            user, ip, port = m.groups()
            self.insert_log(conn, 'auth', 'ssh_success',
                f"Successful SSH login for '{user}' from {ip}:{port}", 'info', ip)
            return

        # Invalid user
        m = PATTERNS['invalid_user'].search(line)
        if m:
            user, ip = m.groups()
            self.insert_log(conn, 'attack', 'invalid_user',
                f"SSH login attempt with invalid user '{user}' from {ip}", 'medium', ip)
            return

        # Suspicious sudo
        m = PATTERNS['sudo_fail'].search(line)
        if m:
            user = m.group(1)
            self.insert_log(conn, 'attack', 'sudo_fail',
                f"Failed sudo authentication for user '{user}'", 'high')
            self.insert_alert(conn, f"Sudo Authentication Failure — {user}",
                f"User '{user}' failed sudo authentication", 'high', 'Privilege Escalation')
            return

        # PAM failure
        m = PATTERNS['pam_fail'].search(line)
        if m:
            user = m.group(1)
            self.insert_log(conn, 'auth', 'pam_fail',
                f"PAM authentication failure for user '{user}'", 'medium')

    def tail_file(self, filepath, conn):
        path = Path(filepath)
        if not path.exists():
            return

        pos = self.file_positions.get(filepath, path.stat().st_size)
        self.file_positions[filepath] = pos

        with open(filepath, 'r', errors='ignore') as f:
            f.seek(pos)
            for line in f:
                line = line.strip()
                if line:
                    self.process_line(line, conn)
            self.file_positions[filepath] = f.tell()

    def run(self):
        logger.info("Log Monitor started")
        while True:
            try:
                conn = self.get_db()
                for log_file in LOG_FILES:
                    self.tail_file(log_file, conn)
                conn.close()
            except pymysql.err.OperationalError as e:
                logger.warning(f"DB not ready, retrying in 10s: {e}")
                time.sleep(10)
                continue
            except Exception as e:
                logger.error(f"Error in log monitor: {e}")
            time.sleep(int(os.getenv('PYTHON_LOG_MONITOR_INTERVAL', 30)))

if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
    monitor = LogMonitor()
    monitor.run()
