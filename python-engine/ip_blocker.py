#!/usr/bin/env python3
"""
IP Blocker — Manages iptables rules based on blocked_ips database table
"""

import os
import subprocess
import logging
import pymysql
from datetime import datetime
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

DB_CONFIG = {
    'host':    os.getenv('DB_HOST', 'localhost'),
    'user':    os.getenv('DB_USER', 'root'),
    'password':os.getenv('DB_PASS', ''),
    'db':      os.getenv('DB_NAME', 'cyber_ai_platform'),
    'charset': 'utf8mb4',
}

logger = logging.getLogger(__name__)


def run_iptables(args: list) -> bool:
    """Run an iptables command"""
    try:
        result = subprocess.run(
            ['sudo', 'iptables'] + args,
            capture_output=True, text=True, timeout=10
        )
        if result.returncode != 0:
            logger.error(f"iptables error: {result.stderr}")
            return False
        return True
    except Exception as e:
        logger.error(f"iptables exception: {e}")
        return False


def is_ip_blocked_in_iptables(ip: str) -> bool:
    """Check if IP is already in iptables DROP rule"""
    try:
        result = subprocess.run(
            ['sudo', 'iptables', '-L', 'INPUT', '-n'],
            capture_output=True, text=True, timeout=10
        )
        return ip in result.stdout
    except Exception:
        return False


def block_ip(ip: str) -> bool:
    """Add iptables DROP rule for IP"""
    if is_ip_blocked_in_iptables(ip):
        logger.info(f"IP {ip} already blocked in iptables")
        return True
    
    success = run_iptables(['-I', 'INPUT', '1', '-s', ip, '-j', 'DROP'])
    if success:
        logger.warning(f"BLOCKED IP: {ip}")
        # Also block outbound
        run_iptables(['-I', 'OUTPUT', '1', '-d', ip, '-j', 'DROP'])
    return success


def unblock_ip(ip: str) -> bool:
    """Remove iptables DROP rule for IP"""
    run_iptables(['-D', 'INPUT', '-s', ip, '-j', 'DROP'])
    run_iptables(['-D', 'OUTPUT', '-d', ip, '-j', 'DROP'])
    logger.info(f"UNBLOCKED IP: {ip}")
    return True


def sync_blocked_ips():
    """Sync database blocked IPs with iptables rules"""
    try:
        conn = pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)
        
        # Get active blocks from DB
        with conn.cursor() as cur:
            cur.execute("SELECT ip_address, is_active FROM blocked_ips")
            db_blocked = cur.fetchall()

        for record in db_blocked:
            ip        = record['ip_address']
            is_active = record['is_active']

            if is_active:
                block_ip(ip)
            else:
                if is_ip_blocked_in_iptables(ip):
                    unblock_ip(ip)

        conn.close()
        logger.info(f"Synced {len(db_blocked)} IP rules with iptables")
    except Exception as e:
        logger.error(f"IP sync error: {e}")


def flush_all_blocks():
    """Remove all CyberAI managed blocks (USE WITH CAUTION)"""
    try:
        conn = pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)
        with conn.cursor() as cur:
            cur.execute("SELECT ip_address FROM blocked_ips")
            rows = cur.fetchall()
        conn.close()

        for row in rows:
            unblock_ip(row['ip_address'])

        logger.warning(f"Flushed all {len(rows)} IP blocks")
    except Exception as e:
        logger.error(f"Flush error: {e}")


if __name__ == '__main__':
    import time
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
    logger.info("IP Blocker service started (requires root)")
    while True:
        sync_blocked_ips()
        time.sleep(30)
