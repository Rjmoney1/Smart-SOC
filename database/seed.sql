-- ==================================================
-- CyberAI Platform — Seed Data
-- ==================================================
USE cyber_ai_platform;

-- ==================================================
-- USERS (password = Admin@123 for admin, Analyst@123 for analyst)
-- ==================================================
-- Passwords: admin=Admin@123  analyst/viewer=Analyst@123
-- Hashes generated with password_hash(..., PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO users (username, email, password, full_name, role, is_active, created_at) VALUES
('admin',   'admin@cyberai.local',   '$2y$12$RmMkdsBCl7ba4SXZTt6JPe82PJeeCgp6j2NKHeLDIW22mnX4fFqDy', 'System Administrator', 'admin',   1, NOW()),
('analyst', 'analyst@cyberai.local', '$2y$12$jA56GOFaWpHcvPPkZhhnMO42eeUGSG7rn9XiLZOe3A95nL1yvbzwa', 'Security Analyst',    'analyst', 1, NOW()),
('viewer',  'viewer@cyberai.local',  '$2y$12$jA56GOFaWpHcvPPkZhhnMO42eeUGSG7rn9XiLZOe3A95nL1yvbzwa', 'SOC Viewer',          'viewer',  1, NOW())
ON DUPLICATE KEY UPDATE id=id;

-- ==================================================
-- SETTINGS (defaults)
-- ==================================================
INSERT INTO settings (setting_key, setting_value) VALUES
('gemini_api_key', ''),
('gemini_model', 'gemini-1.5-pro'),
('alert_email_enabled', '0'),
('alert_telegram_enabled', '0'),
('log_retention_days', '30'),
('alert_threshold_critical', '1'),
('alert_threshold_high', '5'),
('platform_version', '1.0.0')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- ==================================================
-- SAMPLE ALERTS
-- ==================================================
INSERT INTO alerts (title, description, severity, status, attack_type, source_ip, target_ip, source_port, target_port, protocol, risk_score, created_at) VALUES
('SSH Brute Force Attack', 'Multiple failed SSH login attempts detected from same IP. 47 failed attempts in 3 minutes.', 'critical', 'open', 'Brute Force', '203.0.113.42', '10.0.0.5', NULL, 22, 'TCP', 92, NOW() - INTERVAL 15 MINUTE),
('Port Scan Detected', 'Sequential port scanning activity detected. Ports 1-1024 scanned in rapid succession.', 'high', 'investigating', 'Port Scan', '198.51.100.87', '10.0.0.1', NULL, NULL, 'TCP/UDP', 75, NOW() - INTERVAL 45 MINUTE),
('Suspicious Sudo Command', 'Unauthorized sudo usage detected. User tried to escalate privileges.', 'high', 'open', 'Privilege Escalation', '10.0.0.15', '10.0.0.5', NULL, NULL, 'System', 70, NOW() - INTERVAL 2 HOUR),
('Anomalous Network Traffic', 'Unusually high outbound traffic detected. Possible data exfiltration.', 'critical', 'open', 'Data Exfiltration', '10.0.0.22', '185.220.101.34', NULL, 4444, 'TCP', 95, NOW() - INTERVAL 30 MINUTE),
('Failed Authentication - Multiple Users', 'Multiple users experiencing authentication failures from same subnet.', 'medium', 'open', 'Credential Stuffing', '192.168.1.0', '10.0.0.5', NULL, 443, 'HTTPS', 55, NOW() - INTERVAL 1 HOUR),
('Malicious DNS Query', 'DNS query to known malicious domain detected.', 'high', 'resolved', 'DNS Poisoning', '10.0.0.31', '8.8.8.8', NULL, 53, 'UDP', 68, NOW() - INTERVAL 3 HOUR),
('ICMP Flood', 'ICMP flood attack targeting server. 10,000 pings/sec detected.', 'medium', 'resolved', 'DoS Attack', '45.33.32.156', '10.0.0.1', NULL, NULL, 'ICMP', 62, NOW() - INTERVAL 4 HOUR),
('SQL Injection Attempt', 'SQL injection patterns detected in web request parameters.', 'high', 'false_positive', 'SQL Injection', '172.16.0.55', '10.0.0.10', 43210, 80, 'HTTP', 65, NOW() - INTERVAL 6 HOUR),
('Unauthorized FTP Access', 'Anonymous FTP access attempt detected on production server.', 'low', 'resolved', 'Unauthorized Access', '203.0.113.100', '10.0.0.5', NULL, 21, 'FTP', 35, NOW() - INTERVAL 8 HOUR),
('Shellshock Exploit Attempt', 'Bash shellshock vulnerability exploitation attempt detected.', 'critical', 'open', 'Remote Code Execution', '198.51.100.200', '10.0.0.10', NULL, 80, 'HTTP', 98, NOW() - INTERVAL 20 MINUTE),
('Suspicious Cron Job Added', 'New cron job added by non-privileged user.', 'medium', 'investigating', 'Persistence', '10.0.0.40', NULL, NULL, NULL, 'System', 50, NOW() - INTERVAL 5 HOUR),
('XSS Attack Attempt', 'Cross-site scripting payload detected in HTTP request.', 'low', 'false_positive', 'XSS', '192.168.100.1', '10.0.0.10', NULL, 80, 'HTTP', 30, NOW() - INTERVAL 7 HOUR);

-- ==================================================
-- SAMPLE LOGS
-- ==================================================
INSERT INTO logs (type, action, message, severity, ip_address, created_at) VALUES
('auth',   'login',         'User admin logged in successfully',                     'info',     '127.0.0.1',      NOW() - INTERVAL 10 MINUTE),
('attack', 'detect',        'SSH brute force detected from 203.0.113.42',            'critical', '203.0.113.42',   NOW() - INTERVAL 15 MINUTE),
('system', 'startup',       'Python monitoring engine started',                      'info',     '127.0.0.1',      NOW() - INTERVAL 2 HOUR),
('auth',   'fail',          'Failed login attempt for user root from 203.0.113.42', 'high',     '203.0.113.42',   NOW() - INTERVAL 16 MINUTE),
('attack', 'portscan',      'Port scan detected from 198.51.100.87',                 'high',     '198.51.100.87',  NOW() - INTERVAL 45 MINUTE),
('system', 'block_ip',      'IP 203.0.113.42 automatically blocked after 47 failed SSH attempts', 'high', '203.0.113.42', NOW() - INTERVAL 14 MINUTE),
('api',    'ai_analysis',   'Gemini AI analysis requested for alert #1',             'info',     '127.0.0.1',      NOW() - INTERVAL 5 MINUTE),
('system', 'log_rotation',  'Log rotation completed. 1,247 entries archived',        'info',     '127.0.0.1',      NOW() - INTERVAL 1 HOUR),
('auth',   'logout',        'User analyst logged out',                               'info',     '192.168.1.100',  NOW() - INTERVAL 30 MINUTE),
('attack', 'detect',        'SQL injection attempt from 172.16.0.55 on /api/users',  'high',     '172.16.0.55',    NOW() - INTERVAL 6 HOUR),
('system', 'scan',          'Network scan completed. 254 hosts discovered',          'info',     '10.0.0.1',       NOW() - INTERVAL 4 HOUR),
('attack', 'exfiltration',  'Anomalous data transfer detected: 2.3GB to external IP','critical', '10.0.0.22',      NOW() - INTERVAL 30 MINUTE),
('auth',   'register',      'New user registered: viewer',                           'info',     '127.0.0.1',      NOW() - INTERVAL 3 DAY),
('system', 'settings',      'Platform settings updated by admin',                    'info',     '127.0.0.1',      NOW() - INTERVAL 1 DAY),
('attack', 'detect',        'RCE exploit attempt via Shellshock CVE-2014-6271',      'critical', '198.51.100.200', NOW() - INTERVAL 20 MINUTE);

-- ==================================================
-- SAMPLE BLOCKED IPs
-- ==================================================
INSERT INTO blocked_ips (ip_address, reason, is_active, blocked_by, blocked_at) VALUES
('203.0.113.42',  'SSH brute force - 47 failed attempts in 3 minutes', 1, 1, NOW() - INTERVAL 14 MINUTE),
('198.51.100.87', 'Systematic port scanning activity',                 1, 1, NOW() - INTERVAL 40 MINUTE),
('185.220.101.34','Known Tor exit node - suspicious traffic',           1, 1, NOW() - INTERVAL 25 MINUTE)
ON DUPLICATE KEY UPDATE is_active=1;

-- ==================================================
-- SAMPLE ATTACK HISTORY
-- ==================================================
INSERT INTO attack_history (source_ip, target_ip, attack_type, severity, protocol, risk_score, is_blocked, created_at) VALUES
('203.0.113.42',   '10.0.0.5',  'Brute Force',          'critical', 'TCP',  92, 1, NOW() - INTERVAL 15 MINUTE),
('198.51.100.87',  '10.0.0.1',  'Port Scan',            'high',     'TCP',  75, 1, NOW() - INTERVAL 45 MINUTE),
('10.0.0.22',      '185.220.101.34','Data Exfiltration', 'critical', 'TCP',  95, 0, NOW() - INTERVAL 30 MINUTE),
('45.33.32.156',   '10.0.0.1',  'DoS Attack',           'medium',   'ICMP', 62, 0, NOW() - INTERVAL 4 HOUR),
('198.51.100.200', '10.0.0.10', 'Remote Code Execution','critical', 'HTTP', 98, 0, NOW() - INTERVAL 20 MINUTE);
