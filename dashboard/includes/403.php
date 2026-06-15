<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Access Denied · <?= defined('APP_NAME') ? APP_NAME : 'CyberAI Platform' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #0a0e1a; }
        .grid-bg {
            background-image:
                linear-gradient(rgba(239,68,68,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(239,68,68,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
        }
        .glass-card {
            background: rgba(13,18,37,0.92);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(239,68,68,0.12);
            box-shadow: 0 0 0 1px rgba(0,0,0,0.4), 0 32px 64px rgba(0,0,0,0.5);
        }
        @keyframes pulse-ring {
            0%   { transform: scale(1);   opacity: 0.6; }
            50%  { transform: scale(1.15);opacity: 0.2; }
            100% { transform: scale(1);   opacity: 0.6; }
        }
        .pulse-ring { animation: pulse-ring 2.5s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
        .float { animation: float 5s ease-in-out infinite; }
    </style>
</head>
<body class="grid-bg min-h-screen flex items-center justify-center p-6">

    <!-- Radial glow -->
    <div class="fixed inset-0 pointer-events-none" style="background:radial-gradient(ellipse 55% 40% at 50% 30%,rgba(239,68,68,0.08) 0%,transparent 70%)"></div>

    <div class="glass-card rounded-2xl p-10 w-full max-w-lg text-center relative" id="card">

        <!-- Animated lock icon -->
        <div class="relative inline-flex items-center justify-center mb-6 float">
            <div class="absolute w-24 h-24 rounded-full bg-red-500/10 pulse-ring"></div>
            <div class="absolute w-16 h-16 rounded-full bg-red-500/15"></div>
            <div class="relative w-20 h-20 rounded-2xl bg-gradient-to-br from-red-700 to-red-500 flex items-center justify-center shadow-lg shadow-red-900/50">
                <i data-lucide="shield-x" class="w-9 h-9 text-white"></i>
            </div>
        </div>

        <!-- Code -->
        <div class="text-7xl font-black text-transparent mb-2" style="background:linear-gradient(135deg,#ef4444,#f97316);-webkit-background-clip:text;background-clip:text;">
            403
        </div>

        <h1 class="text-xl font-bold text-white mb-2">Access Denied</h1>
        <p class="text-slate-400 text-sm leading-relaxed mb-8 max-w-sm mx-auto">
            You don't have the required permissions to view this page.
            Contact your administrator if you believe this is an error.
        </p>

        <!-- Info box -->
        <div class="flex items-start gap-3 bg-red-500/8 border border-red-500/20 rounded-xl px-4 py-3 text-sm text-left mb-8">
            <i data-lucide="info" class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5"></i>
            <span class="text-red-400/80 text-xs leading-relaxed">
                Your current role — <strong class="text-red-400">
                    <?= htmlspecialchars($_SESSION['role'] ?? 'guest') ?>
                </strong> — does not have permission to access this resource.
            </span>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="javascript:history.back()"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl border border-white/10 text-slate-400 hover:text-white hover:border-white/20 text-sm font-medium transition-all duration-200">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Go Back
            </a>
            <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/dashboard/index.php"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl bg-gradient-to-r from-brand-700 to-brand-500 text-white text-sm font-semibold transition-all duration-200 hover:opacity-90 hover:-translate-y-0.5"
               style="background:linear-gradient(135deg,#4263eb,#5c7cfa)">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                Dashboard
            </a>
        </div>

        <!-- Divider -->
        <div class="mt-8 pt-6 border-t border-white/5 flex items-center justify-center gap-2 text-xs text-slate-600">
            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
            This access attempt has been logged.
        </div>
    </div>

    <script>
        lucide.createIcons();
        gsap.from('#card', { duration: 0.6, y: 30, opacity: 0, ease: 'power3.out' });
    </script>
</body>
</html>
