<?php
/**
 * Quick Admin Creator — run once, then delete this file.
 * Access: http://localhost:8090/create_admin.php
 */

// Load app config
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$message = '';
$error   = '';
$done    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $secret    = $_POST['secret']         ?? '';

    // Simple secret key to prevent unauthorized use
    if ($secret !== 'CyberAI_Setup_2024') {
        $error = 'Wrong setup key. Check the file for the correct key.';
    } elseif (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check if username/email already exists
        $existing = db()->fetch("SELECT id FROM users WHERE username=? OR email=?", [$username, $email]);
        if ($existing) {
            $error = 'Username or email already exists in the database.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $id = db()->insert('users', [
                'username'   => $username,
                'email'      => $email,
                'password'   => $hash,
                'full_name'  => $full_name,
                'role'       => 'admin',
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $message = "Admin user <strong>$username</strong> created successfully (ID: $id). <strong>Delete this file immediately!</strong>";
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin — CyberAI Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        *{font-family:'Inter',sans-serif}
        body{background:#060a14}
        .grid-bg{background-image:linear-gradient(rgba(66,99,235,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(66,99,235,.04) 1px,transparent 1px);background-size:44px 44px}
        .glass{background:rgba(13,18,37,.95);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.08)}
        .inp{background:rgba(26,34,54,.8)!important;border:1px solid rgba(255,255,255,.08)!important;color:#e2e8f0!important;transition:border-color .2s,box-shadow .2s}
        .inp:focus{border-color:#4263eb!important;box-shadow:0 0 0 3px rgba(66,99,235,.15)!important;outline:none}
        .btn{background:linear-gradient(135deg,#4263eb,#5c7cfa);transition:all .2s}
        .btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(66,99,235,.4)}
        /* Password strength */
        .strength-bar{height:4px;border-radius:2px;transition:all .3s}
    </style>
</head>
<body class="grid-bg min-h-screen flex items-center justify-center p-4 dark">

    <div class="w-full max-w-md">

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-500 flex items-center justify-center mx-auto mb-4 shadow-lg"
                 style="background:linear-gradient(135deg,#4263eb,#5c7cfa)">
                <i data-lucide="user-cog" class="w-7 h-7 text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Create Admin User</h1>
            <p class="text-slate-500 text-sm mt-1">One-time setup — <span class="text-red-400">delete this file after use</span></p>
        </div>

        <div class="glass rounded-2xl p-8 shadow-2xl">

            <?php if ($done): ?>
            <!-- Success -->
            <div class="text-center py-4">
                <div class="w-16 h-16 rounded-full bg-green-500/10 border border-green-500/20 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="check-circle" class="w-8 h-8 text-green-400"></i>
                </div>
                <p class="text-green-400 text-sm leading-relaxed mb-6"><?= $message ?></p>
                <div class="space-y-3">
                    <a href="login.php"
                       class="btn w-full flex items-center justify-center gap-2 py-3 rounded-xl text-white text-sm font-semibold">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Go to Login
                    </a>
                    <div class="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3 text-left">
                        <i data-lucide="triangle-alert" class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5"></i>
                        <span class="text-xs text-red-400">Delete <code class="font-mono bg-red-500/20 px-1 rounded">create_admin.php</code> from your server immediately for security!</span>
                    </div>
                </div>
            </div>

            <?php else: ?>

            <?php if ($error): ?>
            <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl px-4 py-3 text-sm mb-5">
                <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Security key notice -->
            <div class="flex items-start gap-2 bg-amber-500/8 border border-amber-500/20 rounded-xl px-4 py-3 mb-5">
                <i data-lucide="key" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                <div class="text-xs text-amber-400/80">
                    Setup key required: <code class="font-mono bg-amber-500/20 px-1.5 py-0.5 rounded text-amber-300">CyberAI_Setup_2024</code>
                </div>
            </div>

            <form method="POST" class="space-y-4">

                <!-- Setup Key -->
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Setup Key</label>
                    <input type="password" name="secret" required
                           class="inp w-full px-3 py-2.5 rounded-xl text-sm" placeholder="Enter setup key">
                </div>

                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Full Name</label>
                    <input type="text" name="full_name" required
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           class="inp w-full px-3 py-2.5 rounded-xl text-sm" placeholder="e.g. Mani Admin">
                </div>

                <!-- Username & Email -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Username</label>
                        <input type="text" name="username" required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               class="inp w-full px-3 py-2.5 rounded-xl text-sm" placeholder="mani">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Email</label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="inp w-full px-3 py-2.5 rounded-xl text-sm" placeholder="mani@example.com">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="pwd" required minlength="8"
                               oninput="checkStrength(this.value)"
                               class="inp w-full px-3 py-2.5 pr-10 rounded-xl text-sm" placeholder="Min 8 characters">
                        <button type="button" onclick="togglePwd()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300">
                            <i data-lucide="eye" class="w-4 h-4" id="eye-icon"></i>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div class="flex gap-1 mt-2">
                        <div class="strength-bar flex-1 bg-white/10" id="s1"></div>
                        <div class="strength-bar flex-1 bg-white/10" id="s2"></div>
                        <div class="strength-bar flex-1 bg-white/10" id="s3"></div>
                        <div class="strength-bar flex-1 bg-white/10" id="s4"></div>
                    </div>
                    <p class="text-xs text-slate-600 mt-1" id="strength-text">Enter a password</p>
                </div>

                <!-- Role (locked to admin) -->
                <div class="flex items-center gap-3 bg-brand-500/8 border border-brand-500/20 rounded-xl px-4 py-3">
                    <i data-lucide="shield" class="w-4 h-4 text-brand-400 flex-shrink-0"></i>
                    <span class="text-xs text-brand-400">This user will be created with <strong>Administrator</strong> role.</span>
                </div>

                <button type="submit" class="btn w-full py-3 rounded-xl text-white text-sm font-semibold flex items-center justify-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Create Admin Account
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-xs text-slate-500 hover:text-slate-300 transition-colors">
                    Already have an account? Log in →
                </a>
            </div>

            <?php endif; ?>
        </div>
    </div>

<script>
lucide.createIcons();

function togglePwd() {
    const inp  = document.getElementById('pwd');
    const icon = document.getElementById('eye-icon');
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
    lucide.createIcons();
}

function checkStrength(val) {
    const bars = ['s1','s2','s3','s4'];
    const txt  = document.getElementById('strength-text');
    let score  = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
    const labels = ['Weak','Fair','Good','Strong'];
    bars.forEach((id, i) => {
        const el = document.getElementById(id);
        el.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,0.1)';
    });
    txt.textContent = val.length === 0 ? 'Enter a password' : labels[score - 1] || 'Too weak';
    txt.style.color = val.length === 0 ? '' : colors[score - 1];
}
</script>
</body>
</html>
