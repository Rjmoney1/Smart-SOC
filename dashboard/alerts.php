<?php
/**
 * Alerts Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireLogin();

$pageTitle  = 'Security Alerts';
$activePage = 'alerts';

// Filters
$severity = $_GET['severity'] ?? '';
$status   = $_GET['status'] ?? '';
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));

// Build query
$where  = [];
$params = [];
if ($severity) { $where[] = "severity = ?"; $params[] = $severity; }
if ($status)   { $where[] = "status = ?";   $params[] = $status; }
if ($search)   { $where[] = "(title LIKE ? OR source_ip LIKE ? OR description LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql        = "SELECT a.*, u.username FROM alerts a LEFT JOIN users u ON a.user_id = u.id $whereStr ORDER BY a.created_at DESC";
$paginated  = paginate($sql, $params, $page);
$alerts     = $paginated['data'];

include __DIR__ . '/includes/header.php';
?>

<div class="flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    <div class="flex-1 lg:ml-64">
        <?php include __DIR__ . '/components/navbar.php'; ?>
        <main class="pt-16 min-h-screen">
            <div class="p-6 space-y-6">

                <!-- Header -->
                <div class="animate-in flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Security Alerts</h2>
                        <p id="alerts-count-display" class="text-slate-500 text-sm mt-0.5">
                            <?= number_format($paginated['total']) ?> total alerts
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="triggerManualRefresh()" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 border border-white/5 transition-colors">
                            <i id="manual-refresh-icon" data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh
                        </button>
                        <button onclick="exportAlerts()" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 border border-white/5 transition-colors">
                            <i data-lucide="download" class="w-4 h-4"></i> Export
                        </button>
                        <button onclick="document.getElementById('create-alert-modal').classList.remove('hidden')"
                                class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white">
                            <i data-lucide="plus" class="w-4 h-4"></i> New Alert
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="animate-in glass-card rounded-2xl p-5 border border-white/5">
                    <form method="GET" action="" class="flex flex-col sm:flex-row gap-3">
                        <!-- Search -->
                        <div class="flex-1 relative">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search alerts, IPs, descriptions..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 placeholder-slate-600 focus:border-brand-500/30 focus:outline-none transition-colors">
                        </div>
                        <!-- Severity Filter -->
                        <select name="severity" class="px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none transition-colors min-w-[140px]">
                            <option value="">All Severities</option>
                            <?php foreach (['critical','high','medium','low','info'] as $s): ?>
                            <option value="<?= $s ?>" <?= $severity === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Status Filter -->
                        <select name="status" class="px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none transition-colors min-w-[130px]">
                            <option value="">All Statuses</option>
                            <?php foreach (['open','investigating','resolved','false_positive'] as $st): ?>
                            <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-medium text-white flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4"></i> Filter
                        </button>
                        <?php if ($search || $severity || $status): ?>
                        <a href="alerts.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/5 transition-colors flex items-center gap-2">
                            <i data-lucide="x" class="w-4 h-4"></i> Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Quick Severity Tabs -->
                <div class="animate-in flex items-center gap-2 flex-wrap">
                    <?php
                    $sevCounts = db()->fetchAll("SELECT severity, COUNT(*) as cnt FROM alerts GROUP BY severity");
                    $sevMap    = array_column($sevCounts, 'cnt', 'severity');
                    $sevList   = [
                        ''         => ['All',      'text-slate-400', 'bg-white/5 border-white/5'],
                        'critical' => ['Critical', 'text-red-400',    'bg-red-500/10 border-red-500/20'],
                        'high'     => ['High',     'text-orange-400', 'bg-orange-500/10 border-orange-500/20'],
                        'medium'   => ['Medium',   'text-yellow-400', 'bg-yellow-500/10 border-yellow-500/20'],
                        'low'      => ['Low',      'text-blue-400',   'bg-blue-500/10 border-blue-500/20'],
                    ];
                    foreach ($sevList as $val => [$label, $tc, $bg]):
                        $isActive = ($severity === $val);
                        $count    = $val ? ($sevMap[$val] ?? 0) : array_sum($sevMap);
                    ?>
                    <a href="?severity=<?= $val ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>"
                       class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold border transition-all
                              <?= $isActive ? $bg . ' ' . $tc . ' border-current' : 'text-slate-500 bg-white/3 border-white/5 hover:text-slate-300 hover:bg-white/8' ?>">
                        <?= $label ?>
                        <span class="text-[10px] font-bold sev-count-badge" data-sev="<?= $val ?>"><?= $count ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Alerts Table -->
                <div class="animate-in glass-card rounded-2xl border border-white/5 overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-white/5">
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Alert</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden md:table-cell">Source IP</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden lg:table-cell">Type</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Severity</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden sm:table-cell">Status</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden lg:table-cell">Time</th>
                                <th class="text-right text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="alerts-table-body" class="divide-y divide-white/5">
                            <?php if (empty($alerts)): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                                    <i data-lucide="shield-check" class="w-10 h-10 mx-auto mb-3 text-slate-700"></i>
                                    <p class="text-sm">No alerts found matching your filters.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                            <tr class="alert-row transition-colors cursor-pointer" onclick="showAlertDetail(<?= $alert['id'] ?>)">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-sm text-slate-200 truncate max-w-xs"><?= htmlspecialchars($alert['title']) ?></div>
                                    <div class="text-xs text-slate-600 mt-0.5 truncate max-w-xs hidden sm:block"><?= htmlspecialchars(substr($alert['description'] ?? '', 0, 60)) ?>...</div>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell">
                                    <span class="font-mono text-xs text-slate-300"><?= htmlspecialchars($alert['source_ip'] ?? '—') ?></span>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell">
                                    <span class="text-xs text-slate-400"><?= htmlspecialchars($alert['attack_type'] ?? 'Unknown') ?></span>
                                </td>
                                <td class="px-5 py-4"><?= severityBadge($alert['severity']) ?></td>
                                <td class="px-5 py-4 hidden sm:table-cell">
                                    <?php
                                    $statusCls = match($alert['status'] ?? 'open') {
                                        'open'          => 'text-red-400 bg-red-500/10 border-red-500/20',
                                        'investigating' => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
                                        'resolved'      => 'text-green-400 bg-green-500/10 border-green-500/20',
                                        'false_positive'=> 'text-slate-400 bg-slate-500/10 border-slate-500/20',
                                        default         => 'text-slate-400 bg-slate-500/10 border-slate-500/20',
                                    };
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= $statusCls ?>">
                                        <?= ucwords(str_replace('_', ' ', $alert['status'] ?? 'open')) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell text-xs text-slate-600"><?= timeAgo($alert['created_at']) ?></td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1" onclick="event.stopPropagation()">
                                        <button onclick="showAlertDetail(<?= $alert['id'] ?>)" class="p-1.5 rounded-lg text-slate-500 hover:text-brand-400 hover:bg-brand-500/10 transition-colors" title="View">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button onclick="analyzeAlert(<?= $alert['id'] ?>)" class="p-1.5 rounded-lg text-slate-500 hover:text-purple-400 hover:bg-purple-500/10 transition-colors" title="AI Analysis">
                                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button onclick="blockIp('<?= htmlspecialchars($alert['source_ip'] ?? '') ?>')" class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Block IP">
                                            <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($paginated['total_pages'] > 1): ?>
                    <div class="flex items-center justify-between px-5 py-4 border-t border-white/5">
                        <span class="text-xs text-slate-500">
                            Showing <?= (($page-1)*ITEMS_PER_PAGE)+1 ?>–<?= min($page*ITEMS_PER_PAGE, $paginated['total']) ?> of <?= $paginated['total'] ?>
                        </span>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&severity=<?= $severity ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>"
                               class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php for ($p = max(1,$page-2); $p <= min($paginated['total_pages'],$page+2); $p++): ?>
                            <a href="?page=<?= $p ?>&severity=<?= $severity ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>"
                               class="w-8 h-8 rounded-lg text-xs font-medium flex items-center justify-center transition-colors
                                      <?= $p === $page ? 'bg-brand-600/30 text-brand-400 border border-brand-500/30' : 'text-slate-500 hover:text-white hover:bg-white/5' ?>">
                                <?= $p ?>
                            </a>
                            <?php endfor; ?>
                            <?php if ($page < $paginated['total_pages']): ?>
                            <a href="?page=<?= $page+1 ?>&severity=<?= $severity ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>"
                               class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- Alert Detail Modal -->
<div id="alert-detail-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay bg-black/60 p-4">
    <div class="glass-card rounded-2xl border border-white/10 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
            <h3 class="text-base font-semibold text-white">Alert Details</h3>
            <button onclick="document.getElementById('alert-detail-modal').classList.add('hidden')" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div id="alert-detail-content" class="p-6">
            <div class="animate-pulse space-y-4">
                <div class="h-4 bg-white/5 rounded w-3/4"></div>
                <div class="h-4 bg-white/5 rounded w-1/2"></div>
                <div class="h-24 bg-white/5 rounded"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showAlertDetail(id) {
    document.getElementById('alert-detail-modal').classList.remove('hidden');
    fetch(`<?= APP_URL ?>/dashboard/api/get_alerts.php?id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (!d.alert) return;
            const a = d.alert;
            const sevColors = { critical:'text-red-400', high:'text-orange-400', medium:'text-yellow-400', low:'text-blue-400', info:'text-slate-400' };
            document.getElementById('alert-detail-content').innerHTML = `
                <div class="space-y-5">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <h4 class="text-base font-semibold text-white">${escHtml(a.title)}</h4>
                            <span class="text-sm font-semibold ${sevColors[a.severity]} flex-shrink-0">${a.severity?.toUpperCase()}</span>
                        </div>
                        <p class="text-sm text-slate-400 leading-relaxed">${escHtml(a.description || 'No description provided.')}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        ${[['Source IP', a.source_ip||'—'], ['Attack Type', a.attack_type||'Unknown'], ['Status', a.status||'open'], ['Risk Score', a.risk_score||'N/A'], ['Detected At', a.created_at], ['Reporter', a.username||'System']].map(([k,v]) => `
                        <div class="bg-surface-600/30 rounded-xl p-3">
                            <div class="text-xs text-slate-500 mb-1">${k}</div>
                            <div class="text-sm font-medium text-slate-200 font-mono">${escHtml(String(v))}</div>
                        </div>`).join('')}
                    </div>
                    ${a.ai_analysis ? `
                    <div class="bg-brand-500/5 border border-brand-500/20 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <i data-lucide="sparkles" class="w-4 h-4 text-brand-400"></i>
                            <span class="text-sm font-semibold text-brand-300">Gemini AI Analysis</span>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed">${escHtml(a.ai_analysis)}</p>
                    </div>` : ''}
                    <div class="flex items-center gap-2">
                        <button onclick="analyzeAlert(${a.id})" class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white">
                            <i data-lucide="sparkles" class="w-4 h-4"></i> Analyze with AI
                        </button>
                        ${a.source_ip ? `<button onclick="blockIp('${escHtml(a.source_ip)}')" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-red-400 bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 transition-colors">
                            <i data-lucide="ban" class="w-4 h-4"></i> Block IP
                        </button>` : ''}
                    </div>
                </div>`;
            lucide.createIcons();
        });
}

function analyzeAlert(id) {
    const btn = event.target.closest('button');
    if (btn) btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>';
    fetch('<?= APP_URL ?>/dashboard/api/ai_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alert_id: id, type: 'alert' })
    }).then(r => r.json()).then(d => {
        showAlertDetail(id);
        lucide.createIcons();
    });
}

function blockIp(ip) {
    if (!ip || !confirm(`Block IP ${ip}? This will prevent all connections from this address.`)) return;
    fetch('<?= APP_URL ?>/dashboard/api/block_ip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ip, reason: 'Blocked from alerts page' })
    }).then(r => r.json()).then(d => {
        alert(d.success ? `IP ${ip} has been blocked.` : (d.message || 'Failed to block IP.'));
    });
}

function exportAlerts() {
    window.location = '<?= APP_URL ?>/dashboard/api/get_alerts.php?export=csv&severity=<?= urlencode($severity) ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>';
}

function escHtml(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }

// --- Auto-Refresh Real-Time Alerts Logic ---
const filterSeverity = '<?= $severity ?>';
const filterStatus   = '<?= $status ?>';
const filterSearch   = '<?= urlencode($search) ?>';
const currentPage    = <?= $page ?>;

function getSeverityBadge(sev) {
    const classes = {
        'critical': 'bg-red-500/20 text-red-400 border-red-500/30',
        'high':     'bg-orange-500/20 text-orange-400 border-orange-500/30',
        'medium':   'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
        'low':      'bg-blue-500/20 text-blue-400 border-blue-500/30',
        'info':     'bg-slate-500/20 text-slate-400 border-slate-500/30',
    };
    const cls = classes[sev] || 'bg-slate-500/20 text-slate-400 border-slate-500/30';
    const label = sev.charAt(0).toUpperCase() + sev.slice(1);
    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${cls}">${label}</span>`;
}

function getStatusBadge(status) {
    const classes = {
        'open':          'text-red-400 bg-red-500/10 border-red-500/20',
        'investigating': 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
        'resolved':      'text-green-400 bg-green-500/10 border-green-500/20',
        'false_positive':'text-slate-400 bg-slate-500/10 border-slate-500/20'
    };
    const cls = classes[status] || 'text-slate-400 bg-slate-500/10 border-slate-500/20';
    const label = status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${cls}">${label}</span>`;
}

function getTimeAgo(dateStr) {
    if (!dateStr) return '—';
    const parts = dateStr.split(/[- :]/);
    const date = new Date(Date.UTC(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]));
    const diff = Math.floor((new Date() - date) / 1000);
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

let isRefreshing = false;

function updateAlertsTable(callback) {
    if (isRefreshing) return;
    isRefreshing = true;

    fetch(`<?= APP_URL ?>/dashboard/api/get_alerts.php?severity=${filterSeverity}&status=${filterStatus}&search=${filterSearch}&page=${currentPage}&limit=20`)
        .then(r => r.json())
        .then(d => {
            isRefreshing = false;
            if (callback) callback();

            if (!d.alerts) return;

            const countDisplay = document.getElementById('alerts-count-display');
            if (countDisplay && d.total !== undefined) {
                countDisplay.innerHTML = `${d.total.toLocaleString()} total alerts`;
            }

            // Update severity tab counts dynamically
            if (d.severity_counts) {
                document.querySelectorAll('.sev-count-badge').forEach(badge => {
                    const sev = badge.getAttribute('data-sev');
                    const key = sev === '' ? 'all' : sev;
                    if (d.severity_counts[key] !== undefined) {
                        badge.textContent = d.severity_counts[key];
                    }
                });
            }

            const tbody = document.getElementById('alerts-table-body');
            if (d.alerts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                            <i data-lucide="shield-check" class="w-10 h-10 mx-auto mb-3 text-slate-700"></i>
                            <p class="text-sm">No alerts found matching your filters.</p>
                        </td>
                    </tr>`;
                lucide.createIcons();
                return;
            }

            tbody.innerHTML = d.alerts.map(a => `
                <tr class="alert-row transition-colors cursor-pointer" onclick="showAlertDetail(${a.id})">
                    <td class="px-5 py-4">
                        <div class="font-medium text-sm text-slate-200 truncate max-w-xs">${escHtml(a.title)}</div>
                        <div class="text-xs text-slate-600 mt-0.5 truncate max-w-xs hidden sm:block">${escHtml(a.description ? a.description.substring(0, 60) : '')}...</div>
                    </td>
                    <td class="px-5 py-4 hidden md:table-cell">
                        <span class="font-mono text-xs text-slate-300">${escHtml(a.source_ip || '—')}</span>
                    </td>
                    <td class="px-5 py-4 hidden lg:table-cell">
                        <span class="text-xs text-slate-400">${escHtml(a.attack_type || 'Unknown')}</span>
                    </td>
                    <td class="px-5 py-4">${getSeverityBadge(a.severity)}</td>
                    <td class="px-5 py-4 hidden sm:table-cell">${getStatusBadge(a.status || 'open')}</td>
                    <td class="px-5 py-4 hidden lg:table-cell text-xs text-slate-600">${getTimeAgo(a.created_at)}</td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-1" onclick="event.stopPropagation()">
                            <button onclick="showAlertDetail(${a.id})" class="p-1.5 rounded-lg text-slate-500 hover:text-brand-400 hover:bg-brand-500/10 transition-colors" title="View">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="analyzeAlert(${a.id})" class="p-1.5 rounded-lg text-slate-500 hover:text-purple-400 hover:bg-purple-500/10 transition-colors" title="AI Analysis">
                                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="blockIp('${escHtml(a.source_ip || '')}')" class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Block IP">
                                <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </td>
                </tr>`).join('');

            lucide.createIcons();
        })
        .catch(err => {
            isRefreshing = false;
            if (callback) callback();
            console.error('Error fetching alerts:', err);
        });
}

function triggerManualRefresh() {
    updateAlertsTable();
}

// Listen to SSE updates dynamically instead of page polling
window.addEventListener('new-security-alert', e => {
    console.log('New alert received in alerts page:', e.detail);
    updateAlertsTable();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
