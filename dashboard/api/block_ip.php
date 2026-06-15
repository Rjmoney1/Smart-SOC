<?php
/**
 * API: Block IP
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth()->requireRole(ROLE_ADMIN, ROLE_ANALYST);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $ip     = trim($body['ip'] ?? '');
    $reason = trim($body['reason'] ?? 'Manually blocked');
    $user   = auth()->getCurrentUser();

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        jsonResponse(['success'=>false,'message'=>'Invalid IP address.']);
    }

    // Check if already blocked
    $existing = db()->fetch("SELECT id, is_active FROM blocked_ips WHERE ip_address=?", [$ip]);
    if ($existing) {
        if ($existing['is_active']) {
            jsonResponse(['success'=>false,'message'=>"IP {$ip} is already blocked."]);
        }
        db()->update('blocked_ips', ['is_active'=>1,'blocked_at'=>date('Y-m-d H:i:s'),'reason'=>$reason,'blocked_by'=>$user['id']], 'ip_address=?', [$ip]);
    } else {
        db()->insert('blocked_ips', [
            'ip_address' => $ip,
            'reason'     => $reason,
            'is_active'  => 1,
            'blocked_by' => $user['id'],
            'blocked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    logActivity('system', 'block_ip', "IP {$ip} blocked. Reason: {$reason}", 'high');
    jsonResponse(['success'=>true,'message'=>"IP {$ip} has been blocked."]);
}

if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $ip   = trim($body['ip'] ?? '');
    if (!$ip) { jsonResponse(['success'=>false,'message'=>'IP required.']); }
    db()->update('blocked_ips', ['is_active'=>0], 'ip_address=?', [$ip]);
    logActivity('system', 'unblock_ip', "IP {$ip} unblocked", 'info');
    jsonResponse(['success'=>true,'message'=>"IP {$ip} unblocked."]);
}

if ($method === 'GET') {
    $blocked = db()->fetchAll("SELECT b.*, u.username FROM blocked_ips b LEFT JOIN users u ON b.blocked_by = u.id WHERE b.is_active=1 ORDER BY b.blocked_at DESC");
    jsonResponse(['blocked_ips' => $blocked, 'count' => count($blocked)]);
}
