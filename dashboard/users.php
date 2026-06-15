<?php
/**
 * User Management Page (Admin Only)
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireRole(ROLE_ADMIN);

$pageTitle  = 'User Management';
$activePage = 'users';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = auth()->register([
                'username'         => $_POST['username'],
                'email'            => $_POST['email'],
                'password'         => $_POST['password'],
                'confirm_password' => $_POST['password'],
                'full_name'        => $_POST['full_name'],
                'role'             => $_POST['role'],
            ]);
            echo json_encode($result);
            exit;
        case 'toggle_status':
            $userId = (int)$_POST['user_id'];
            if ($userId === auth()->getCurrentUser()['id']) {
                echo json_encode(['success'=>false,'message'=>'Cannot deactivate yourself.']);
                exit;
            }
            $user = db()->fetch("SELECT is_active FROM users WHERE id=?",[$userId]);
            db()->update('users',['is_active'=> $user ? ($user['is_active'] ? 0 : 1) : 0],'id=?',[$userId]);
            echo json_encode(['success'=>true]);
            exit;
        case 'update_role':
            $userId = (int)$_POST['user_id'];
            $role   = in_array($_POST['role'],['admin','analyst','viewer']) ? $_POST['role'] : 'analyst';
            db()->update('users',['role'=>$role],'id=?',[$userId]);
            echo json_encode(['success'=>true]);
            exit;
        case 'delete':
            $userId = (int)$_POST['user_id'];
            if ($userId === auth()->getCurrentUser()['id']) {
                echo json_encode(['success'=>false,'message'=>'Cannot delete yourself.']);
                exit;
            }
            db()->delete('users','id=?',[$userId]);
            echo json_encode(['success'=>true]);
            exit;
    }
}

$page      = max(1, (int)($_GET['page'] ?? 1));
$search    = trim($_GET['search'] ?? '');
$roleFilter= $_GET['role'] ?? '';
$where     = [];
$params    = [];
if ($search)     { $where[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)"; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($roleFilter) { $where[] = "role=?"; $params[] = $roleFilter; }
$whereStr  = $where ? 'WHERE '.implode(' AND ',$where) : '';
$sql       = "SELECT * FROM users $whereStr ORDER BY created_at DESC";
$paginated = paginate($sql, $params, $page);
$users     = $paginated['data'];

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
                        <h2 class="text-xl font-bold text-white">User Management</h2>
                        <p class="text-slate-500 text-sm mt-0.5"><?= $paginated['total'] ?> total users</p>
                    </div>
                    <button onclick="document.getElementById('create-user-modal').classList.remove('hidden')"
                            class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Add User
                    </button>
                </div>

                <!-- Search & Filter -->
                <div class="animate-in glass-card rounded-2xl p-5 border border-white/5">
                    <form method="GET" class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1 relative">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search users..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 placeholder-slate-600 focus:border-brand-500/30 focus:outline-none transition-colors">
                        </div>
                        <select name="role" class="px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none min-w-[130px]">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $roleFilter==='admin'?'selected':''?>>Admin</option>
                            <option value="analyst" <?= $roleFilter==='analyst'?'selected':''?>>Analyst</option>
                            <option value="viewer" <?= $roleFilter==='viewer'?'selected':''?>>Viewer</option>
                        </select>
                        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-medium text-white flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4"></i> Filter
                        </button>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="animate-in glass-card rounded-2xl border border-white/5 overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-white/5">
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">User</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden md:table-cell">Email</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Role</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Status</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden lg:table-cell">Last Login</th>
                                <th class="text-left text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide hidden lg:table-cell">Joined</th>
                                <th class="text-right text-xs font-semibold text-slate-500 px-5 py-3.5 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="px-5 py-12 text-center text-slate-600 text-sm">No users found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($users as $user):
                                $isSelf = $user['id'] == auth()->getCurrentUser()['id'];
                                $roleCls = match($user['role']) {
                                    'admin'   => 'text-red-400 bg-red-500/10 border-red-500/20',
                                    'analyst' => 'text-blue-400 bg-blue-500/10 border-blue-500/20',
                                    default   => 'text-slate-400 bg-slate-500/10 border-slate-500/20',
                                };
                            ?>
                            <tr class="alert-row transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                                            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-slate-200">
                                                <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>
                                                <?php if ($isSelf): ?> <span class="text-xs text-brand-400">(you)</span><?php endif; ?>
                                            </div>
                                            <div class="text-xs text-slate-500">@<?= htmlspecialchars($user['username']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell text-sm text-slate-400"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-5 py-4">
                                    <select onchange="updateRole(<?= $user['id'] ?>, this.value)" <?= $isSelf ? 'disabled' : '' ?>
                                            class="text-xs px-2 py-1 rounded-lg border focus:outline-none transition-colors <?= $roleCls ?> bg-transparent cursor-pointer">
                                        <?php foreach (['admin','analyst','viewer'] as $r): ?>
                                        <option value="<?= $r ?>" <?= $user['role']===$r?'selected':'' ?> class="bg-surface-700 text-slate-200"><?= ucfirst($r) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-5 py-4">
                                    <button onclick="toggleUserStatus(<?= $user['id'] ?>, this)" <?= $isSelf ? 'disabled' : '' ?>
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors
                                                   <?= $user['is_active'] ? 'bg-green-500/10 text-green-400 border-green-500/20 hover:bg-green-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20' ?>
                                                   <?= $isSelf ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $user['is_active'] ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                    </button>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell text-xs text-slate-500">
                                    <?= $user['last_login'] ? timeAgo($user['last_login']) : 'Never' ?>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell text-xs text-slate-500">
                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                            <?= $isSelf ? 'disabled' : '' ?>
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors <?= $isSelf ? 'opacity-30 cursor-not-allowed' : '' ?>">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
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

<!-- Create User Modal -->
<div id="create-user-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay bg-black/60 p-4">
    <div class="glass-card rounded-2xl border border-white/10 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
            <h3 class="text-base font-semibold text-white">Add New User</h3>
            <button onclick="document.getElementById('create-user-modal').classList.add('hidden')" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form onsubmit="createUser(event)" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Full Name</label>
                    <input type="text" name="full_name" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="John Doe">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Username</label>
                    <input type="text" name="username" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="johndoe">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Email</label>
                <input type="email" name="email" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="john@example.com">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Password</label>
                <input type="password" name="password" required minlength="8" class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="Min 8 characters">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Role</label>
                <select name="role" class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none">
                    <option value="analyst">Analyst</option>
                    <option value="viewer">Viewer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div id="create-user-error" class="hidden text-xs text-red-400 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-2.5"></div>
            <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-medium text-white flex items-center justify-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Create User
            </button>
        </form>
    </div>
</div>

<script>
function createUser(e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    data.append('action', 'create');
    
    fetch('users.php', { method: 'POST', body: data })
        .then(r => r.json()).then(d => {
            if (d.success) { location.reload(); }
            else {
                const err = document.getElementById('create-user-error');
                err.textContent = d.message;
                err.classList.remove('hidden');
            }
        });
}

function toggleUserStatus(id, btn) {
    const fd = new FormData();
    fd.append('action', 'toggle_status');
    fd.append('user_id', id);
    fetch('users.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function updateRole(id, role) {
    const fd = new FormData();
    fd.append('action', 'update_role');
    fd.append('user_id', id);
    fd.append('role', role);
    fetch('users.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (!d.success) alert(d.message || 'Failed to update role.'); });
}

function deleteUser(id, username) {
    if (!confirm(`Delete user "${username}"? This action cannot be undone.`)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('user_id', id);
    fetch('users.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message || 'Failed to delete user.'); });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
