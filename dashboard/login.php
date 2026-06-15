<?php
/**
 * Login Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (auth()->isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $result = auth()->login($username, $password);
        if ($result['success']) {
            header('Location: ' . APP_URL . '/dashboard/index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle  = 'Login';
$activePage = 'login';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0a0e1a; }
        .grid-bg {
            background-image: linear-gradient(rgba(66,99,235,0.05) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(66,99,235,0.05) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .glass-card { background: rgba(13,18,37,0.9); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.07); }
        .input-field {
            background: rgba(26,34,54,0.8) !important;
            border: 1px solid rgba(255,255,255,0.07) !important;
            color: #e2e8f0 !important;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-field:focus { border-color: #4263eb !important; box-shadow: 0 0 0 3px rgba(66,99,235,0.15) !important; outline: none; }
        .btn-login { background: linear-gradient(135deg, #4263eb, #5c7cfa); transition: all 0.2s ease; }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(66,99,235,0.4); }
        .feature-dot { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        .float-anim { animation: float 6s ease-in-out infinite; }
    </style>
</head>
<body class="grid-bg min-h-screen flex">

    <!-- Left Panel — Branding -->
    <div class="hidden lg:flex flex-col justify-between w-1/2 p-12 relative overflow-hidden">
        <!-- Background Glow -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full filter blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-1/4 right-1/4 w-64 h-64 bg-indigo-600/10 rounded-full filter blur-3xl pointer-events-none"></div>

        <!-- Logo -->
        <div class="flex items-center gap-3 z-10">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg">
                <i data-lucide="shield" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <span class="font-bold text-white text-lg">CyberAI</span>
                <div class="text-xs text-slate-500">Security Operations Center</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="z-10 float-anim">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-medium mb-6">
                <span class="feature-dot w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                AI-Powered Security Monitoring
            </div>
            <h2 class="text-4xl font-bold text-white leading-tight mb-4">
                Next-Generation<br>
                <span style="background: linear-gradient(135deg, #5c7cfa, #748ffc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    Cyber Defense
                </span>
            </h2>
            <p class="text-slate-400 text-base leading-relaxed mb-8 max-w-md">
                Enterprise-grade security monitoring powered by Google Gemini AI. Detect threats, analyze incidents, and respond in real time.
            </p>

            <!-- Feature List -->
            <div class="space-y-3">
                <?php $features = [
                    ['shield-check', 'Real-time threat detection & response'],
                    ['brain-circuit', 'Gemini AI-powered incident analysis'],
                    ['activity', 'Live network monitoring & packet analysis'],
                    ['bar-chart-2', 'Advanced security analytics & reporting'],
                ]; foreach ($features as [$icon, $text]): ?>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="<?= $icon ?>" class="w-4 h-4 text-blue-400"></i>
                    </div>
                    <span class="text-sm text-slate-300"><?= $text ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="z-10 grid grid-cols-3 gap-4">
            <?php $stats = [['99.9%','Uptime'], ['< 1s','Detection Time'], ['AI', 'Powered']]; foreach ($stats as [$val, $lbl]): ?>
            <div class="glass-card rounded-xl p-4 text-center">
                <div class="text-xl font-bold text-white"><?= $val ?></div>
                <div class="text-xs text-slate-500 mt-1"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Panel — Login Form -->
    <div class="flex-1 flex items-center justify-center p-6 lg:p-12">
        <div class="glass-card rounded-2xl p-8 w-full max-w-md shadow-2xl" id="login-card">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="lg:hidden flex items-center justify-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-500 flex items-center justify-center">
                        <i data-lucide="shield" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="font-bold text-white text-lg">CyberAI</span>
                </div>
                <h1 class="text-2xl font-bold text-white">Welcome back</h1>
                <p class="text-slate-500 text-sm mt-1">Sign in to your security console</p>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl px-4 py-3 mb-6 text-sm">
                <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['timeout'])): ?>
            <div class="flex items-start gap-3 bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 rounded-xl px-4 py-3 mb-6 text-sm">
                <i data-lucide="clock" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                <span>Your session has expired. Please sign in again.</span>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" id="login-form">
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Username or Email</label>
                        <div class="relative">
                            <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input type="text" name="username" id="username"
                                   class="input-field w-full pl-10 pr-4 py-2.5 rounded-xl text-sm"
                                   placeholder="admin or admin@example.com"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   required autocomplete="username">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-slate-300">Password</label>
                        </div>
                        <div class="relative">
                            <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input type="password" name="password" id="password"
                                   class="input-field w-full pl-10 pr-12 py-2.5 rounded-xl text-sm"
                                   placeholder="Enter your password"
                                   required autocomplete="current-password">
                            <button type="button" onclick="togglePwd()" 
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
                                <i data-lucide="eye" class="w-4 h-4" id="pwd-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" class="w-4 h-4 rounded border-white/10 bg-surface-600 text-blue-500">
                            <span class="text-sm text-slate-400">Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-login w-full py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2 mt-2" id="login-btn">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Sign In to Console
                    </button>
                </div>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-6 p-4 bg-blue-500/5 border border-blue-500/15 rounded-xl">
                <div class="text-xs font-semibold text-blue-400 mb-2 flex items-center gap-1">
                    <i data-lucide="info" class="w-3 h-3"></i> Demo Credentials
                </div>
                <div class="space-y-1 text-xs text-slate-400 font-mono">
                    <div>Admin: <span class="text-slate-300">admin / Admin@123</span></div>
                    <div>Analyst: <span class="text-slate-300">analyst / Analyst@123</span></div>
                </div>
            </div>

            <p class="mt-6 text-center text-sm text-slate-600">
                Need an account? <a href="register.php" class="text-blue-400 hover:text-blue-300 transition-colors font-medium">Register here</a>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
        gsap.from('#login-card', { duration: 0.6, y: 30, opacity: 0, ease: 'power2.out' });

        function togglePwd() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('pwd-eye');
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
            lucide.createIcons();
        }

        document.getElementById('login-form').addEventListener('submit', () => {
            const btn = document.getElementById('login-btn');
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Signing in...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
