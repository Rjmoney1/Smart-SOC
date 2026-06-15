<?php
/**
 * Top Navbar Component
 */
?>
<header class="fixed top-0 right-0 left-0 lg:left-64 z-30 glass border-b border-white/5 h-16 flex items-center px-6 gap-4">
    <!-- Mobile Menu Toggle -->
    <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/5">
        <i data-lucide="menu" class="w-5 h-5"></i>
    </button>

    <!-- Page Title -->
    <div class="flex items-center gap-3">
        <h1 class="text-base font-semibold text-white"><?= htmlspecialchars($pageTitle) ?></h1>
        <div class="hidden sm:flex items-center gap-1 text-xs text-slate-500">
            <i data-lucide="chevron-right" class="w-3 h-3"></i>
            <span class="capitalize"><?= htmlspecialchars($activePage ?: 'overview') ?></span>
        </div>
    </div>

    <div class="flex-1"></div>

    <!-- Search -->
    <div class="hidden md:flex items-center gap-2 bg-surface-600/50 border border-white/5 rounded-lg px-3 py-1.5 w-64 hover:border-brand-500/30 transition-colors">
        <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
        <input type="text" id="global-search" placeholder="Search logs, alerts..." 
               class="bg-transparent border-none outline-none text-sm text-slate-300 placeholder-slate-600 flex-1 !bg-transparent !border-0 !shadow-none">
    </div>

    <!-- Real-time Clock -->
    <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500 font-mono">
        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
        <span id="live-clock"></span>
    </div>

    <!-- Notifications -->
    <div class="relative">
        <button id="notif-btn" class="relative p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
                onclick="toggleNotifications()">
            <i data-lucide="bell" class="w-5 h-5"></i>
            <span id="notif-badge" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full hidden"></span>
        </button>

        <!-- Notification Dropdown -->
        <div id="notif-dropdown" class="hidden absolute right-0 top-full mt-2 w-80 glass-card rounded-xl shadow-2xl border border-white/5 overflow-hidden z-50">
            <div class="px-4 py-3 border-b border-white/5 flex items-center justify-between">
                <span class="text-sm font-semibold text-white">Notifications</span>
                <button class="text-xs text-brand-400 hover:text-brand-300" onclick="markAllRead()">Mark all read</button>
            </div>
            <div id="notif-list" class="max-h-72 overflow-y-auto divide-y divide-white/5">
                <div class="px-4 py-8 text-center text-slate-500 text-sm">Loading...</div>
            </div>
            <div class="px-4 py-3 border-t border-white/5 text-center">
                <a href="<?= APP_URL ?>/dashboard/alerts.php" class="text-xs text-brand-400 hover:text-brand-300">View all alerts →</a>
            </div>
        </div>
    </div>

    <!-- Theme / Profile -->
    <div class="flex items-center gap-2">
        <a href="<?= APP_URL ?>/dashboard/profile.php" class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-semibold text-sm hover:scale-105 transition-transform">
            <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'] ?? 'U', 0, 1)) ?>
        </a>
    </div>
</header>

<script>
// Live Clock
function updateClock() {
    const el = document.getElementById('live-clock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-US', { hour12: false });
}
updateClock();
setInterval(updateClock, 1000);

// Notifications
function toggleNotifications() {
    const dd = document.getElementById('notif-dropdown');
    dd.classList.toggle('hidden');
    if (!dd.classList.contains('hidden')) loadNotifications();
}

function loadNotifications() {
    fetch('<?= APP_URL ?>/dashboard/api/get_alerts.php?limit=5&status=open')
        .then(r => r.json())
        .then(d => {
            const list = document.getElementById('notif-list');
            const badge = document.getElementById('notif-badge');
            if (!d.alerts || d.alerts.length === 0) {
                list.innerHTML = '<div class="px-4 py-8 text-center text-slate-500 text-sm">No new alerts</div>';
                return;
            }
            badge.classList.remove('hidden');
            list.innerHTML = d.alerts.map(a => `
                <div class="px-4 py-3 hover:bg-white/3 transition-colors cursor-pointer" onclick="window.location='<?= APP_URL ?>/dashboard/alerts.php?id=${a.id}'">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 ${severityDot(a.severity)}"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-slate-200 font-medium truncate">${escHtml(a.title)}</div>
                            <div class="text-xs text-slate-500 mt-0.5">${escHtml(a.source_ip || 'Unknown')} · ${timeAgo(a.created_at)}</div>
                        </div>
                    </div>
                </div>
            `).join('');
        }).catch(() => {});
}

function severityDot(s) {
    const m = { critical: 'bg-red-500', high: 'bg-orange-400', medium: 'bg-yellow-400', low: 'bg-blue-400', info: 'bg-slate-400' };
    return m[s] || 'bg-slate-400';
}

function escHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function timeAgo(dt) {
    const diff = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function markAllRead() {
    document.getElementById('notif-badge').classList.add('hidden');
    document.getElementById('notif-dropdown').classList.add('hidden');
}

document.addEventListener('click', e => {
    const btn = document.getElementById('notif-btn');
    const dd  = document.getElementById('notif-dropdown');
    if (!btn.contains(e.target) && !dd.contains(e.target)) dd.classList.add('hidden');
});
</script>
