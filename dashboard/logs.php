<?php
/**
 * Logs Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireLogin();

$pageTitle  = 'System Logs';
$activePage = 'logs';

$type   = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($type)   { $where[] = "type = ?";           $params[] = $type; }
if ($search) { $where[] = "(message LIKE ? OR action LIKE ? OR ip_address LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql       = "SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereStr ORDER BY l.created_at DESC";
$paginated = paginate($sql, $params, $page);
$logs      = $paginated['data'];

$logTypes = db()->fetchAll("SELECT DISTINCT type FROM logs ORDER BY type");

include __DIR__ . '/includes/header.php';
?>

<div class="flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    <div class="flex-1 lg:ml-64">
        <?php include __DIR__ . '/components/navbar.php'; ?>
        <main class="pt-16 min-h-screen">
            <div class="p-6 space-y-6">

                <div class="animate-in flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">System Logs</h2>
                        <p id="logs-count-display" class="text-slate-500 text-sm mt-0.5"><?= number_format($paginated['total']) ?> total log entries</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="triggerLogsManualRefresh()" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 border border-white/5 transition-colors">
                            <i id="logs-refresh-icon" data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh
                        </button>
                        <button onclick="window.location='api/get_logs.php?export=csv&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>'"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 border border-white/5 transition-colors">
                            <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                        </button>
                        <button onclick="toggleLogsAutoRefresh()" id="auto-refresh-btn"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 border border-white/5 transition-colors">
                            <i id="auto-refresh-icon" data-lucide="refresh-cw" class="w-4 h-4"></i> Auto Refresh
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="animate-in glass-card rounded-2xl p-5 border border-white/5">
                    <form method="GET" class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1 relative">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search log messages, actions, IPs..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 placeholder-slate-600 focus:border-brand-500/30 focus:outline-none transition-colors">
                        </div>
                        <select name="type" class="px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none min-w-[140px]">
                            <option value="">All Types</option>
                            <?php foreach ($logTypes as $lt): ?>
                            <option value="<?= htmlspecialchars($lt['type']) ?>" <?= $type === $lt['type'] ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_',' ',$lt['type'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-medium text-white flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4"></i> Filter
                        </button>
                        <?php if ($search || $type): ?>
                        <a href="logs.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white bg-white/5 border border-white/5 transition-colors flex items-center gap-2">
                            <i data-lucide="x" class="w-4 h-4"></i> Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Logs Table -->
                <div class="animate-in glass-card rounded-2xl border border-white/5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px]">
                            <thead>
                                <tr class="border-b border-white/5">
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide w-40">Time</th>
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide w-24">Type</th>
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide w-32">Action</th>
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Message</th>
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide w-24">Severity</th>
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide w-32">IP</th>
                                    <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide w-28">User</th>
                                </tr>
                            </thead>
                            <tbody id="logs-table-body" class="divide-y divide-white/5 font-mono">
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                                        <i data-lucide="scroll-text" class="w-10 h-10 mx-auto mb-3 text-slate-700"></i>
                                        <p class="text-sm font-sans">No logs found matching your filters.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="alert-row transition-colors">
                                    <td class="px-5 py-3 text-xs text-slate-600">
                                        <?= date('M j H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="text-xs px-2 py-0.5 rounded-md font-medium font-sans
                                            <?php echo match($log['type']) {
                                                'auth'    => 'bg-blue-500/10 text-blue-400',
                                                'attack'  => 'bg-red-500/10 text-red-400',
                                                'system'  => 'bg-slate-500/10 text-slate-400',
                                                'api'     => 'bg-purple-500/10 text-purple-400',
                                                default   => 'bg-slate-500/10 text-slate-400',
                                            }; ?>">
                                            <?= htmlspecialchars($log['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars($log['action']) ?></td>
                                    <td class="px-5 py-3 text-xs text-slate-300 max-w-sm truncate">
                                        <span title="<?= htmlspecialchars($log['message']) ?>"><?= htmlspecialchars($log['message']) ?></span>
                                    </td>
                                    <td class="px-5 py-3"><?= severityBadge($log['severity'] ?? 'info') ?></td>
                                    <td class="px-5 py-3 text-xs text-slate-500"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                                    <td class="px-5 py-3 text-xs text-slate-500"><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="logs-pagination-container">
                    <?php if ($paginated['total_pages'] > 1): ?>
                    <div class="flex items-center justify-between px-5 py-4 border-t border-white/5">
                        <span class="text-xs text-slate-500 font-sans">
                            Showing <?= (($page-1)*ITEMS_PER_PAGE)+1 ?>–<?= min($page*ITEMS_PER_PAGE, $paginated['total']) ?> of <?= number_format($paginated['total']) ?>
                        </span>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&type=<?= $type ?>&search=<?= urlencode($search) ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php for ($p = max(1,$page-2); $p <= min($paginated['total_pages'],$page+2); $p++): ?>
                            <a href="?page=<?= $p ?>&type=<?= $type ?>&search=<?= urlencode($search) ?>"
                               class="w-8 h-8 rounded-lg text-xs font-medium flex items-center justify-center transition-colors font-sans
                                      <?= $p === $page ? 'bg-brand-600/30 text-brand-400 border border-brand-500/30' : 'text-slate-500 hover:text-white hover:bg-white/5' ?>">
                                <?= $p ?>
                            </a>
                            <?php endfor; ?>
                            <?php if ($page < $paginated['total_pages']): ?>
                            <a href="?page=<?= $page+1 ?>&type=<?= $type ?>&search=<?= urlencode($search) ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
const filterType   = '<?= $type ?>';
const filterSearch = '<?= urlencode($search) ?>';
const currentPage  = <?= $page ?>;
const itemsPerPage = <?= ITEMS_PER_PAGE ?>;

let isRefreshing = false;

function updateLogsTable(callback) {
    if (isRefreshing) return;
    isRefreshing = true;

    fetch(`<?= APP_URL ?>/dashboard/api/get_logs.php?type=${filterType}&search=${filterSearch}&page=${currentPage}&limit=${itemsPerPage}`)
        .then(r => r.json())
        .then(d => {
            isRefreshing = false;
            if (callback) callback();
            if (!d.logs) return;

            // Update log count display
            const countDisplay = document.getElementById('logs-count-display');
            if (countDisplay && d.total !== undefined) {
                countDisplay.textContent = `${d.total.toLocaleString()} total log entries`;
            }

            // Update table body
            const tbody = document.getElementById('logs-table-body');
            if (!tbody) return;

            if (d.logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                            <i data-lucide="scroll-text" class="w-10 h-10 mx-auto mb-3 text-slate-700"></i>
                            <p class="text-sm font-sans">No logs found matching your filters.</p>
                        </td>
                    </tr>`;
                lucide.createIcons();
                return;
            }

            const typeColors = {
                auth:   'bg-blue-500/10 text-blue-400',
                attack: 'bg-red-500/10 text-red-400',
                system: 'bg-slate-500/10 text-slate-400',
                api:    'bg-purple-500/10 text-purple-400'
            };

            const sevBadgeClasses = {
                critical: 'bg-red-500/20 text-red-400 border-red-500/30',
                high:     'bg-orange-500/20 text-orange-400 border-orange-500/30',
                medium:   'bg-yellow-500/20 text-yellow-400 border-yellow-500/20',
                low:      'bg-blue-500/20 text-blue-400 border-blue-500/20',
                info:     'bg-slate-500/20 text-slate-400 border-slate-500/30'
            };

            tbody.innerHTML = d.logs.map(log => {
                const typeCls = typeColors[log.type] || 'bg-slate-500/10 text-slate-400';
                const typeLabel = log.type.charAt(0).toUpperCase() + log.type.slice(1);
                
                const sev = log.severity || 'info';
                const badgeCls = sevBadgeClasses[sev] || 'bg-slate-500/20 text-slate-400 border-slate-500/30';
                const sevLabel = sev.charAt(0).toUpperCase() + sev.slice(1);

                // Format timestamp (e.g. "Jun 15 23:19:00")
                let formattedTime = '—';
                if (log.created_at) {
                    const date = new Date(log.created_at.replace(/-/g, '/')); // browser compatible parsing
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const pad = n => String(n).padStart(2, '0');
                    formattedTime = `${months[date.getMonth()]} ${date.getDate()} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
                }

                return `
                <tr class="alert-row transition-colors">
                    <td class="px-5 py-3 text-xs text-slate-600">${formattedTime}</td>
                    <td class="px-5 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-md font-medium font-sans ${typeCls}">
                            ${escHtml(typeLabel)}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-400">${escHtml(log.action)}</td>
                    <td class="px-5 py-3 text-xs text-slate-300 max-w-sm truncate">
                        <span title="${escHtml(log.message)}">${escHtml(log.message)}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${badgeCls}">${sevLabel}</span>
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-500">${escHtml(log.ip_address || '—')}</td>
                    <td class="px-5 py-3 text-xs text-slate-500">${escHtml(log.username || 'System')}</td>
                </tr>`;
            }).join('');

            // Update Pagination
            const paginationContainer = document.getElementById('logs-pagination-container');
            if (paginationContainer) {
                if (d.total_pages <= 1) {
                    paginationContainer.innerHTML = '';
                } else {
                    const startItem = (currentPage - 1) * itemsPerPage + 1;
                    const endItem = Math.min(currentPage * itemsPerPage, d.total);
                    
                    let linksHtml = '';
                    
                    // Left chevron
                    if (currentPage > 1) {
                        linksHtml += `
                        <a href="?page=${currentPage - 1}&type=${filterType}&search=${filterSearch}" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>`;
                    }
                    
                    // Page numbers (max 5)
                    const minPage = Math.max(1, currentPage - 2);
                    const maxPage = Math.min(d.total_pages, currentPage + 2);
                    for (let p = minPage; p <= maxPage; p++) {
                        const activeCls = p === currentPage 
                            ? 'bg-brand-600/30 text-brand-400 border border-brand-500/30' 
                            : 'text-slate-500 hover:text-white hover:bg-white/5';
                        linksHtml += `
                        <a href="?page=${p}&type=${filterType}&search=${filterSearch}"
                           class="w-8 h-8 rounded-lg text-xs font-medium flex items-center justify-center transition-colors font-sans ${activeCls}">
                            ${p}
                        </a>`;
                    }
                    
                    // Right chevron
                    if (currentPage < d.total_pages) {
                        linksHtml += `
                        <a href="?page=${currentPage + 1}&type=${filterType}&search=${filterSearch}" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>`;
                    }

                    paginationContainer.innerHTML = `
                    <div class="flex items-center justify-between px-5 py-4 border-t border-white/5">
                        <span class="text-xs text-slate-500 font-sans">
                            Showing ${startItem}–${endItem} of ${d.total.toLocaleString()}
                        </span>
                        <div class="flex items-center gap-1">
                            ${linksHtml}
                        </div>
                    </div>`;
                }
            }

            lucide.createIcons();
        })
        .catch(err => {
            isRefreshing = false;
            if (callback) callback();
            console.error('Error fetching logs:', err);
        });
}

function triggerLogsManualRefresh() {
    updateLogsTable();
}

let autoRefreshInterval = null;

function toggleLogsAutoRefresh() {
    const btn = document.getElementById('auto-refresh-btn');
    
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        btn.innerHTML = '<i id="auto-refresh-icon" data-lucide="refresh-cw" class="w-4 h-4"></i> Auto Refresh';
        btn.classList.remove('text-green-400', 'border-green-500/30');
        lucide.createIcons();
    } else {
        triggerLogsManualRefresh();
        autoRefreshInterval = setInterval(() => {
            updateLogsTable();
        }, 10000);
        
        btn.innerHTML = '<i id="auto-refresh-icon" data-lucide="refresh-cw" class="w-4 h-4"></i> Auto Refresh';
        btn.classList.add('text-green-400', 'border-green-500/30');
        lucide.createIcons();
    }
}

function escHtml(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
