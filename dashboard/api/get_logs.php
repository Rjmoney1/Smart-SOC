<?php
/**
 * API: Get Logs
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth()->requireLogin();
header('Content-Type: application/json');

$limit  = min(500, (int)($_GET['limit'] ?? 50));
$type   = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$where  = [];
$params = [];
if ($type)   { $where[] = "type=?";         $params[] = $type; }
if ($search) { $where[] = "message LIKE ?"; $params[] = "%$search%"; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

if (isset($_GET['export'])) {
    $logs = db()->fetchAll("SELECT id,type,action,message,severity,ip_address,created_at FROM logs $whereStr ORDER BY created_at DESC LIMIT 10000", $params);
    exportCsv($logs, 'logs_export_'.date('Ymd_His').'.csv');
}

$logs = db()->fetchAll(
    "SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereStr ORDER BY l.created_at DESC LIMIT ?",
    array_merge($params, [$limit])
);

jsonResponse(['logs' => $logs, 'count' => count($logs)]);
