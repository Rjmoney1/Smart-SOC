<?php
/**
 * API: Get Alerts
 * Supports: count, list, single, feed, CSV export
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth()->requireLogin();
header('Content-Type: application/json');

// Count only
if (isset($_GET['count'])) {
    $open = db()->count('alerts', "status = 'open'");
    jsonResponse(['open_count' => $open]);
}

// Single alert
if (isset($_GET['id']) && !isset($_GET['export'])) {
    $id    = (int)$_GET['id'];
    $alert = db()->fetch("SELECT a.*, u.username FROM alerts a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?", [$id]);
    jsonResponse(['alert' => $alert]);
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where  = [];
    $params = [];
    if (!empty($_GET['severity'])) { $where[] = "severity=?"; $params[] = $_GET['severity']; }
    if (!empty($_GET['status']))   { $where[] = "status=?";   $params[] = $_GET['status']; }
    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where[] = "(title LIKE ? OR source_ip LIKE ?)";
        $params  = array_merge($params, [$s, $s]);
    }
    $whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $alerts   = db()->fetchAll("SELECT id,title,severity,status,source_ip,attack_type,risk_score,created_at FROM alerts $whereStr ORDER BY created_at DESC", $params);
    exportCsv($alerts, 'alerts_export_' . date('Ymd_His') . '.csv');
}

// Feed format (for live attack feed)
if (isset($_GET['format']) && $_GET['format'] === 'feed') {
    $limit  = min(20, (int)($_GET['limit'] ?? 10));
    $alerts = db()->fetchAll("SELECT id,title,severity,source_ip,attack_type,created_at FROM alerts ORDER BY created_at DESC LIMIT ?", [$limit]);
    jsonResponse(['alerts' => $alerts]);
}

// List alerts
$limit    = min(100, (int)($_GET['limit'] ?? 20));
$status   = $_GET['status'] ?? '';
$severity = $_GET['severity'] ?? '';
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $limit;

$where    = [];
$params   = [];
if ($status)   { $where[] = "status=?";   $params[] = $status; }
if ($severity) { $where[] = "severity=?"; $params[] = $severity; }
if ($search)   {
    $where[] = "(title LIKE ? OR source_ip LIKE ? OR description LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$alerts = db()->fetchAll(
    "SELECT a.*, u.username FROM alerts a LEFT JOIN users u ON a.user_id = u.id $whereStr ORDER BY a.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

// Get total count for pagination display
$total = db()->count('alerts', $whereStr ?: '1=1', $params);

// Get severity counts
$allSevCounts = db()->fetchAll("SELECT severity, COUNT(*) as cnt FROM alerts GROUP BY severity");
$allSevMap    = array_column($allSevCounts, 'cnt', 'severity');
$totalAlerts  = array_sum($allSevMap);

jsonResponse([
    'alerts' => $alerts, 
    'count' => count($alerts), 
    'total' => $total, 
    'page' => $page, 
    'total_pages' => (int)ceil($total / $limit),
    'severity_counts' => [
        'all'      => $totalAlerts,
        'critical' => (int)($allSevMap['critical'] ?? 0),
        'high'     => (int)($allSevMap['high'] ?? 0),
        'medium'   => (int)($allSevMap['medium'] ?? 0),
        'low'      => (int)($allSevMap['low'] ?? 0),
        'info'     => (int)($allSevMap['info'] ?? 0)
    ]
]);

