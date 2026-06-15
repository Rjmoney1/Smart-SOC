#!/usr/bin/env python3
"""
Anomaly Detector — ML-based anomaly detection using Isolation Forest
Detects: anomalous login behavior, abnormal traffic patterns
"""

import os
import json
import time
import logging
import numpy as np
import pymysql
from datetime import datetime, timedelta
from pathlib import Path
from dotenv import load_dotenv
from sklearn.ensemble import IsolationForest
from sklearn.preprocessing import StandardScaler

load_dotenv(Path(__file__).parent.parent / '.env')

DB_CONFIG = {
    'host':    os.getenv('DB_HOST', 'localhost'),
    'user':    os.getenv('DB_USER', 'root'),
    'password':os.getenv('DB_PASS', ''),
    'db':      os.getenv('DB_NAME', 'cyber_ai_platform'),
    'charset': 'utf8mb4',
}

logger = logging.getLogger(__name__)

class AnomalyDetector:
    def __init__(self):
        self.model      = IsolationForest(contamination=0.1, random_state=42, n_estimators=100)
        self.scaler     = StandardScaler()
        self.trained    = False

    def get_db(self):
        return pymysql.connect(**DB_CONFIG)

    def get_features(self, conn, hours=24):
        """Extract features from recent log entries"""
        cutoff = (datetime.now() - timedelta(hours=hours)).strftime('%Y-%m-%d %H:%M:%S')
        with conn.cursor() as cur:
            # Failed login counts per IP per hour
            cur.execute("""
                SELECT ip_address,
                       COUNT(*) as total_events,
                       SUM(CASE WHEN severity IN ('critical','high') THEN 1 ELSE 0 END) as high_severity,
                       SUM(CASE WHEN action LIKE '%fail%' THEN 1 ELSE 0 END) as failures,
                       COUNT(DISTINCT action) as unique_actions,
                       HOUR(MAX(created_at)) as last_hour
                FROM logs
                WHERE created_at >= %s AND ip_address IS NOT NULL
                GROUP BY ip_address
            """, (cutoff,))
            rows = cur.fetchall()
        return rows

    def get_alert_features(self, conn, hours=24):
        """Extract alert-based features"""
        cutoff = (datetime.now() - timedelta(hours=hours)).strftime('%Y-%m-%d %H:%M:%S')
        with conn.cursor() as cur:
            cur.execute("""
                SELECT source_ip,
                       COUNT(*) as alert_count,
                       AVG(risk_score) as avg_risk,
                       MAX(risk_score) as max_risk,
                       COUNT(DISTINCT attack_type) as attack_variety,
                       COUNT(DISTINCT target_port) as ports_targeted
                FROM alerts
                WHERE created_at >= %s AND source_ip IS NOT NULL
                GROUP BY source_ip
            """, (cutoff,))
            rows = cur.fetchall()
        return rows

    def train_and_detect(self, conn):
        features_rows = self.get_features(conn)
        if len(features_rows) < 5:
            logger.info("Not enough data to train anomaly detector. Need at least 5 data points.")
            return

        # Build feature matrix
        feature_matrix = []
        ip_map = []
        for row in features_rows:
            ip, total, high, failures, unique, last_hour = row
            # Cast all MySQL values to int/float explicitly (MySQL may return strings)
            total     = int(total    or 0)
            high      = int(high     or 0)
            failures  = int(failures or 0)
            unique    = int(unique   or 0)
            last_hour = int(last_hour or 0)
            fail_ratio = float(failures) / float(max(total, 1))
            feature_matrix.append([
                float(total),
                float(high),
                float(failures),
                float(unique),
                float(last_hour),
                fail_ratio,
            ])
            ip_map.append(ip)

        X = np.array(feature_matrix)
        X_scaled = self.scaler.fit_transform(X)
        self.model.fit(X_scaled)
        predictions   = self.model.predict(X_scaled)
        anomaly_scores = self.model.score_samples(X_scaled)

        anomalies = []
        for i, (pred, score) in enumerate(zip(predictions, anomaly_scores)):
            if pred == -1:  # Anomaly
                ip     = ip_map[i]
                feats  = feature_matrix[i]
                severity = 'critical' if score < -0.5 else ('high' if score < -0.3 else 'medium')
                risk_score = min(100, int(abs(score) * 100))

                anomalies.append({
                    'ip':         ip,
                    'score':      score,
                    'severity':   severity,
                    'risk_score': risk_score,
                    'features':   feats,
                })
                logger.warning(f"ANOMALY DETECTED: {ip} | Score: {score:.3f} | Severity: {severity}")

        # Insert anomaly alerts
        for anomaly in anomalies:
            # Check if we already alerted for this recently
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT id FROM alerts 
                    WHERE source_ip=%s AND attack_type='Anomalous Behavior' 
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                """, (anomaly['ip'],))
                if cur.fetchone():
                    continue

            total, high, failures, unique, _, fail_ratio = anomaly['features']
            description = (
                f"ML Anomaly Detection (Isolation Forest) flagged {anomaly['ip']}.\n"
                f"Events: {int(total)}, High Severity: {int(high)}, "
                f"Failures: {int(failures)}, Failure Ratio: {fail_ratio:.1%}, "
                f"Anomaly Score: {anomaly['score']:.3f}"
            )
            with conn.cursor() as cur:
                cur.execute("""
                    INSERT INTO alerts (title, description, severity, status, attack_type, source_ip, risk_score, created_at)
                    VALUES (%s, %s, %s, 'open', 'Anomalous Behavior', %s, %s, %s)
                """, (
                    f"Anomalous Behavior Detected — {anomaly['ip']}",
                    description,
                    anomaly['severity'],
                    anomaly['ip'],
                    anomaly['risk_score'],
                    datetime.now()
                ))
            conn.commit()

        logger.info(f"Anomaly detection complete. Found {len(anomalies)} anomalies from {len(features_rows)} IPs.")
        return anomalies

    def run(self, interval=300):
        logger.info("Anomaly Detector started")
        while True:
            try:
                conn = self.get_db()
                self.train_and_detect(conn)
                conn.close()
            except Exception as e:
                logger.error(f"Anomaly detector error: {e}")
            time.sleep(interval)

if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
    detector = AnomalyDetector()
    detector.run()
