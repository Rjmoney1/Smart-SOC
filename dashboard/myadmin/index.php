<?php
/**
 * MyAdmin — Secure Database Management Gateway
 * Only users with 'admin' role can access phpMyAdmin.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// phpMyAdmin runs on port 8080 (docker-compose)
define('PMA_URL', 'http://localhost:8080');

$error   = '';
$success = '';

// ── Handle POST login ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = auth()->login($username, $password);

        if ($result['success']) {
            // Only admin role may proceed to DB management
            $user = auth()->getCurrentUser();
            if ($user['role'] !== ROLE_ADMIN) {
                auth()->logout();
                $error = 'Access denied. Database management requires administrator privileges.';
            } else {
                // Log the privileged access
                logActivity('myadmin', 'access', "Admin {$user['username']} accessed MyAdmin gateway", 'warning', $user['id']);
                // Redirect to phpMyAdmin
                header('Location: ' . PMA_URL);
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}

// ── If already logged in as admin, go straight through ────────────────────────
if (auth()->isLoggedIn()) {
    $user = auth()->getCurrentUser();
    if ($user['role'] === ROLE_ADMIN) {
        logActivity('myadmin', 'access', "Admin {$user['username']} accessed MyAdmin gateway", 'warning', $user['id']);
        header('Location: ' . PMA_URL);
        exit;
    }
    // Logged in but not admin — show error below
    $error = 'Access denied. Database management requires administrator privileges.';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyAdmin — Database Access · <?= APP_NAME ?></title>
    <meta name="description" content="Secure gateway to the CyberAI Platform database management interface. Administrator credentials required.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }

        body {
            background: #060a14;
            min-height: 100vh;
        }

        /* Animated grid */
        .grid-bg {
            background-image:
                linear-gradient(rgba(139,92,246,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(139,92,246,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
        }

        /* Radial glow */
        .radial-glow {
            background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(139,92,246,0.12) 0%, transparent 70%);
        }

        /* Glass card */
        .glass-card {
            background: rgba(10, 14, 28, 0.92);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(139,92,246,0.15);
            box-shadow: 0 0 0 1px rgba(0,0,0,0.4), 0 32px 64px rgba(0,0,0,0.5);
        }

        /* Input */
        .db-input {
            background: rgba(20, 25, 48, 0.8) !important;
            border: 1px solid rgba(139,92,246,0.2) !important;
            color: #e2e8f0 !important;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        .db-input::placeholder { color: rgba(148,163,184,0.4) !important; }
        .db-input:focus {
            border-color: #8b5cf6 !important;
            box-shadow: 0 0 0 3px rgba(139,92,246,0.2) !important;
            outline: none;
        }

        /* Button */
        .btn-db {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6, #6d28d9);
            background-size: 200% 200%;
            transition: all 0.3s ease;
        }
        .btn-db:hover {
            background-position: right center;
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(139,92,246,0.45);
        }
        .btn-db:active { transform: translateY(0); }

        /* Warning badge */
        .warning-badge {
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.2);
        }

        /* Error */
        .error-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.2);
        }

        /* Lock animation */
        @keyframes lock-shake {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(-4deg); }
            40% { transform: rotate(4deg); }
            60% { transform: rotate(-2deg); }
            80% { transform: rotate(2deg); }
        }
        .lock-shake { animation: lock-shake 0.5s ease-in-out; }

        /* Floating particles */
        @keyframes particle-float {
            0%   { transform: translateY(0px) scale(1); opacity: 0.6; }
            50%  { transform: translateY(-20px) scale(1.05); opacity: 1; }
            100% { transform: translateY(0px) scale(1); opacity: 0.6; }
        }
        .particle { animation: particle-float var(--dur, 6s) ease-in-out infinite; animation-delay: var(--delay, 0s); }

        /* Pulse ring */
        @keyframes ping-slow { 75%, 100% { transform: scale(2); opacity: 0; } }
        .ping-slow { animation: ping-slow 3s cubic-bezier(0,0,0.2,1) infinite; }

        /* Scan line */
        @keyframes scan {
            0%   { transform: translateY(-100%); }
            100% { transform: translateY(400%); }
        }
        .scan-line { animation: scan 3s linear infinite; }

        /* Typing cursor */
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0;} }
        .cursor { animation: blink 1s step-end infinite; }

        /* Glitch text */
        @keyframes glitch {
            0%,100%  { clip-path: inset(50% 0 30% 0); transform: translate(-2px, -1px); }
            20%      { clip-path: inset(15% 0 65% 0); transform: translate(2px, 1px); }
            40%      { clip-path: inset(80% 0 5% 0); transform: translate(-1px, 2px); }
            60%      { clip-path: inset(40% 0 40% 0); transform: translate(1px,-1px); }
            80%      { clip-path: inset(70% 0 10% 0); transform: translate(-2px,0); }
        }

        /* Hex grid bg item */
        .hex-glow {
            background: rgba(139,92,246,0.05);
            border: 1px solid rgba(139,92,246,0.1);
        }
    </style>
