<?php
/**
 * API: Gemini AI Analysis
 * POST: prompt, type, alert_id, title, content
 * GET: id (for fetching a report)
 * DELETE: id (delete report)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth()->requireLogin();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET single report
if ($method === 'GET' && isset($_GET['id'])) {
    $report = db()->fetch("SELECT * FROM ai_reports WHERE id=?", [(int)$_GET['id']]);
    jsonResponse(['report' => $report]);
}

// DELETE report
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? 0);
    auth()->requireRole(ROLE_ADMIN, ROLE_ANALYST);
    db()->delete('ai_reports', 'id=?', [$id]);
    jsonResponse(['success' => true]);
}

// POST
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $body['type'] ?? 'quick';
    $user = auth()->getCurrentUser();

    // Save existing report
    if ($type === 'save') {
        $id = db()->insert('ai_reports', [
            'title'       => sanitize($body['title'] ?? 'AI Report'),
            'content'     => $body['content'] ?? '',
            'report_type' => 'custom',
            'user_id'     => $user['id'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
    }

    // Build prompt based on type
    $prompt = '';
    switch ($type) {
        case 'alert':
            $alertId = (int)($body['alert_id'] ?? 0);
            $alert   = db()->fetch("SELECT * FROM alerts WHERE id=?", [$alertId]);
            if (!$alert) { jsonResponse(['success'=>false,'message'=>'Alert not found.']); }
            $prompt = "You are a senior cybersecurity analyst. Analyze this security alert and provide:\n1. Threat assessment\n2. Attack classification\n3. Potential impact\n4. Immediate mitigation steps\n\nAlert Data:\n" . json_encode($alert, JSON_PRETTY_PRINT);
            break;
        
        case 'full_report':
            $stats   = getDashboardStats();
            $alerts  = db()->fetchAll("SELECT severity, attack_type, source_ip, created_at FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 50");
            $topIPs  = getTopAttackingIPs(5);
            $prompt  = "You are a senior SOC analyst. Generate a professional security incident report for the last 24 hours.\n\n";
            $prompt .= "STATISTICS:\n" . json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";
            $prompt .= "RECENT ALERTS:\n" . json_encode($alerts, JSON_PRETTY_PRINT) . "\n\n";
            $prompt .= "TOP ATTACKING IPs:\n" . json_encode($topIPs, JSON_PRETTY_PRINT) . "\n\n";
            $prompt .= "Provide: Executive Summary, Threat Analysis, Risk Assessment, Incident Timeline, Mitigation Recommendations, and Next Steps.";
            break;
        
        case 'custom':
            // Add security context
            $stats  = getDashboardStats();
            $recent = db()->fetchAll("SELECT title,severity,source_ip,attack_type,created_at FROM alerts ORDER BY created_at DESC LIMIT 10");
            $prompt = "You are a senior cybersecurity analyst with expertise in threat detection, incident response, and security operations.\n\n";
            $prompt .= "Platform Context:\n- Total Alerts: {$stats['total_alerts']}\n- Critical: {$stats['critical_alerts']}\n- Blocked IPs: {$stats['blocked_ips']}\n\n";
            $prompt .= "Recent Alerts:\n" . json_encode($recent, JSON_PRETTY_PRINT) . "\n\n";
            $prompt .= "User Query: " . ($body['prompt'] ?? '');
            break;
        
        default: // quick
            $recent = db()->fetchAll("SELECT title,severity,source_ip,attack_type FROM alerts ORDER BY created_at DESC LIMIT 5");
            $prompt = "You are a cybersecurity expert. " . ($body['prompt'] ?? '') . "\n\nRecent security context: " . json_encode($recent);
            break;
    }

    $result = callGeminiApi($prompt);
    
    if (!$result['success']) {
        jsonResponse(['success'=>false,'message'=>$result['message'],'analysis'=>null], 500);
    }

    // Auto-save for full reports
    if ($type === 'full_report') {
        db()->insert('ai_reports', [
            'title'       => 'Security Report — ' . date('M j, Y H:i'),
            'content'     => $result['text'],
            'report_type' => 'automatic',
            'user_id'     => $user['id'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    // Store AI analysis for alert
    if ($type === 'alert' && isset($alertId)) {
        db()->update('alerts', ['ai_analysis'=>$result['text'],'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$alertId]);
    }

    logActivity('api', 'ai_analysis', "AI analysis performed (type: $type)", 'info');

    jsonResponse([
        'success'  => true,
        'analysis' => $result['text'],
    ]);
}

jsonResponse(['success'=>false,'message'=>'Method not allowed.'], 405);
