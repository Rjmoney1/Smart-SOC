# ─────────────────────────────────────────────────────────────────────────────
#  CyberAI Platform — Python Engine Dockerfile
# ─────────────────────────────────────────────────────────────────────────────
FROM python:3.11-slim

# Install system deps for scapy (libpcap) + build tools for scikit-learn
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpcap-dev \
        gcc \
        libffi-dev \
        libssl-dev \
        && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install Python dependencies first (layer cache)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy engine source
COPY . .

# Default: run scheduler (orchestrates all engines) + traffic simulator
CMD ["python", "-u", "scheduler.py"]