</head>
<body class="grid-bg flex items-center justify-center p-4">

    <!-- Background radial glow -->
    <div class="fixed inset-0 radial-glow pointer-events-none"></div>

    <!-- Floating particles -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="particle absolute w-1 h-1 rounded-full bg-violet-500/40" style="top:20%;left:10%;--dur:7s;--delay:0s;"></div>
        <div class="particle absolute w-1.5 h-1.5 rounded-full bg-purple-400/30" style="top:60%;left:85%;--dur:9s;--delay:2s;"></div>
        <div class="particle absolute w-1 h-1 rounded-full bg-violet-600/50" style="top:80%;left:30%;--dur:6s;--delay:1s;"></div>
        <div class="particle absolute w-2 h-2 rounded-full bg-purple-500/20" style="top:40%;left:70%;--dur:11s;--delay:3s;"></div>
        <div class="particle absolute w-1 h-1 rounded-full bg-violet-400/40" style="top:10%;left:55%;--dur:8s;--delay:0.5s;"></div>
        <div class="particle absolute w-1 h-1 rounded-full bg-purple-300/30" style="top:70%;left:20%;--dur:10s;--delay:4s;"></div>
    </div>

    <!-- Main Container -->
    <div class="relative w-full max-w-5xl flex flex-col lg:flex-row gap-6 z-10" id="main-container">

        <!-- ── Left Info Panel ── -->
        <div class="hidden lg:flex flex-col gap-6 w-80 flex-shrink-0 pt-4" id="info-panel">

            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-600 to-purple-500 flex items-center justify-center shadow-lg shadow-violet-900/50">
                        <i data-lucide="database" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-red-500 border-2 border-[#060a14] flex items-center justify-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                    </span>
                </div>
                <div>
                    <div class="font-bold text-white text-sm">CyberAI Platform</div>
                    <div class="text-xs text-slate-500">Database Management</div>
                </div>
            </div>

            <!-- DB Status Card -->
            <div class="glass-card rounded-2xl p-5 relative overflow-hidden">
                <!-- Scan line effect -->
                <div class="absolute inset-0 overflow-hidden rounded-2xl">
                    <div class="scan-line absolute w-full h-0.5 bg-gradient-to-r from-transparent via-violet-500/30 to-transparent opacity-50"></div>
                </div>

                <div class="flex items-center gap-2 mb-4">
                    <div class="relative flex h-2 w-2">
                        <span class="ping-slow absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </div>
                    <span class="text-xs font-semibold text-slate-300">MySQL Server Online</span>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500">Database</span>
                        <span class="text-violet-400 font-mono">cyber_ai_platform</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500">Engine</span>
                        <span class="text-slate-300 font-mono">MySQL 8.0</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500">Interface</span>
                        <span class="text-slate-300 font-mono">phpMyAdmin</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500">Port</span>
                        <span class="text-slate-300 font-mono">3306</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-white/5">
                    <div class="text-xs text-slate-600">Access restricted to administrators</div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="warning-badge rounded-xl p-4">
                <div class="flex items-start gap-2.5">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <div class="text-xs font-semibold text-amber-400 mb-1">Restricted Access</div>
                        <div class="text-xs text-slate-400 leading-relaxed">
                            This gateway provides direct access to the platform database. All sessions are logged and monitored.
                        </div>
                    </div>
                </div>
            </div>

            <!-- What you can do -->
            <div class="glass-card rounded-2xl p-5">
                <div class="text-xs font-semibold text-slate-400 mb-3 flex items-center gap-2">
                    <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                    Database Access Includes
                </div>
                <div class="space-y-2.5">
                    <?php $features = [
                        ['table-2', 'Browse & edit all tables'],
                        ['terminal', 'Run SQL queries'],
                        ['download', 'Export / import data'],
                        ['settings', 'Manage users & privileges'],
                        ['bar-chart-2', 'View performance metrics'],
                    ]; foreach ($features as [$icon, $label]): ?>
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-md bg-violet-500/10 border border-violet-500/20 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="<?= $icon ?>" class="w-3 h-3 text-violet-400"></i>
                        </div>
                        <span class="text-xs text-slate-400"><?= $label ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Right — Login Form ── -->
        <div class="flex-1 flex items-center justify-center">
            <div class="glass-card rounded-2xl p-8 w-full max-w-md" id="login-card">

                <!-- Header -->
                <div class="text-center mb-8">
                    <!-- Mobile logo -->
                    <div class="lg:hidden flex items-center justify-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-600 to-purple-500 flex items-center justify-center shadow-lg shadow-violet-900/50">
                            <i data-lucide="database" class="w-5 h-5 text-white"></i>
                        </div>
                        <span class="font-bold text-white text-lg">CyberAI</span>
                    </div>

                    <!-- Lock icon with glow -->
                    <div class="relative inline-flex items-center justify-center mb-5">
                        <div class="absolute w-16 h-16 rounded-full bg-violet-600/20 blur-xl"></div>
                        <div class="relative w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-700 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-900/60" id="lock-icon">
                            <i data-lucide="lock-keyhole" class="w-6 h-6 text-white"></i>
                        </div>
                    </div>

                    <h1 class="text-2xl font-bold text-white mb-1">Database Access</h1>
                    <p class="text-slate-500 text-sm">Authenticate with your <span class="text-violet-400 font-medium">admin</span> credentials</p>
                </div>

                <!-- Error Alert -->
                <?php if ($error): ?>
                <div class="error-box flex items-start gap-3 rounded-xl px-4 py-3 mb-6 text-sm" id="error-alert">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0"></i>
                    <span class="text-red-400"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- Session timeout -->
                <?php if (isset($_GET['timeout'])): ?>
                <div class="flex items-start gap-3 bg-amber-500/8 border border-amber-500/20 rounded-xl px-4 py-3 mb-6 text-sm">
                    <i data-lucide="clock" class="w-4 h-4 text-amber-400 mt-0.5 flex-shrink-0"></i>
                    <span class="text-amber-400">Session expired. Please re-authenticate.</span>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="" id="myadmin-form" autocomplete="on">

                    <div class="space-y-5">
                        <!-- Username -->
                        <div>
                            <label for="username" class="block text-sm font-medium text-slate-300 mb-2">Admin Username or Email</label>
                            <div class="relative">
                                <i data-lucide="user-cog" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                                <input
                                    type="text" name="username" id="username"
                                    class="db-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm"
                                    placeholder="admin"
                                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                    required autocomplete="username"
                                    autofocus>
                            </div>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="db-password" class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                            <div class="relative">
                                <i data-lucide="key-round" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                                <input
                                    type="password" name="password" id="db-password"
                                    class="db-input w-full pl-10 pr-12 py-2.5 rounded-xl text-sm"
                                    placeholder="••••••••"
                                    required autocomplete="current-password">
                                <button type="button" onclick="togglePwd()" id="toggle-pwd-btn"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                                        aria-label="Toggle password visibility">
                                    <i data-lucide="eye" class="w-4 h-4" id="pwd-eye-icon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Admin Only Warning -->
                        <div class="warning-badge rounded-xl px-4 py-3 flex items-center gap-3">
                            <i data-lucide="shield" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
                            <span class="text-xs text-amber-400/80">
                                Only users with <strong class="text-amber-400">admin</strong> role can access database management.
                            </span>
                        </div>

                        <!-- Submit -->
                        <button type="submit" id="submit-btn"
                                class="btn-db w-full py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                            <i data-lucide="database" class="w-4 h-4" id="btn-icon"></i>
                            <span id="btn-text">Authenticate & Open Database</span>
                        </button>
                    </div>
                </form>

                <!-- Divider -->
                <div class="my-6 flex items-center gap-3">
                    <div class="flex-1 h-px bg-white/5"></div>
                    <span class="text-xs text-slate-600">or</span>
                    <div class="flex-1 h-px bg-white/5"></div>
                </div>

                <!-- Quick links -->
                <div class="flex gap-3">
                    <a href="<?= APP_URL ?>/dashboard/index.php"
                       class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl border border-white/8 text-slate-400 hover:text-white hover:border-white/15 text-xs font-medium transition-all duration-200">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
                        Back to Dashboard
                    </a>
                    <a href="<?= APP_URL ?>/dashboard/login.php"
                       class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl border border-white/8 text-slate-400 hover:text-white hover:border-white/15 text-xs font-medium transition-all duration-200">
                        <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                        Main Login
                    </a>
                </div>

                <!-- Activity log notice -->
                <p class="mt-6 text-center text-xs text-slate-700">
                    <i data-lucide="eye" class="inline w-3 h-3 mb-0.5"></i>
                    All database access is logged and audited
                </p>
            </div>
        </div>
    </div>

    <script>
        // Init icons
        lucide.createIcons();

        // Entry animations
        gsap.from('#login-card', { duration: 0.7, y: 30, opacity: 0, ease: 'power3.out' });
        gsap.from('#info-panel > *', {
            duration: 0.6, y: 20, opacity: 0, ease: 'power2.out',
            stagger: 0.1, delay: 0.15
        });

        <?php if ($error): ?>
        // Shake lock on error
        const lockEl = document.getElementById('lock-icon');
        lockEl.classList.add('lock-shake');
        lockEl.addEventListener('animationend', () => lockEl.classList.remove('lock-shake'));
        <?php endif; ?>

        // Toggle password visibility
        function togglePwd() {
            const input = document.getElementById('db-password');
            const icon  = document.getElementById('pwd-eye-icon');
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
            lucide.createIcons();
        }

        // Submit loader
        document.getElementById('myadmin-form').addEventListener('submit', function(e) {
            const btn    = document.getElementById('submit-btn');
            const btnTxt = document.getElementById('btn-text');

            btn.disabled = true;
            btn.style.opacity = '0.8';
            btnTxt.textContent = 'Authenticating…';

            const spinnerSVG = `<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>`;

            document.getElementById('btn-icon').outerHTML = spinnerSVG;
        });

        // Focus animation on inputs
        document.querySelectorAll('.db-input').forEach(input => {
            input.addEventListener('focus', () => {
                input.closest('.relative')?.querySelector('[data-lucide]')
                    ?.setAttribute('style', 'color: #8b5cf6');
            });
            input.addEventListener('blur', () => {
                input.closest('.relative')?.querySelector('[data-lucide]')
                    ?.removeAttribute('style');
            });
        });
    </script>
</body>
</html>
