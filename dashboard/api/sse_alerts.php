<?php
/**
 * API: Server-Sent Events (SSE) Alerts Stream
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Disable default php error display and output buffering to ensure SSE works
ini_set('display_errors', 0);
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
ob_implicit_flush(1);

// Ensure user is logged in
auth()->requireLogin();

// Close the write-lock on session file so other pages can load concurrently
session_write_close();

// Set SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for Nginx/Reverse Proxies

// Get last seen ID from parameter or header
$lastSeenId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
if (isset($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastSeenId = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
}

// If no last seen ID was provided, default to current max ID so we only stream *new* alerts
if ($lastSeenId <= 0) {
    $row = db()->fetch("SELECT MAX(id) as max_id FROM alerts");
    $lastSeenId = (int)($row['max_id'] ?? 0);
}

// Keep connection open, check for new alerts every 1.5 seconds
$heartbeatCount = 0;

while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }

    // Query for new alerts
    $newAlerts = db()->fetchAll(
        "SELECT a.*, u.username FROM alerts a LEFT JOIN users u ON a.user_id = u.id WHERE a.id > ? ORDER BY a.id ASC",
        [$lastSeenId]
    );

    if (!empty($newAlerts)) {
        foreach ($newAlerts as $alert) {
            $lastSeenId = (int)$alert['id'];
            echo "id: " . $lastSeenId . "\n";
            echo "event: new_alert\n";
            echo "data: " . json_encode($alert) . "\n\n";
        }
        ob_flush();
        flush();
        $heartbeatCount = 0;
    } else {
        // Send heartbeat comment every 15 seconds to prevent gateway/proxy timeout
        $heartbeatCount++;
        if ($heartbeatCount >= 10) {
            echo ": heartbeat\n\n";
            ob_flush();
            flush();
            $heartbeatCount = 0;
        }
    }

    // Sleep for 1.5 seconds
    usleep(1500000);
}
