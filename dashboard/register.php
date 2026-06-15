<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (auth()->isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = auth()->register([
        'username'         => trim($_POST['username'] ?? ''),
        'email'            => trim($_POST['email'] ?? ''),
        'password'         => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name'        => trim($_POST['full_name'] ?? ''),
        'role'             => 'analyst',
    ]);
    if ($result['success']) {
        header('Location: login.php?registered=1');
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0a0e1a; background-image: linear-gradient(rgba(66,99,235,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(66,99,235,0.04) 1px, transparent 1px); background-size: 40px 40px; }
        .glass-card { background: rgba(13,18,37,0.9); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.07); }
        .input-field { background: rgba(26,34,54,0.8) !important; border: 1px solid rgba(255,255,255,0.07) !important; color: #e2e8f0 !important; transition: border-color 0.2s; }
        .input-field:focus { border-color: #4263eb !important; box-shadow: 0 0 0 3px rgba(66,99,235,0.15) !important; outline: none; }
        .btn-register { background: linear-gradient(135deg, #4263eb, #5c7cfa); transition: all 0.2s; }
        .btn-register:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(66,99,235,0.4); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="glass-card rounded-2xl p-8 w-full max-w-md shadow-2xl">
        <div class="text-center mb-8">
            <div class="flex items-center justify-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-500 flex items-center justify-center">
                    <i data-lucide="shield" class="w-5 h-5 text-white"></i>
                </div>
                <span class="font-bold text-white text-lg">CyberAI</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Create Account</h1>
            <p class="text-slate-500 text-sm mt-1">Join the security operations center</p>
        </div>
        <?php if ($error): ?>
        <div class="flex items-start gap-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl px-4 py-3 mb-5 text-sm">
            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Full Name</label>
                    <input type="text" name="full_name" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Username</label>
                    <input type="text" name="username" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="johndoe" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Email</label>
                <input type="email" name="email" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Password</label>
                <input type="password" name="password" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="Min 8 characters" required minlength="8">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Confirm Password</label>
                <input type="password" name="confirm_password" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="Repeat password" required>
            </div>
            <button type="submit" class="btn-register w-full py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Create Account
            </button>
        </form>
        <p class="mt-5 text-center text-sm text-slate-600">
            Already have an account? <a href="login.php" class="text-blue-400 hover:text-blue-300 transition-colors font-medium">Sign in</a>
        </p>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
