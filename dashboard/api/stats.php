<?php
/**
 * API: Statistics for charts
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth()->requireLogin();
header('Content-Type: application/json');

$type = $_GET['type'] ?? 'overview';

switch ($type) {
    case 'trends':
        $days = min(30, (int)($_GET['days'] ?? 7));
        $raw  = getThreatTrends($days);
        
        // Build date range
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = date('D', strtotime("-{$i} days"));
        }
        $dateKeys = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateKeys[] = date('Y-m-d', strtotime("-{$i} days"));
        }
        
        $severities = ['critical','high','medium','low'];
        $series = [];
        foreach ($severities as $sev) {
            $data = array_fill(0, $days, 0);
            foreach ($raw as $row) {
                $idx = array_search($row['date'], $dateKeys);
                if ($idx !== false && $row['severity'] === $sev) {
                    $data[$idx] = (int)$row['count'];
                }
            }
            $series[] = ['name' => ucfirst($sev), 'data' => $data];
        }
        
        // Severity breakdown
        $sevCounts = db()->fetchAll("SELECT severity, COUNT(*) as cnt FROM alerts GROUP BY severity");
        $sevMap    = array_column($sevCounts, 'cnt', 'severity');
        $severity  = [
            'values' => [
                (int)($sevMap['critical'] ?? 0),
                (int)($sevMap['high'] ?? 0),
                (int)($sevMap['medium'] ?? 0),
                (int)($sevMap['low'] ?? 0),
                (int)($sevMap['info'] ?? 0),
            ]
        ];
        
        jsonResponse(['series' => $series, 'categories' => $dates, 'severity' => $severity]);
        break;
    
    case 'top_ips':
        $limit = min(20, (int)($_GET['limit'] ?? 10));
        jsonResponse(['top_ips' => getTopAttackingIPs($limit)]);
        break;
    
    case 'overview':
    default:
        $stats = getDashboardStats();
        // Attack type distribution
        $attackTypes = db()->fetchAll("SELECT attack_type, COUNT(*) as cnt FROM alerts WHERE attack_type IS NOT NULL AND attack_type != '' GROUP BY attack_type ORDER BY cnt DESC LIMIT 8");
        // Hourly distribution for today
        $hourly = db()->fetchAll("SELECT HOUR(created_at) as hour, COUNT(*) as cnt FROM alerts WHERE DATE(created_at) = CURDATE() GROUP BY HOUR(created_at) ORDER BY hour ASC");
        
        jsonResponse([
            'stats'        => $stats,
            'attack_types' => $attackTypes,
            'hourly'       => $hourly,
        ]);
        break;
}
