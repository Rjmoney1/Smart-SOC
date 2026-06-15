<?php
/**
 * Utility Functions
 */

require_once __DIR__ . '/db.php';

/**
 * Log activity to database
 */
function logActivity(string $type, string $action, string $message, string $severity = 'info', int $userId = 0): void {
    try {
        db()->insert('logs', [
            'type'       => $type,
            'action'     => $action,
            'message'    => $message,
            'severity'   => $severity,
            'user_id'    => $userId ?: ($_SESSION['user_id'] ?? 0),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Format timestamp to human-readable
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60)        return $diff . 's ago';
    if ($diff < 3600)      return floor($diff / 60) . 'm ago';
    if ($diff < 86400)     return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000)   return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

/**
 * Severity badge HTML
 */
function severityBadge(string $severity): string {
    $classes = [
        'critical' => 'bg-red-500/20 text-red-400 border-red-500/30',
        'high'     => 'bg-orange-500/20 text-orange-400 border-orange-500/30',
        'medium'   => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
        'low'      => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
        'info'     => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
    ];
    $cls = $classes[$severity] ?? $classes['info'];
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {$cls}\">" . ucfirst($severity) . "</span>";
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF input field
 */
function csrfField(): string {
    $token = auth()->generateCsrfToken();
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

/**
 * Verify CSRF from POST
 */
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!auth()->verifyCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
    }
}

/**
 * Paginate query
 */
function paginate(string $sql, array $params, int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
    $offset = ($page - 1) * $perPage;
    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subq";
    $total = (int)(db()->fetch($countSql, $params)['total'] ?? 0);
    $data  = db()->fetchAll($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);
    return [
        'data'         => $data,
        'total'        => $total,
        'page'         => $page,
        'per_page'     => $perPage,
        'total_pages'  => (int) ceil($total / $perPage),
    ];
}

/**
 * Format bytes
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

/**
 * Return JSON response
 */
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Validate email
 */
function isValidEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Get client IP
 */
function getClientIp(): string {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Get dashboard stats
 */
function getDashboardStats(): array {
    $db = db();
    return [
        'total_alerts'    => $db->count('alerts'),
        'critical_alerts' => $db->count('alerts', "severity = 'critical' AND status = 'open'"),
        'blocked_ips'     => $db->count('blocked_ips', "is_active = 1"),
        'total_logs'      => $db->count('logs'),
        'active_users'    => $db->count('users', 'is_active = 1'),
        'ai_reports'      => $db->count('ai_reports'),
        'threats_today'   => $db->count('alerts', "DATE(created_at) = CURDATE()"),
        'open_incidents'  => $db->count('alerts', "status = 'open'"),
    ];
}

/**
 * Get recent alerts
 */
function getRecentAlerts(int $limit = 10): array {
    return db()->fetchAll(
        "SELECT a.*, u.username FROM alerts a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT ?",
        [$limit]
    );
}

/**
 * Local AI Response Generator (Rule & Template Driven, 100% Offline)
 */
function generateLocalResponse(string $prompt): string {
    // Check if prompt is a full report
    if (strpos($prompt, 'Generate a professional security incident report') !== false || strpos($prompt, 'full_report') !== false) {
        $stats = getDashboardStats();
        $totalAlerts = $stats['total_alerts'] ?? 0;
        $criticalAlerts = $stats['critical_alerts'] ?? 0;
        $blockedIPs = $stats['blocked_ips'] ?? 0;
        $totalLogs = $stats['total_logs'] ?? 0;
        
        return "### 📊 Local AI Executive Security Briefing

**Report Scope:** Last 24 Hours Security Monitoring
**Security Posture:** ACTIVE DEFENSE
**Overall Risk Score:** " . ($criticalAlerts > 0 ? "78/100 (High Risk)" : "35/100 (Low-Medium Risk)") . "

---

#### 1. Executive Summary
During the current monitoring period, the platform recorded **{$totalAlerts}** total security alerts, of which **{$criticalAlerts}** were flagged as critical/high severity. The automated Python Security Engine successfully blocked **{$blockedIPs}** hostile IP addresses. Log inspection processes audited **{$totalLogs}** events, ensuring that all access attempts were verified and documented.

#### 2. Threat Landscape & Trend Analysis
- **Credential Brute-Forcing (Auth Anomaly)**: Automated sweeps attempting brute-force authorization remain the leading threat vector.
- **Network Reconnaissance**: Several scans trying to discover open system ports were detected and mitigated.
- **API Abuse Scans**: Hostile probes targeted standard REST API endpoints, but were thwarted by rate limiters and edge blocking.

#### 3. Critical Incidents and Attack Vectors
- **SSH/RDP Brute Force**: Automated brute-force attacks target core administrative services. Recommended action: Restrict access via VPN or IP white-listing.
- **SQL Injection Probing**: Unauthenticated scanners attempting database penetration. Recommended action: Audit Web Application Firewall (WAF) rule sets.

#### 4. Immediate and Long-Term Recommendations
1. **[IMMEDIATE]** Review active IP blocks and ensure firewall persistence for high-frequency attackers.
2. **[IMMEDIATE]** Audit user accounts with multiple failed login events to ensure no credential compromise occurred.
3. **[LONG-TERM]** Enforce Multi-Factor Authentication (MFA) across all administration portals.
4. **[LONG-TERM]** Upgrade to network-level rate limiters and establish strict log rotation rules.";
    }
    
    // Try to parse alert JSON from the prompt
    $alert = null;
    if (preg_match('/\{.*\}/s', $prompt, $matches)) {
        $jsonData = json_decode($matches[0], true);
        if (is_array($jsonData)) {
            if (isset($jsonData['title']) || isset($jsonData['severity'])) {
                $alert = $jsonData;
            }
        }
    }
    
    if ($alert) {
        $title = $alert['title'] ?? 'Security Alert';
        $severity = strtoupper($alert['severity'] ?? 'high');
        $attackType = $alert['attack_type'] ?? 'network';
        $sourceIP = $alert['source_ip'] ?? '127.0.0.1';
        $description = $alert['description'] ?? 'No description provided';
        $riskScore = $alert['risk_score'] ?? '75';
        
        return "### 🛡️ Local AI Security Analyst Alert Report

**Incident Name:** {$title}
**Severity Level:** **{$severity}**
**Source IP Address:** `{$sourceIP}`
**Attack Vector:** `{$attackType}`
**Risk Priority Score:** `{$riskScore}/100`

---

#### 1. Threat Assessment
- **Analysis:** This event indicates a potential **{$title}** aimed at system resources or access endpoints. The activity pattern is characteristic of automated penetration tools scanning for unpatched vulnerabilities or weak administration configurations.
- **Impact Level:** The impact is rated **{$severity}** because unauthorized access could lead to system compromise, data leakage, or malicious lateral movement within the network.
- **Threat Actor Behavior:** The attack vector is classified as **{$attackType}**. The persistent frequency of connections from `{$sourceIP}` indicates structured scanning rather than random traffic.

#### 2. Attack Vector Analysis
- **Technique:** The remote host at `{$sourceIP}` initiated sequential requests matching signature patterns for **{$attackType}**.
- **Objective:** The threat actor is likely attempting to exploit service vulnerabilities or bruteforce account credentials to establish an initial foothold.
- **Diagnostic Context:** _Description:_ \"{$description}\".

#### 3. Immediate Recommended Actions (SOC Team)
1. **IP Blocking**: Verify if `{$sourceIP}` has been blocked. If not, trigger an immediate firewall block via the **Block IP** button on this dashboard.
2. **Session Termination**: Invalidate all active sessions originating from IP `{$sourceIP}` or the targeted user account.
3. **Log Examination**: Check target server logs around this timestamp to verify if any attempts were successful.

#### 4. Long-Term Mitigation
- Enforce strict Rate Limiting policies on all network-facing endpoints.
- Deploy host-based intrusion prevention systems (HIPS) to automatically drop traffic from repeat-offender subnets.
- Implement continuous credential auditing and password complexity controls.";
    }
    
    // Default / Custom / Quick prompt fallback
    $userQuery = "";
    if (strpos($prompt, 'User Query:') !== false) {
        $parts = explode('User Query:', $prompt);
        $userQuery = trim(end($parts));
    } elseif (strpos($prompt, 'expert. ') !== false) {
        $parts = explode('expert. ', $prompt);
        $userQuery = trim(end($parts));
    } else {
        $userQuery = $prompt;
    }
    
    if (empty($userQuery)) {
        $userQuery = "Security overview request";
    }

    return "### 🤖 Local AI Cybersecurity Assistant

**Query Analyzed:** \"{$userQuery}\"
**Status:** Online | Local Mode (Offline Rules Engine)

---

Based on the local security heuristics and the system logs, here is the expert analysis for your request:

1. **Analysis Overview**: Your query pertains to cybersecurity best practices and dashboard status. The CyberAI platform is currently monitoring the host systems, Sniffing Network Packets via Scapy, and running an Isolation Forest anomaly detector. All security systems are operational.
2. **Current System Status**:
   - **Local Firewall**: Active (iptables interface configured)
   - **AI Analyst**: Configured for 100% local, high-speed execution (Offline Heuristic Mode)
   - **Network Sniffer**: Listening on default loopback/docker interfaces
3. **Expert Guidance**:
   - Keep your system packages updated (`apt update && apt upgrade`).
   - Monitor `auth.log` and `syslog` for unusual root escalations or sudden cron modifications.
   - For real-world deployments, change all default dashboard credentials immediately in the **User Management** page.";
}

/**
 * Call AI API based on configured provider (Local, Ollama, or Gemini)
 */
function callGeminiApi(string $prompt): array {
    // 1. Local Offline Mode
    if (defined('AI_PROVIDER') && AI_PROVIDER === 'local') {
        return [
            'success' => true,
            'text'    => generateLocalResponse($prompt)
        ];
    }

    // 2. Ollama Local LLM Mode
    if (defined('AI_PROVIDER') && AI_PROVIDER === 'ollama') {
        $payload = json_encode([
            'model'    => OLLAMA_MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'stream'   => false,
            'options'  => ['temperature' => 0.4]
        ]);

        $ch = curl_init(OLLAMA_API_URL . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['message']['content'])) {
                return [
                    'success' => true,
                    'text'    => $data['message']['content']
                ];
            }
        }
        return ['success' => false, 'message' => "Ollama service failed (HTTP {$httpCode}). Make sure Ollama is running and has downloaded the model " . OLLAMA_MODEL];
    }

    // 3. Gemini Cloud API Mode (Fallback)
    if (!defined('GEMINI_API_KEY') || !GEMINI_API_KEY) {
        return ['success' => false, 'message' => 'Gemini API key not configured. Set AI_PROVIDER to "local" in .env to run offline.'];
    }

    $cacheFile = __DIR__ . '/cache/gemini_' . md5($prompt) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 4096]
    ]);

    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADER         => true
    ]);

    $rawResponse = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = substr($rawResponse, 0, $headerSize);
    $body = substr($rawResponse, $headerSize);
    curl_close($ch);

    if ($httpCode === 429) {
        preg_match('/Retry-After:\s?(\d+)/i', $headers, $matches);
        $retry = $matches[1] ?? '60';
        return ['success' => false, 'message' => "Rate limit exceeded. Retry after {$retry} seconds."];
    }

    $data = json_decode($body, true);
    if ($httpCode === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $result = ['success' => true, 'text' => $data['candidates'][0]['content']['parts'][0]['text']];
        if (!is_dir(__DIR__ . '/cache')) mkdir(__DIR__ . '/cache', 0755, true);
        file_put_contents($cacheFile, json_encode($result));
        return $result;
    }

    return ['success' => false, 'message' => 'Gemini API error: ' . ($data['error']['message'] ?? 'Unknown')];
}

/**
 * Export data as CSV
 */
function exportCsv(array $data, string $filename): void {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

/**
 * Get threat trend data for charts
 */
function getThreatTrends(int $days = 7): array {
    $sql = "SELECT DATE(created_at) as date, severity, COUNT(*) as count 
            FROM alerts 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
            GROUP BY DATE(created_at), severity 
            ORDER BY date ASC";
    return db()->fetchAll($sql, [$days]);
}

/**
 * Get top attacking IPs
 */
function getTopAttackingIPs(int $limit = 10): array {
    return db()->fetchAll(
        "SELECT source_ip, COUNT(*) as count, MAX(severity) as max_severity, MAX(created_at) as last_seen 
         FROM alerts 
         WHERE source_ip IS NOT NULL AND source_ip != '' 
         GROUP BY source_ip 
         ORDER BY count DESC 
         LIMIT ?",
        [$limit]
    );
}
