# 🛡️ CyberAI Platform

> **Enterprise-Grade AI-Powered Cybersecurity Monitoring & Automated Incident Response Platform**

CyberAI Platform is a full-stack, enterprise-grade Security Operations Center (SOC) solution. It integrates rule-based and ML-based threat detection with advanced AI-driven security analysis. The platform supports **Cloud AI (Google Gemini)** and **Local/Offline AI (Ollama Llama3 or Template Engines)** to analyze security alerts and provide actionable mitigation advice.

---

## 🏗️ System Architecture

The platform consists of two main components:
1. **PHP Web Dashboard**: A dark-themed SOC interface displaying real-time alert logs, statistics, network metrics, and an interface to trigger AI reports or manually block attacker IPs.
2. **Python Security Engine**: A set of background daemons running packet sniffing, log monitoring, Isolation Forest ML anomaly detection, and automated iptables IP blocking.

```
cyber-ai-platform/
├── dashboard/               # PHP Web Application (Frontend + REST API)
│   ├── api/                 # Endpoint logic (get_alerts, stats, block_ip, etc.)
│   ├── includes/            # DB, Auth, and session components
│   └── reports.php          # AI Report viewing and creation
├── python-engine/           # Security Monitoring Engine
│   ├── scheduler.py         # Orchestrator daemon running all sub-engines
│   ├── anomaly_detector.py  # ML anomaly detector (Isolation Forest)
│   ├── packet_sniffer.py    # Network sniffer (Scapy)
│   ├── gemini_analysis.py   # AI Integrations (Ollama, Gemini, Local)
│   └── traffic_simulator.py # Live attack generator for demonstration
└── database/                # MySQL database schema and mock seeds
```

---

## ⚙️ AI Configuration: Local vs. Cloud

The platform supports three AI providers configured in your `.env` file:

### Option 1: Local Ollama LLM (Offline AI)
Run a self-hosted LLM (e.g., Llama 3) locally without sending telemetry data online.
1. Install and run [Ollama](https://ollama.com).
2. Download your preferred model:
   ```bash
   ollama run llama3
   ```
3. Configure your `.env`:
   ```env
   AI_PROVIDER=ollama
   OLLAMA_API_URL=http://localhost:11434
   OLLAMA_MODEL=llama3
   ```

### Option 2: Local Static Rules (Zero Dependencies)
A fully-offline rule template engine that generates instant reports locally without LLM resource overhead.
```env
AI_PROVIDER=local
```

### Option 3: Google Gemini (Cloud AI)
Leverage Google's high-capacity Gemini 1.5 API models.
1. Get an API key from [Google AI Studio](https://aistudio.google.com).
2. Configure your `.env`:
   ```env
   AI_PROVIDER=gemini
   GEMINI_API_KEY=your_google_api_key_here
   GEMINI_MODEL=gemini-1.5-pro
   ```

---

## 🚀 Quick Start & Running Examples

### 1. Database Setup
Import the database schema and seed mock data:
```bash
mysql -u root -p -e "CREATE DATABASE cyber_ai_platform;"
mysql -u root -p cyber_ai_platform < database/schema.sql
mysql -u root -p cyber_ai_platform < database/seed.sql
```

### 2. Configure Environment `.env`
Copy `.env.example` to `.env` (or update existing `.env`):
```env
DB_HOST=localhost
DB_USER=cyberai
DB_PASS=your_password
DB_NAME=cyber_ai_platform

# AI Configuration
AI_PROVIDER=ollama
OLLAMA_API_URL=http://localhost:11434
OLLAMA_MODEL=llama3
```

### 3. Run the Security Engine (Scheduler)
The scheduler initiates log monitoring, threat classification, notifications, and LLM report processors.
```bash
cd python-engine
pip install -r requirements.txt
python scheduler.py
```
**Example Log Output:**
```
2026-06-15 22:36:01 [INFO] Scheduler — Database is ready.
2026-06-15 22:36:02 [INFO] Scheduler — Starting log monitor thread...
2026-06-15 22:36:02 [INFO] Scheduler — Running threat classifier...
2026-06-15 22:36:05 [INFO] Scheduler — Classified 3 alerts
2026-06-15 22:37:10 [INFO] AnomalyDetector — Extracting features for ML training...
2026-06-15 22:37:12 [INFO] AnomalyDetector — Isolation Forest trained. Anomalies detected: 2
```

### 4. Run the Network Packet Sniffer (Requires Root/Admin)
The packet sniffer intercepts real-time interface packets to catch port scanning and flooded connection anomalies.
```bash
sudo python packet_sniffer.py
```

### 5. Generate Live Mock Attack Events (Demo Simulation)
If you want to quickly test the dashboard with live moving data:
```bash
python traffic_simulator.py
```
**Example Output:**
```
2026-06-15 22:38:00 [INFO] TrafficSim — Injected event: SSH Brute Force Attack from 185.220.101.34
2026-06-15 22:38:15 [INFO] TrafficSim — Injected event: Port Scan from 45.33.32.156 targeting 22 ports
```

---

## 📡 API Reference & Payload Examples

The platform provides standard HTTP endpoints. You must be authenticated to invoke these actions.

### 1. Retrieve Alerts (GET)
Get a list of recent security events.
* **Request:**
  ```bash
  curl -X GET "http://localhost/dashboard/api/get_alerts.php?limit=2&severity=high" \
       -H "Cookie: PHPSESSID=your_session_cookie"
  ```
* **JSON Response Example:**
  ```json
  {
    "success": true,
    "alerts": [
      {
        "id": 104,
        "title": "SSH Brute Force Attack — 185.220.101.34",
        "severity": "high",
        "status": "open",
        "attack_type": "Brute Force",
        "source_ip": "185.220.101.34",
        "risk_score": 85,
        "created_at": "2026-06-15 22:38:00"
      }
    ]
  }
  ```

### 2. Request AI Analysis for an Alert (POST)
Instruct the Local AI or Gemini API to analyze a specific incident and provide mitigation steps.
* **Request:**
  ```bash
  curl -X POST "http://localhost/dashboard/api/ai_analysis.php" \
       -H "Content-Type: application/json" \
       -H "Cookie: PHPSESSID=your_session_cookie" \
       -d '{"type": "alert", "alert_id": 104}'
  ```
* **JSON Response Example:**
  ```json
  {
    "success": true,
    "analysis": "### 🛡️ Threat Assessment\n- **Type**: SSH Brute Force Attempt.\n- **Risk**: Critical. Unauthorized access opens administrative consoles.\n\n### 🛠️ Immediate Mitigation\n1. Block IP `185.220.101.34` using firewall rules.\n2. Revoke active terminal sessions."
  }
  ```

### 3. Block Threat Source IP (POST)
Trigger iptables firewall rule commands dynamically from the PHP dashboard.
* **Request:**
  ```bash
  curl -X POST "http://localhost/dashboard/api/block_ip.php" \
       -H "Content-Type: application/json" \
       -H "Cookie: PHPSESSID=your_session_cookie" \
       -d '{"ip": "185.220.101.34", "reason": "SSH Brute Force"}'
  ```
* **JSON Response Example:**
  ```json
  {
    "success": true,
    "message": "IP address 185.220.101.34 successfully blocked in system firewall."
  }
  ```
