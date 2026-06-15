<?php
/**
 * Main Dashboard Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$stats      = getDashboardStats();
$recentAlerts = getRecentAlerts(8);
$topIPs     = getTopAttackingIPs(5);
$currentUser = auth()->getCurrentUser();

include __DIR__ . '/includes/header.php';
?>

<div class="flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 lg:ml-64">
        <?php include __DIR__ . '/components/navbar.php'; ?>

        <main class="pt-16 min-h-screen">
            <div class="p-6 space-y-6">

                <!-- Welcome Banner -->
                <div class="animate-in flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-white">
                            Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>,
                            <span style="background: linear-gradient(135deg, #5c7cfa, #748ffc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?>
                            </span>
                        </h2>
                        <p class="text-slate-500 text-sm mt-1">Here's what's happening in your security environment.</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-2">
                        <span class="text-xs text-slate-500 font-mono"><?= date('D, M j Y · H:i') ?> UTC</span>
                        <button onclick="triggerOverviewRefresh()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium text-slate-300 bg-white/5 hover:bg-white/10 border border-white/5 transition-colors">
                            <i id="overview-refresh-icon" data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
                        </button>
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-500/10 border border-green-500/20">
                            <span class="live-dot w-1.5 h-1.5 rounded-full bg-green-400"></span>
                            <span class="text-xs text-green-400 font-medium">Live</span>
                        </div>
                    </div>
                </div>

                <!-- Stat Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-in">
                    <?php
                    $cards = [
                        ['label'=>'Total Alerts',     'value'=>$stats['total_alerts'],    'icon'=>'bell-ring',     'color'=>'blue',   'change'=>'+12%', 'key'=>'total_alerts'],
                        ['label'=>'Critical Threats', 'value'=>$stats['critical_alerts'], 'icon'=>'shield-x',      'color'=>'red',    'change'=>'-3%', 'key'=>'critical_alerts'],
                        ['label'=>'Blocked IPs',      'value'=>$stats['blocked_ips'],     'icon'=>'ban',           'color'=>'orange', 'change'=>'+5',  'key'=>'blocked_ips'],
                        ['label'=>'AI Reports',       'value'=>$stats['ai_reports'],      'icon'=>'brain-circuit',  'color'=>'purple', 'change'=>'+8',  'key'=>'ai_reports'],
                    ];
                    $colors = [
                        'blue'   => ['bg'=>'bg-blue-500/10',   'icon'=>'text-blue-400',   'border'=>'border-blue-500/20'],
                        'red'    => ['bg'=>'bg-red-500/10',    'icon'=>'text-red-400',    'border'=>'border-red-500/20'],
                        'orange' => ['bg'=>'bg-orange-500/10', 'icon'=>'text-orange-400', 'border'=>'border-orange-500/20'],
                        'purple' => ['bg'=>'bg-purple-500/10', 'icon'=>'text-purple-400', 'border'=>'border-purple-500/20'],
                    ];
                    foreach ($cards as $card):
                        $c = $colors[$card['color']];
                    ?>
                    <div class="stat-card glass-card rounded-2xl p-5 border border-white/5">
                        <div class="flex items-start justify-between mb-4">
                            <div class="<?= $c['bg'] ?> <?= $c['border'] ?> border rounded-xl p-2.5">
                                <i data-lucide="<?= $card['icon'] ?>" class="w-5 h-5 <?= $c['icon'] ?>"></i>
                            </div>
                            <span class="text-xs font-medium <?= str_starts_with($card['change'], '+') ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $card['change'] ?>
                            </span>
                        </div>
                        <div class="text-3xl font-bold text-white mb-1" data-counter="<?= $card['value'] ?>" data-stat-key="<?= $card['key'] ?>">
                            <?= number_format($card['value']) ?>
                        </div>
                        <div class="text-sm text-slate-500"><?= $card['label'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 animate-in">
                    <!-- Threat Trend Chart -->
                    <div class="lg:col-span-2 glass-card rounded-2xl p-5 border border-white/5">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="text-sm font-semibold text-white">Threat Activity</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Last 7 days</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="text-xs px-3 py-1.5 rounded-lg bg-brand-600/20 text-brand-400 border border-brand-500/20 font-medium">7D</button>
                                <button class="text-xs px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-300 hover:bg-white/5 transition-colors">30D</button>
                            </div>
                        </div>
                        <div id="threat-trend-chart" style="height:220px"></div>
                    </div>

                    <!-- Severity Donut -->
                    <div class="glass-card rounded-2xl p-5 border border-white/5">
                        <div class="mb-5">
                            <h3 class="text-sm font-semibold text-white">Alert Severity</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Distribution</p>
                        </div>
                        <div id="severity-chart" style="height:180px"></div>
                        <div class="mt-4 space-y-2" id="severity-legend"></div>
                    </div>
                </div>

                <!-- Bottom Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 animate-in">
                    <!-- Recent Alerts -->
                    <div class="lg:col-span-2 glass-card rounded-2xl border border-white/5 overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
                            <h3 class="text-sm font-semibold text-white">Recent Alerts</h3>
                            <a href="alerts.php" class="text-xs text-brand-400 hover:text-brand-300 transition-colors flex items-center gap-1">
                                View all <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </a>
                        </div>
                        <div id="recent-alerts-list" class="divide-y divide-white/5">
                            <?php if (empty($recentAlerts)): ?>
                            <div class="px-5 py-10 text-center text-slate-600 text-sm">
                                <i data-lucide="shield-check" class="w-8 h-8 mx-auto mb-2 text-slate-700"></i>
                                No alerts found. System is secure.
                            </div>
                            <?php else: ?>
                            <?php foreach ($recentAlerts as $alert): ?>
                            <div class="alert-row flex items-center gap-4 px-5 py-3.5 cursor-pointer transition-colors"
                                 onclick="window.location='alerts.php?id=<?= $alert['id'] ?>'">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 <?php
                                    echo match($alert['severity']) {
                                        'critical' => 'bg-red-500',
                                        'high'     => 'bg-orange-400',
                                        'medium'   => 'bg-yellow-400',
                                        'low'      => 'bg-blue-400',
                                        default    => 'bg-slate-500'
                                    }; ?>"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-slate-200 truncate"><?= htmlspecialchars($alert['title']) ?></div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        <?= htmlspecialchars($alert['source_ip'] ?? 'Unknown') ?>
                                        · <?= htmlspecialchars($alert['attack_type'] ?? 'Unknown') ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <?= severityBadge($alert['severity']) ?>
                                    <span class="text-xs text-slate-600"><?= timeAgo($alert['created_at']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Attackers & AI Quick Panel -->
                    <div class="space-y-4">
                        <!-- Top Attacking IPs -->
                        <div class="glass-card rounded-2xl p-5 border border-white/5">
                            <h3 class="text-sm font-semibold text-white mb-4">Top Attacking IPs</h3>
                            <?php if (empty($topIPs)): ?>
                            <div class="text-center text-slate-600 text-xs py-4">No data available</div>
                            <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($topIPs as $ip): ?>
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-lg bg-red-500/10 border border-red-500/20 flex items-center justify-center flex-shrink-0">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-red-400"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-mono text-slate-300 truncate"><?= htmlspecialchars($ip['source_ip']) ?></div>
                                        <div class="text-xs text-slate-600"><?= $ip['count'] ?> attacks</div>
                                    </div>
                                    <?= severityBadge($ip['max_severity']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- AI Analysis Quick Panel -->
                        <div class="glass-card rounded-2xl p-5 border border-white/5 bg-gradient-to-br from-brand-900/30 to-transparent">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-7 h-7 rounded-lg bg-brand-500/20 border border-brand-500/30 flex items-center justify-center">
                                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-brand-400"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-white">Gemini AI</h3>
                                <span class="ml-auto text-xs text-green-400 bg-green-500/10 border border-green-500/20 px-2 py-0.5 rounded-full">Active</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-4">Ask Gemini AI to analyze your security data</p>
                            <textarea id="ai-quick-input" class="w-full text-xs text-slate-300 bg-surface-600/50 border border-white/5 rounded-xl p-3 resize-none h-20 focus:border-brand-500/30 focus:outline-none placeholder-slate-600 transition-colors" 
                                      placeholder="e.g. Analyze the latest SSH brute-force attempts..."></textarea>
                            <button onclick="quickAiAnalysis()" 
                                    class="mt-2 w-full py-2 rounded-xl text-white text-xs font-semibold flex items-center justify-center gap-2 btn-primary">
                                <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                                Analyze with Gemini
                            </button>
                            <div id="ai-quick-result" class="mt-3 hidden text-xs text-slate-300 bg-surface-600/30 border border-white/5 rounded-xl p-3 leading-relaxed"></div>
                        </div>
                    </div>
                </div>

                <!-- Live Attack Feed -->
                <div class="animate-in glass-card rounded-2xl border border-white/5 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-semibold text-white">Live Attack Feed</h3>
                            <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/20">
                                <span class="live-dot w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                <span class="text-xs text-red-400 font-medium">Live</span>
                            </div>
                        </div>
                        <button onclick="clearFeed()" class="text-xs text-slate-500 hover:text-slate-300 transition-colors flex items-center gap-1">
                            <i data-lucide="trash-2" class="w-3 h-3"></i> Clear
                        </button>
                    </div>
                    <div id="attack-feed" class="font-mono text-xs p-4 space-y-1.5 max-h-48 overflow-y-auto bg-surface-900/50">
                        <div class="text-slate-600">Initializing attack feed...</div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
// Counter animation
document.querySelectorAll('[data-counter]').forEach(el => {
    const target = parseInt(el.dataset.counter);
    const duration = 1500;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = Math.floor(current).toLocaleString();
        if (current >= target) clearInterval(timer);
    }, 16);
});

// Threat Trend Chart
const threatChart = new ApexCharts(document.getElementById('threat-trend-chart'), {
    chart: { type: 'area', height: '100%', background: 'transparent', toolbar: { show: false }, sparkline: { enabled: false } },
    series: [],
    xaxis: { categories: [], labels: { style: { colors: '#64748b', fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { colors: '#64748b', fontSize: '11px' } }, min: 0 },
    grid: { borderColor: 'rgba(255,255,255,0.04)', strokeDashArray: 4 },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0, stops: [0, 100] } },
    colors: ['#ef4444', '#f97316', '#eab308', '#3b82f6'],
    legend: { labels: { colors: '#94a3b8' }, fontSize: '12px' },
    tooltip: { theme: 'dark' },
    dataLabels: { enabled: false },
    theme: { mode: 'dark' },
});
threatChart.render();

// Severity Donut Chart
const sevChart = new ApexCharts(document.getElementById('severity-chart'), {
    chart: { type: 'donut', height: '100%', background: 'transparent' },
    series: [1, 1, 1, 1, 1],
    labels: ['Critical', 'High', 'Medium', 'Low', 'Info'],
    colors: ['#ef4444', '#f97316', '#eab308', '#3b82f6', '#64748b'],
    legend: { show: false },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', color: '#94a3b8', fontSize: '12px', fontWeight: 600, formatter: w => w.globals.seriesTotals.reduce((a,b) => a+b, 0) } } } } },
    dataLabels: { enabled: false },
    tooltip: { theme: 'dark' },
    theme: { mode: 'dark' },
    stroke: { width: 0 },
});
sevChart.render();

// Load real chart data
function loadChartData() {
    fetch('<?= APP_URL ?>/dashboard/api/stats.php?type=trends')
        .then(r => r.json())
        .then(d => {
            if (d.series) threatChart.updateOptions({ series: d.series, xaxis: { categories: d.categories } });
            if (d.severity) {
                sevChart.updateSeries(d.severity.values);
                const legend = document.getElementById('severity-legend');
                const colors = ['#ef4444','#f97316','#eab308','#3b82f6','#64748b'];
                const labels = ['Critical','High','Medium','Low','Info'];
                legend.innerHTML = labels.map((l,i) => `
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full" style="background:${colors[i]}"></div>
                            <span class="text-slate-400">${l}</span>
                        </div>
                        <span class="text-slate-300 font-medium">${d.severity.values[i]}</span>
                    </div>`).join('');
            }
        }).catch(() => {
            // Fallback dummy data
            threatChart.updateOptions({
                series: [
                    { name: 'Critical', data: [3,5,2,8,4,6,3] },
                    { name: 'High',     data: [8,12,7,15,9,11,8] },
                    { name: 'Medium',   data: [15,18,12,20,14,16,13] },
                    { name: 'Low',      data: [22,25,19,28,21,24,20] }
                ],
                xaxis: { categories: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] }
            });
        });
}
loadChartData();

// Live Attack Feed
const severityColors = { critical:'text-red-400', high:'text-orange-400', medium:'text-yellow-400', low:'text-blue-400', info:'text-slate-400' };

function updateAttackFeed(callback) {
    fetch('<?= APP_URL ?>/dashboard/api/get_alerts.php?limit=10&format=feed')
        .then(r => r.json())
        .then(d => {
            if (callback) callback();
            if (!d.alerts) return;
            const feed = document.getElementById('attack-feed');
            if (!feed) return;
            feed.innerHTML = d.alerts.map(a => `
                <div class="flex items-start gap-3 animate-slide-up">
                    <span class="text-slate-600 flex-shrink-0">${new Date(a.created_at).toLocaleTimeString('en-US',{hour12:false})}</span>
                    <span class="font-semibold ${severityColors[a.severity] || 'text-slate-400'} uppercase flex-shrink-0">[${a.severity}]</span>
                    <span class="text-slate-400 flex-shrink-0">${a.source_ip || '0.0.0.0'}</span>
                    <span class="text-slate-300">${escHtml(a.title)}</span>
                </div>
            `).join('') || '<div class="text-slate-600">No recent activity</div>';
        }).catch(() => {
            if (callback) callback();
        });
}

function updateRecentAlerts(callback) {
    fetch('<?= APP_URL ?>/dashboard/api/get_alerts.php?limit=8')
        .then(r => r.json())
        .then(d => {
            if (callback) callback();
            if (!d.alerts) return;
            const list = document.getElementById('recent-alerts-list');
            if (!list) return;
            
            if (d.alerts.length === 0) {
                list.innerHTML = `
                    <div class="px-5 py-10 text-center text-slate-600 text-sm">
                        <i data-lucide="shield-check" class="w-8 h-8 mx-auto mb-2 text-slate-700"></i>
                        No alerts found. System is secure.
                    </div>`;
                lucide.createIcons();
                return;
            }

            const sevColors = {
                critical: 'bg-red-500',
                high:     'bg-orange-400',
                medium:   'bg-yellow-400',
                low:      'bg-blue-400',
                info:     'bg-slate-500'
            };

            const sevBadgeClasses = {
                critical: 'bg-red-500/20 text-red-400 border-red-500/30',
                high:     'bg-orange-500/20 text-orange-400 border-orange-500/30',
                medium:   'bg-yellow-500/20 text-yellow-400 border-yellow-500/20',
                low:      'bg-blue-500/20 text-blue-400 border-blue-500/20',
                info:     'bg-slate-500/20 text-slate-400 border-slate-500/20'
            };

            list.innerHTML = d.alerts.map(a => {
                const dotCls = sevColors[a.severity] || 'bg-slate-500';
                const badgeCls = sevBadgeClasses[a.severity] || 'bg-slate-500/20 text-slate-400';
                const label = a.severity.charAt(0).toUpperCase() + a.severity.slice(1);
                
                return `
                <div class="alert-row flex items-center gap-4 px-5 py-3.5 cursor-pointer transition-colors"
                     onclick="window.location='alerts.php?id=${a.id}'">
                    <div class="w-2 h-2 rounded-full flex-shrink-0 ${dotCls}"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-slate-200 truncate">${escHtml(a.title)}</div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            ${escHtml(a.source_ip || 'Unknown')}
                            · ${escHtml(a.attack_type || 'Unknown')}
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${badgeCls}">${label}</span>
                        <span class="text-xs text-slate-600">${getTimeAgo(a.created_at)}</span>
                    </div>
                </div>`;
            }).join('');
            
            lucide.createIcons();
        }).catch(() => {
            if (callback) callback();
        });
}

function getTimeAgo(dateStr) {
    if (!dateStr) return '—';
    const parts = dateStr.split(/[- :]/);
    const date = new Date(Date.UTC(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]));
    const diff = Math.floor((new Date() - date) / 1000);
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function updateStats(callback) {
    fetch('<?= APP_URL ?>/dashboard/api/stats.php?type=overview')
        .then(r => r.json())
        .then(d => {
            if (callback) callback();
            if (!d.stats) return;
            
            const mapping = {
                total_alerts: d.stats.total_alerts,
                critical_alerts: d.stats.critical_alerts,
                blocked_ips: d.stats.blocked_ips,
                ai_reports: d.stats.ai_reports
            };

            for (const [key, val] of Object.entries(mapping)) {
                const el = document.querySelector(`[data-stat-key="${key}"]`);
                if (el) {
                    const startVal = parseInt(el.textContent.replace(/,/g, '')) || 0;
                    animateCounter(el, startVal, val);
                }
            }
        }).catch(() => {
            if (callback) callback();
        });
}

function animateCounter(el, start, end) {
    if (start === end) return;
    const duration = 1000;
    const startTime = performance.now();
    
    function update(now) {
        const progress = Math.min((now - startTime) / duration, 1);
        const value = Math.floor(start + progress * (end - start));
        el.textContent = value.toLocaleString();
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    requestAnimationFrame(update);
}

// Global Manual Refresh
function triggerOverviewRefresh() {
    loadChartData(); // updates threat activity and severity donut charts
    updateAttackFeed();
    updateRecentAlerts();
    updateStats();
}

// Initial feed updates
updateAttackFeed();

// Listen to global SSE events for real-time overview updates
window.addEventListener('new-security-alert', e => {
    console.log('Overview page received real-time alert SSE:', e.detail);
    
    // Quietly update all metrics without page block
    loadChartData();
    updateAttackFeed();
    updateRecentAlerts();
    updateStats();
});

function clearFeed() {
    document.getElementById('attack-feed').innerHTML = '<div class="text-slate-600">Feed cleared.</div>';
}

// AI Quick Analysis
function quickAiAnalysis() {
    const input  = document.getElementById('ai-quick-input').value.trim();
    const result = document.getElementById('ai-quick-result');
    if (!input) { result.textContent = 'Please enter a query.'; result.classList.remove('hidden'); return; }
    result.textContent = 'Analyzing with Gemini AI...';
    result.classList.remove('hidden');
    fetch('<?= APP_URL ?>/dashboard/api/ai_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt: input, type: 'quick' })
    }).then(r => r.json()).then(d => {
        result.textContent = d.analysis || d.message || 'Analysis complete.';
    }).catch(() => {
        result.textContent = 'Error: Could not connect to Gemini AI. Ensure your API key is configured.';
    });
}

function escHtml(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
