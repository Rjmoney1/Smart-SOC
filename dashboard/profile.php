<?php
/**
 * Profile Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireLogin();

$pageTitle   = 'My Profile';
$activePage  = 'profile';
$currentUser = auth()->getCurrentUser();
$user        = db()->fetch("SELECT * FROM users WHERE id=?", [$currentUser['id']]);
$message     = '';
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if (!$fullName || !$email) { $error = 'Full name and email are required.'; }
        elseif (!isValidEmail($email)) { $error = 'Invalid email address.'; }
        else {
            db()->update('users', ['full_name'=>$fullName,'email'=>$email,'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$currentUser['id']]);
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email']     = $email;
            $message = 'Profile updated successfully.';
            $user = db()->fetch("SELECT * FROM users WHERE id=?", [$currentUser['id']]);
        }
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) { $error = 'Current password is incorrect.'; }
        elseif (strlen($new) < PASSWORD_MIN_LENGTH)         { $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'; }
        elseif ($new !== $confirm)                          { $error = 'New passwords do not match.'; }
        else {
            db()->update('users', ['password'=>password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]),'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$currentUser['id']]);
            $message = 'Password changed successfully.';
        }
    }
}

// Activity stats
$myAlerts   = db()->count('alerts', 'user_id=?', [$currentUser['id']]);
$myReports  = db()->count('ai_reports', 'user_id=?', [$currentUser['id']]);
$myBlockedIPs = db()->count('blocked_ips', 'blocked_by=?', [$currentUser['id']]);

include __DIR__ . '/includes/header.php';
?>

<div class="flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    <div class="flex-1 lg:ml-64">
        <?php include __DIR__ . '/components/navbar.php'; ?>
        <main class="pt-16 min-h-screen">
            <div class="p-6 max-w-3xl space-y-6">

                <!-- Header -->
                <div class="animate-in">
                    <h2 class="text-xl font-bold text-white">My Profile</h2>
                    <p class="text-slate-500 text-sm mt-0.5">Manage your account settings and security</p>
                </div>

                <!-- Alerts -->
                <?php if ($message): ?><div class="flex items-center gap-3 bg-green-500/10 border border-green-500/20 text-green-400 rounded-xl px-4 py-3 text-sm"><i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <?php if ($error):   ?><div class="flex items-center gap-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl px-4 py-3 text-sm"><i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <!-- Profile Card -->
                <div class="animate-in glass-card rounded-2xl p-6 border border-white/5">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-2xl flex-shrink-0">
                            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($user['full_name']) ?></h3>
                            <div class="text-sm text-slate-500">@<?= htmlspecialchars($user['username']) ?></div>
                            <div class="flex items-center gap-2 mt-1">
                                <?php $roleCls = match($user['role']) { 'admin'=>'text-red-400 bg-red-500/10 border-red-500/20', 'analyst'=>'text-blue-400 bg-blue-500/10 border-blue-500/20', default=>'text-slate-400 bg-slate-500/10 border-slate-500/20' }; ?>
                                <span class="text-xs px-2 py-0.5 rounded-full border font-medium <?= $roleCls ?>"><?= ucfirst($user['role']) ?></span>
                                <span class="text-xs text-slate-600">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Stats -->
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <?php foreach ([['Alerts Created',$myAlerts,'bell-ring'],['AI Reports',$myReports,'sparkles'],['IPs Blocked',$myBlockedIPs,'ban']] as [$label,$val,$icon]): ?>
                        <div class="text-center p-3 bg-surface-600/30 rounded-xl">
                            <div class="text-xl font-bold text-white"><?= $val ?></div>
                            <div class="text-xs text-slate-500 mt-0.5"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Update Profile Form -->
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">Full Name</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none">
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">Username</label>
                                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled class="w-full px-3 py-2.5 rounded-xl text-sm opacity-50 cursor-not-allowed">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">Last Login</label>
                                <input type="text" value="<?= $user['last_login'] ? timeAgo($user['last_login']) : 'Never' ?>" disabled class="w-full px-3 py-2.5 rounded-xl text-sm opacity-50 cursor-not-allowed">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-medium text-white">
                            <i data-lucide="save" class="w-4 h-4"></i> Save Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="animate-in glass-card rounded-2xl p-6 border border-white/5">
                    <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                        <i data-lucide="lock" class="w-4 h-4 text-orange-400"></i> Change Password
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Current Password</label>
                            <input type="password" name="current_password" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="Enter current password">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">New Password</label>
                                <input type="password" name="new_password" required minlength="8" class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="Min 8 characters">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5">Confirm New Password</label>
                                <input type="password" name="confirm_password" required class="w-full px-3 py-2.5 rounded-xl text-sm bg-surface-600/50 border border-white/5 text-slate-300 focus:border-brand-500/30 focus:outline-none" placeholder="Repeat password">
                            </div>
                        </div>
                        <button type="submit" class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-medium text-orange-400 bg-orange-500/10 border border-orange-500/20 hover:bg-orange-500/20 transition-colors">
                            <i data-lucide="key" class="w-4 h-4"></i> Update Password
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
