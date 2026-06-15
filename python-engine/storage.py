#!/usr/bin/env python3
"""
Storage Adapter — Supports both SQLite (no-DB mode) and MySQL
Set USE_SQLITE=true in .env to use SQLite instead of MySQL
"""

import os
import json
import sqlite3
import logging
from datetime import datetime
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

USE_SQLITE = os.getenv('USE_SQLITE', 'false').lower() == 'true'
SQLITE_PATH = Path(__file__).parent.parent / 'logs' / 'cyberai.db'

logger = logging.getLogger(__name__)


# ─────────────────────────────────────────────
#  SQLite Backend
# ─────────────────────────────────────────────
def get_sqlite():
    """Get SQLite connection with WAL mode for concurrent access"""
    SQLITE_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(SQLITE_PATH), timeout=10, check_same_thread=False)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


def init_sqlite():
    """Create tables if they don't exist (SQLite mode)"""
    conn = get_sqlite()
    conn.executescript("""
        CREATE TABLE IF NOT EXISTS logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            type        TEXT NOT NULL DEFAULT 'system',
            action      TEXT NOT NULL,
            message     TEXT NOT NULL,
            severity    TEXT NOT NULL DEFAULT 'info',
            user_id     INTEGER DEFAULT 0,
            ip_address  TEXT DEFAULT NULL,
            raw_data    TEXT DEFAULT NULL,
            created_at  TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS alerts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT NOT NULL,
            description TEXT DEFAULT NULL,
            severity    TEXT NOT NULL DEFAULT 'medium',
            status      TEXT NOT NULL DEFAULT 'open',
            attack_type TEXT DEFAULT NULL,
            source_ip   TEXT DEFAULT NULL,
            target_ip   TEXT DEFAULT NULL,
            source_port INTEGER DEFAULT NULL,
            target_port INTEGER DEFAULT NULL,
            protocol    TEXT DEFAULT NULL,
            risk_score  INTEGER DEFAULT 0,
            ai_analysis TEXT DEFAULT NULL,
            raw_log     TEXT DEFAULT NULL,
            created_at  TEXT NOT NULL,
            updated_at  TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS blocked_ips (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address  TEXT NOT NULL UNIQUE,
            reason      TEXT DEFAULT NULL,
            is_active   INTEGER NOT NULL DEFAULT 1,
            blocked_by  INTEGER DEFAULT NULL,
            blocked_at  TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_logs_created   ON logs(created_at);
        CREATE INDEX IF NOT EXISTS idx_alerts_sev     ON alerts(severity);
        CREATE INDEX IF NOT EXISTS idx_alerts_created ON alerts(created_at);
        CREATE INDEX IF NOT EXISTS idx_blocked_ip     ON blocked_ips(ip_address);
    """)
    conn.commit()
    conn.close()
    logger.info(f"SQLite database initialized at: {SQLITE_PATH}")


# ─────────────────────────────────────────────
#  MySQL Backend
# ─────────────────────────────────────────────
def get_mysql():
    import pymysql
    return pymysql.connect(
        host=os.getenv('DB_HOST', 'localhost'),
        port=int(os.getenv('DB_PORT', 3306)),
        user=os.getenv('DB_USER', 'root'),
        password=os.getenv('DB_PASS', ''),
        db=os.getenv('DB_NAME', 'cyber_ai_platform'),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=5,
    )


