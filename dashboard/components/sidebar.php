<?php
/**
 * Sidebar Navigation Component
 */
$currentUser = auth()->getCurrentUser();
$nav = [
    ['icon' => 'layout-dashboard', 'label' => 'Dashboard',       'href' => 'index.php',    'page' => 'dashboard'],
    ['icon' => 'bell-ring',        'label' => 'Alerts',           'href' => 'alerts.php',   'page' => 'alerts'],
    ['icon' => 'scroll-text',      'label' => 'Logs',             'href' => 'logs.php',     'page' => 'logs'],
    ['icon' => 'file-bar-chart',   'label' => 'AI Reports',       'href' => 'reports.php',  'page' => 'reports'],
    ['icon' => 'users',            'label' => 'Users',            'href' => 'users.php',    'page' => 'users',    'roles' => ['admin']],
    ['icon' => 'shield-check',     'label' => 'Blocked IPs',      'href' => 'blocked.php',  'page' => 'blocked'],
    ['icon' => 'settings',         'label' => 'Settings',         'href' => 'settings.php', 'page' => 'settings'],
    ['icon' => 'database',         'label' => 'MyAdmin (DB)',     'href' => 'myadmin/',     'page' => 'myadmin',   'roles' => ['admin']],
];
?>

<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 glass border-r border-white/5 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0">
    <!-- Logo -->
    <div class="flex items-center gap-3 px-6 py-5 border-b border-white/5">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 to-brand-400 flex items-center justify-center shadow-lg glow-blue">
            <i data-lucide="shield" class="w-5 h-5 text-white"></i>
        </div>
        <div>
            <span class="font-bold text-white text-base tracking-tight">CyberAI</span>
            <div class="text-xs text-slate-500 -mt-0.5">Security Platform</div>
        </div>
    </div>

    <!-- Live Status -->
    <div class="px-4 py-3 border-b border-white/5">
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-green-500/10 border border-green-500/20">
            <span class="live-dot w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
            <span class="text-xs text-green-400 font-medium">System Operational</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
        <div class="text-xs font-semibold text-slate-600 uppercase tracking-widest px-3 mb-3">Navigation</div>

        <?php foreach ($nav as $item): ?>
            <?php
            if (isset($item['roles']) && !in_array($currentUser['role'], $item['roles'])) continue;
            $isActive = ($activePage === $item['page']);
            ?>
            <a href="<?= APP_URL ?>/dashboard/<?= $item['href'] ?>"
               class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium border-l-2 border-transparent cursor-pointer
                      <?= $isActive ? 'active text-brand-400 bg-brand-600/15' : 'text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="<?= $item['icon'] ?>" class="w-4.5 h-4.5 flex-shrink-0 w-5 h-5"></i>
                <span><?= $item['label'] ?></span>
                <?php if ($item['page'] === 'alerts'): ?>
                    <span id="sidebar-alert-count" class="ml-auto text-xs bg-red-500/20 text-red-400 border border-red-500/30 px-1.5 py-0.5 rounded-full"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-white/5">
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-surface-600/50 hover:bg-surface-600 transition-colors cursor-pointer" onclick="window.location='<?= APP_URL ?>/dashboard/profile.php'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-slate-200 truncate"><?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></div>
                <div class="text-xs text-slate-500 capitalize"><?= htmlspecialchars($currentUser['role']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/dashboard/logout.php" class="text-slate-500 hover:text-red-400 transition-colors" title="Logout" onclick="event.stopPropagation()">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 z-30 bg-black/60 lg:hidden hidden" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Update alert count
fetch('<?= APP_URL ?>/dashboard/api/get_alerts.php?count=1')
    .then(r => r.json())
    .then(d => {
        const el = document.getElementById('sidebar-alert-count');
        if (el && d.open_count > 0) {
            el.textContent = d.open_count;
        }
    }).catch(() => {});
</script>
