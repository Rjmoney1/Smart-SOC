#!/usr/bin/env python3
"""
Scheduler — Orchestrates all Python engine components
Runs log monitor, anomaly detector, threat classifier, alert sender, and Gemini AI
"""

import os
import time
import signal
import logging
import threading
import pymysql
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

# Setup logging
log_dir = Path(__file__).parent.parent / 'logs' / 'system_logs'
log_dir.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s — %(message)s',
    handlers=[
        logging.FileHandler(log_dir / 'scheduler.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger('Scheduler')

# Import engines
from log_monitor       import LogMonitor
from anomaly_detector  import AnomalyDetector
from threat_classifier import classify_unclassified_alerts
from alert_sender      import send_pending_alerts
from gemini_analysis   import analyze_unprocessed_alerts

running = True

# ─── Wait for DB before starting engines ─────────────────────────────────────
def wait_for_db(max_retries=30, interval=5):
    """Block until MySQL is reachable"""
    db_cfg = {
        'host':     os.getenv('DB_HOST', 'db'),
        'user':     os.getenv('DB_USER', 'cyberai'),
        'password': os.getenv('DB_PASS', 'CyberAI_Secure_2024!'),
        'db':       os.getenv('DB_NAME', 'cyber_ai_platform'),
    }
    for i in range(max_retries):
        try:
            conn = pymysql.connect(**db_cfg, connect_timeout=3)
            conn.close()
            logger.info("Database is ready.")
            return True
        except Exception as e:
            logger.warning(f"DB not ready ({i+1}/{max_retries}): {e}")
            time.sleep(interval)
    logger.error("Could not connect to database after maximum retries.")
    return False

def signal_handler(sig, frame):
    global running
    logger.info("Shutdown signal received. Stopping all engines...")
    running = False

signal.signal(signal.SIGINT,  signal_handler)
signal.signal(signal.SIGTERM, signal_handler)


def run_log_monitor():
    """Continuous log file monitoring"""
    monitor = LogMonitor()
    monitor.run()


def run_anomaly_detector():
    """Periodic anomaly detection every 5 minutes"""
    detector = AnomalyDetector()
    while running:
        try:
            detector.train_and_detect(detector.get_db())
        except Exception as e:
            logger.error(f"Anomaly detector error: {e}")
        time.sleep(300)


def run_threat_classifier():
    """Periodic threat classification every 60 seconds"""
    while running:
        try:
            count = classify_unclassified_alerts()
            if count: logger.info(f"Classified {count} alerts")
        except Exception as e:
            logger.error(f"Threat classifier error: {e}")
        time.sleep(60)


def run_alert_sender():
    """Periodic alert notification sending every 60 seconds"""
    while running:
        try:
            send_pending_alerts()
        except Exception as e:
            logger.error(f"Alert sender error: {e}")
        time.sleep(60)


def run_gemini_analysis():
    """Periodic Gemini AI analysis every 2 minutes"""
    while running:
        try:
            analyze_unprocessed_alerts()
        except Exception as e:
            logger.error(f"Gemini analysis error: {e}")
        time.sleep(120)


def main():
    logger.info("=" * 60)
    logger.info("  CyberAI Platform — Python Engine Starting")
    logger.info("=" * 60)

    # Wait for MySQL to be ready before launching threads
    if not wait_for_db():
        logger.error("Database unavailable. Exiting.")
        return

    threads = [
        threading.Thread(target=run_log_monitor,      name='LogMonitor',      daemon=True),
        threading.Thread(target=run_anomaly_detector, name='AnomalyDetector', daemon=True),
        threading.Thread(target=run_threat_classifier,name='ThreatClassifier',daemon=True),
        threading.Thread(target=run_alert_sender,     name='AlertSender',     daemon=True),
        threading.Thread(target=run_gemini_analysis,  name='GeminiAnalysis',  daemon=True),
    ]

    for t in threads:
        t.start()
        logger.info(f"  ✓ Started: {t.name}")

    logger.info("=" * 60)
    logger.info("  All engines running. Press Ctrl+C to stop.")
    logger.info("=" * 60)

    while running:
        alive = [t.name for t in threads if t.is_alive()]
        logger.info(f"Health: {len(alive)}/5 threads alive — {', '.join(alive)}")
        time.sleep(300)


if __name__ == '__main__':
    main()