# ─────────────────────────────────────────────
#  Unified API
# ─────────────────────────────────────────────
class Storage:
    """Unified storage: use SQLite or MySQL transparently"""

    def __init__(self):
        self.mode = 'sqlite' if USE_SQLITE else 'mysql'
        if USE_SQLITE:
            init_sqlite()
            logger.info("Storage mode: SQLite (no-database mode)")
        else:
            logger.info("Storage mode: MySQL")

    def _conn(self):
        return get_sqlite() if USE_SQLITE else get_mysql()

    def insert_log(self, log_type, action, message, severity='info', ip=None, raw=None):
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        raw_str = json.dumps(raw) if raw else None

        try:
            if USE_SQLITE:
                conn = get_sqlite()
                conn.execute(
                    "INSERT INTO logs (type,action,message,severity,ip_address,raw_data,created_at) "
                    "VALUES (?,?,?,?,?,?,?)",
                    (log_type, action, message, severity, ip, raw_str, now)
                )
                conn.commit()
                conn.close()
            else:
                conn = get_mysql()
                with conn.cursor() as cur:
                    cur.execute(
                        "INSERT INTO logs (type,action,message,severity,ip_address,raw_data,created_at) "
                        "VALUES (%s,%s,%s,%s,%s,%s,%s)",
                        (log_type, action, message, severity, ip, raw_str, now)
                    )
                conn.commit()
                conn.close()
        except Exception as e:
            logger.error(f"insert_log failed: {e}")

    def insert_alert(self, title, description, severity, attack_type,
                     source_ip=None, target_port=None, protocol=None, risk_score=None):
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        if risk_score is None:
            risk_score = {'critical':90,'high':70,'medium':50,'low':25,'info':10}.get(severity,50)

        try:
            if USE_SQLITE:
                conn = get_sqlite()
                cur = conn.execute(
                    "INSERT INTO alerts (title,description,severity,status,attack_type,"
                    "source_ip,target_port,protocol,risk_score,created_at,updated_at) "
                    "VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    (title,description,severity,'open',attack_type,
                     source_ip,target_port,protocol,risk_score,now,now)
                )
                conn.commit()
                row_id = cur.lastrowid
                conn.close()
                return row_id
            else:
                conn = get_mysql()
                with conn.cursor() as cur:
                    cur.execute(
                        "INSERT INTO alerts (title,description,severity,status,attack_type,"
                        "source_ip,target_port,protocol,risk_score,created_at,updated_at) "
                        "VALUES (%s,%s,%s,'open',%s,%s,%s,%s,%s,%s,%s)",
                        (title,description,severity,attack_type,
                         source_ip,target_port,protocol,risk_score,now,now)
                    )
                conn.commit()
                last_id = cur.lastrowid
                conn.close()
                return last_id
        except Exception as e:
            logger.error(f"insert_alert failed: {e}")
            return None

    def block_ip(self, ip, reason='Auto-blocked'):
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        try:
            if USE_SQLITE:
                conn = get_sqlite()
                conn.execute(
                    "INSERT INTO blocked_ips (ip_address,reason,is_active,blocked_at) "
                    "VALUES (?,?,1,?) ON CONFLICT(ip_address) DO UPDATE SET is_active=1,blocked_at=?",
                    (ip, reason, now, now)
                )
                conn.commit()
                conn.close()
            else:
                conn = get_mysql()
                with conn.cursor() as cur:
                    cur.execute(
                        "INSERT INTO blocked_ips (ip_address,reason,is_active,blocked_at) "
                        "VALUES (%s,%s,1,%s) ON DUPLICATE KEY UPDATE is_active=1,blocked_at=%s",
                        (ip, reason, now, now)
                    )
                conn.commit()
                conn.close()
        except Exception as e:
            logger.error(f"block_ip failed: {e}")

    def fetchall(self, query, params=()):
        """Run a SELECT and return list of dicts"""
        try:
            if USE_SQLITE:
                # Convert %s placeholders to ? for SQLite
                q = query.replace('%s', '?')
                conn = get_sqlite()
                rows = conn.execute(q, params).fetchall()
                conn.close()
                return [dict(r) for r in rows]
            else:
                conn = get_mysql()
                with conn.cursor() as cur:
                    cur.execute(query, params)
                    rows = cur.fetchall()
                conn.close()
                return rows
        except Exception as e:
            logger.error(f"fetchall failed: {e}")
            return []

    def count(self, table, where='1', params=()):
        try:
            q = f"SELECT COUNT(*) as cnt FROM {table} WHERE {where}"
            rows = self.fetchall(q, params)
            return rows[0]['cnt'] if rows else 0
        except Exception as e:
            logger.error(f"count failed: {e}")
            return 0


# Singleton
_storage = None

def storage() -> Storage:
    global _storage
    if _storage is None:
        _storage = Storage()
    return _storage
