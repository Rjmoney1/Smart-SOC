#!/usr/bin/env python3
"""
Alert Sender — Sends notifications via Email and Telegram
"""

import os
import smtplib
import logging
import pymysql
import requests
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from datetime import datetime, timedelta
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


def get_settings() -> dict:
    """Fetch settings from database"""
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor() as cur:
            cur.execute("SELECT setting_key, setting_value FROM settings")
            rows = cur.fetchall()
        conn.close()
        return {k: v for k, v in rows}
    except Exception as e:
        logger.error(f"Failed to load settings: {e}")
        return {}


def send_telegram(bot_token: str, chat_id: str, message: str) -> bool:
    """Send Telegram notification"""
    try:
        url  = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        resp = requests.post(url, json={
            'chat_id':    chat_id,
            'text':       message,
            'parse_mode': 'HTML',
        }, timeout=10)
        return resp.status_code == 200
    except Exception as e:
        logger.error(f"Telegram send failed: {e}")
        return False


def send_email(settings: dict, subject: str, body: str) -> bool:
    """Send email notification"""
    try:
        msg = MIMEMultipart('alternative')
        msg['From']    = f"{settings.get('mail_from_name','CyberAI')} <{settings.get('mail_from','')}>"
        msg['To']      = settings.get('mail_from', '')
        msg['Subject'] = subject
        msg.attach(MIMEText(body, 'html'))

        server = smtplib.SMTP(settings.get('mail_host',''), int(settings.get('mail_port', 587)))
        server.starttls()
        server.login(settings.get('mail_user', ''), settings.get('mail_pass', ''))
        server.send_message(msg)
        server.quit()
        return True
    except Exception as e:
        logger.error(f"Email send failed: {e}")
        return False


def format_alert_message(alert: dict) -> str:
    """Format alert for notification"""
    severity_emoji = {
        'critical': '🚨',
        'high':     '⚠️',
        'medium':   '🔶',
        'low':      '🔵',
        'info':     'ℹ️',
    }.get(alert.get('severity', 'info'), '⚠️')

    return (
        f"{severity_emoji} <b>CyberAI Security Alert</b>\n\n"
        f"<b>Title:</b> {alert.get('title', 'Unknown')}\n"
        f"<b>Severity:</b> {alert.get('severity', 'Unknown').upper()}\n"
        f"<b>Type:</b> {alert.get('attack_type', 'Unknown')}\n"
        f"<b>Source IP:</b> {alert.get('source_ip', 'Unknown')}\n"
        f"<b>Risk Score:</b> {alert.get('risk_score', 'N/A')}/100\n"
        f"<b>Time:</b> {alert.get('created_at', 'Unknown')}\n\n"
        f"<i>{alert.get('description', '')[:200]}</i>"
    )


def send_pending_alerts():
    """Send notifications for unsent critical/high alerts"""
    settings = get_settings()
    email_enabled    = settings.get('alert_email_enabled', '0') == '1'
    telegram_enabled = settings.get('alert_telegram_enabled', '0') == '1'

    if not email_enabled and not telegram_enabled:
        return

    try:
        conn = pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)
        
        # Fetch unsent critical alerts from last 5 minutes
        cutoff = (datetime.now() - timedelta(minutes=5)).strftime('%Y-%m-%d %H:%M:%S')
        with conn.cursor() as cur:
            cur.execute("""
                SELECT * FROM alerts 
                WHERE severity IN ('critical', 'high') 
                  AND created_at >= %s
                  AND status = 'open'
                ORDER BY created_at DESC
                LIMIT 5
            """, (cutoff,))
            alerts = cur.fetchall()

        conn.close()

        for alert in alerts:
            message = format_alert_message(dict(alert))

            if telegram_enabled:
                bot_token = settings.get('telegram_bot_token', '')
                chat_id   = settings.get('telegram_chat_id', '')
                if bot_token and chat_id:
                    success = send_telegram(bot_token, chat_id, message)
                    logger.info(f"Telegram alert for #{alert['id']}: {'sent' if success else 'failed'}")

            if email_enabled:
                subject = f"[{alert['severity'].upper()}] Security Alert: {alert['title']}"
                body    = f"<html><body><pre>{message}</pre></body></html>"
                success = send_email(settings, subject, body)
                logger.info(f"Email alert for #{alert['id']}: {'sent' if success else 'failed'}")

    except Exception as e:
        logger.error(f"Alert sender error: {e}")


if __name__ == '__main__':
    import time
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
    logger.info("Alert Sender started")
    while True:
        send_pending_alerts()
        time.sleep(60)
