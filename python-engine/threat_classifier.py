#!/usr/bin/env python3
"""
Threat Classifier — Classifies attacks by type, severity, and risk score
"""

import re
import os
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

# Threat classification rules
THREAT_RULES = [
    {
        'pattern':    re.compile(r'brute.?force|multiple failed|repeated.*login|47 failed|failed.*attempt', re.I),
        'type':       'Brute Force',
        'severity':   'critical',
        'risk_score': 90,
    },
    {
        'pattern':    re.compile(r'port.?scan|sequential.*port|nmap|masscan', re.I),
        'type':       'Port Scan',
        'severity':   'high',
        'risk_score': 70,
    },
    {
        'pattern':    re.compile(r'sql.?injection|sqlmap|union.*select|drop.*table|1=1', re.I),
        'type':       'SQL Injection',
        'severity':   'high',
        'risk_score': 75,
    },
    {
        'pattern':    re.compile(r'xss|cross.?site.?script|<script|alert\(', re.I),
        'type':       'XSS',
        'severity':   'medium',
        'risk_score': 55,
    },
    {
        'pattern':    re.compile(r'shellshock|bash.*exploit|cve.?2014.?6271', re.I),
        'type':       'Remote Code Execution',
        'severity':   'critical',
        'risk_score': 98,
    },
    {
        'pattern':    re.compile(r'dos|denial.?of.?service|flood|icmp.*flood|syn.*flood', re.I),
        'type':       'DoS Attack',
        'severity':   'high',
        'risk_score': 65,
    },
    {
        'pattern':    re.compile(r'exfil|data.*transfer|suspicious.*outbound|large.*upload', re.I),
        'type':       'Data Exfiltration',
        'severity':   'critical',
        'risk_score': 95,
    },
    {
        'pattern':    re.compile(r'privilege.*escal|sudo.*fail|unauthorized.*root|su.*fail', re.I),
        'type':       'Privilege Escalation',
        'severity':   'high',
        'risk_score': 80,
    },
    {
        'pattern':    re.compile(r'ransomware|encrypt.*file|ransom|\.locked|cryptolocker', re.I),
        'type':       'Ransomware',
        'severity':   'critical',
        'risk_score': 99,
    },
    {
        'pattern':    re.compile(r'phishing|spear.?phish|credential.*harvest|fake.*login', re.I),
        'type':       'Phishing',
        'severity':   'high',
        'risk_score': 72,
    },
    {
        'pattern':    re.compile(r'malware|trojan|backdoor|c2|command.*control|rat\b', re.I),
        'type':       'Malware',
        'severity':   'critical',
        'risk_score': 88,
    },
    {
        'pattern':    re.compile(r'anoma|unusual|suspicious|abnormal', re.I),
        'type':       'Anomalous Behavior',
        'severity':   'medium',
        'risk_score': 50,
    },
]

def classify_alert(title: str, description: str) -> dict:
    """Classify an alert based on title and description"""
    text = f"{title} {description}".lower()
    
    best_match = None
    for rule in THREAT_RULES:
        if rule['pattern'].search(text):
            if best_match is None or rule['risk_score'] > best_match['risk_score']:
                best_match = {
                    'attack_type': rule['type'],
                    'severity':    rule['severity'],
                    'risk_score':  rule['risk_score'],
                }

    return best_match or {
        'attack_type': 'Unknown Threat',
        'severity':    'medium',
        'risk_score':  40,
    }


def classify_unclassified_alerts():
    """Find alerts without proper classification and classify them"""
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor(pymysql.cursors.DictCursor) as cur:
            cur.execute("""
                SELECT id, title, description, attack_type FROM alerts
                WHERE attack_type IS NULL OR attack_type = '' OR attack_type = 'Unknown'
                ORDER BY created_at DESC
                LIMIT 50
            """)
            alerts = cur.fetchall()

        for alert in alerts:
            classification = classify_alert(alert['title'] or '', alert['description'] or '')
            with conn.cursor() as cur:
                cur.execute("""
                    UPDATE alerts SET attack_type=%s, severity=%s, risk_score=%s, updated_at=%s
                    WHERE id=%s AND (attack_type IS NULL OR attack_type = '' OR attack_type = 'Unknown')
                """, (
                    classification['attack_type'],
                    classification['severity'],
                    classification['risk_score'],
                    datetime.now(),
                    alert['id']
                ))
            conn.commit()
            logger.info(f"Classified alert #{alert['id']} as {classification['attack_type']} ({classification['severity']})")

        conn.close()
        return len(alerts)
    except Exception as e:
        logger.error(f"Classification error: {e}")
        return 0


if __name__ == '__main__':
    import time
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
    logger.info("Threat Classifier started")
    while True:
        count = classify_unclassified_alerts()
        logger.info(f"Classified {count} alerts")
        time.sleep(60)
