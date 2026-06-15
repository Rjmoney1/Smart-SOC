<?php
/**
 * Blocked IPs Management Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireRole(ROLE_ADMIN, ROLE_ANALYST);

$pageTitle  = 'Blocked IPs';
$activePage = 'blocked';

$blockedIPs = db()->fetchAll(
    "SELECT b.*, u.username FROM blocked_ips b LEFT JOIN users u ON b.blocked_by = u.id ORDER BY b.blocked_at DESC"
);
$activeCount   = array_sum(array_column($blockedIPs, 'is_active'));
$inactiveCount = count($blockedIPs) - $activeCount;

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
                        <h2 class="text-xl font-bold text-white">Blocked IPs</h2>
                        <p class="text-slate-500 text-sm mt-0.5"><?= $activeCount ?> active blocks · <?= $inactiveCount ?> unblocked</p>
                    </div>
                    <button onclick="document.getElementById('block-ip-modal').classList.remove('hidden')"
                            class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white">
                        <i data-lucide="shield-x" class="w-4 h-4"></i> Block IP
                    </button>
                </div>

                <!-- Table -->
                <div class="animate-in glass-card rounded-2xl border border-white/5 overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-white/5">
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">IP Address</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden md:table-cell">Reason</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Status</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden lg:table-cell">Blocked By</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden lg:table-cell">Blocked At</th>
                                <th class="text-right text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (empty($blockedIPs)): ?>
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-600 text-sm">
                                    <i data-lucide="shield-check" class="w-10 h-10 mx-auto mb-3 text-slate-700"></i>
                                    No blocked IPs yet.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($blockedIPs as $blocked): ?>
                            <tr class="alert-row transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg <?= $blocked['is_active'] ? 'bg-red-500/10 border border-red-500/20' : 'bg-slate-500/10 border border-slate-500/20' ?> flex items-center justify-center flex-shrink-0">
                                            <i data-lucide="ban" class="w-3.5 h-3.5 <?= $blocked['is_active'] ? 'text-red-400' : 'text-slate-500' ?>"></i>
                                        </div>
                                        <span class="font-mono text-sm text-slate-200"><?= htmlspecialchars($blocked['ip_address']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell">
                                    <span class="text-sm text-slate-400 max-w-xs truncate block" title="<?= htmlspecialchars($blocked['reason'] ?? '') ?>">
                                        <?= htmlspecialchars($blocked['reason'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border
                                        <?= $blocked['is_active'] ? 'bg-red-500/10 text-red-400 border-red-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $blocked['is_active'] ? 'bg-red-400' : 'bg-slate-500' ?>"></span>
                                        <?= $blocked['is_active'] ? 'Blocked' : 'Unblocked' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell text-sm text-slate-500">
                                    <?= htmlspecialchars($blocked['username'] ?? 'System') ?>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell text-xs text-slate-500">
                                    <?= timeAgo($blocked['blocked_at']) ?>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button onclick="toggleBlock('<?= htmlspecialchars($blocked['ip_address']) ?>', <?= $blocked['is_active'] ?>)"
                                            class="p-1.5 rounded-lg text-slate-500 transition-colors
                                                   <?= $blocked['is_active'] ? 'hover:text-green-400 hover:bg-green-500/10' : 'hover:text-red-400 hover:bg-red-500/10' ?>"
                                            title="<?= $blocked['is_active'] ? 'Unblock' : 'Re-block' ?>">
                                        <i data-lucide="<?= $blocked['is_active'] ? 'shield-check' : 'ban' ?>" class="w-3.5 h-3.5"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- Block IP Modal -->
<div id="block-ip-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay bg-black/60 p-4">
    <div class="glass-card rounded-2xl border border-white/10 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
            <h3 class="text-base font-semibold text-white">Block IP Address</h3>
            <button onclick="document.getElementById('block-ip-modal').classList.add('hidden')" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">IP Address</label>
                <input type="text" id="block-ip-input" class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none font-mono" placeholder="e.g. 192.168.1.1">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Reason</label>
                <input type="text" id="block-reason-input" class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="e.g. SSH brute force attack" value="Manually blocked by admin">
            </div>
            <div id="block-result" class="hidden text-xs p-3 rounded-xl"></div>
            <button onclick="submitBlock()" class="btn-primary w-full py-2.5 rounded-xl text-sm font-medium text-white flex items-center justify-center gap-2">
                <i data-lucide="ban" class="w-4 h-4"></i> Block IP
            </button>
        </div>
    </div>
</div>

<script>
function submitBlock() {
    const ip = document.getElementById('block-ip-input').value.trim();
    const reason = document.getElementById('block-reason-input').value.trim();
    if (!ip) { alert('Please enter an IP address.'); return; }
    fetch('<?= APP_URL ?>/dashboard/api/block_ip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ip, reason })
    }).then(r => r.json()).then(d => {
        const res = document.getElementById('block-result');
        res.classList.remove('hidden');
        res.className = `text-xs p-3 rounded-xl ${d.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`;
        res.textContent = d.message;
        if (d.success) setTimeout(() => location.reload(), 1500);
    });
}

function toggleBlock(ip, isActive) {
    const action = isActive ? 'unblock' : 'block';
    if (!confirm(`${action === 'unblock' ? 'Unblock' : 'Re-block'} IP ${ip}?`)) return;
    fetch('<?= APP_URL ?>/dashboard/api/block_ip.php', {
        method: isActive ? 'DELETE' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ip, reason: 'Re-blocked by admin' })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.message || 'Action failed.');
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
