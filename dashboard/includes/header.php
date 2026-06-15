<?php
/**
 * Global HTML Header - included at top of every page
 */
$currentUser = auth()->getCurrentUser();
$pageTitle   = $pageTitle ?? 'Dashboard';
$activePage  = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CyberAI Platform - Enterprise AI-Powered Security Operations Center">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#f0f4ff',
                            100: '#dbe4ff',
                            200: '#bac8ff',
                            300: '#91a7ff',
                            400: '#748ffc',
                            500: '#5c7cfa',
                            600: '#4c6ef5',
                            700: '#4263eb',
                            800: '#3b5bdb',
                            900: '#364fc7',
                        },
                        surface: {
                            900: '#0a0e1a',
                            800: '#0d1225',
                            700: '#111827',
                            600: '#1a2236',
                            500: '#1e2a42',
                            400: '#253047',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">

    <style>
        * { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0d1225; }
        ::-webkit-scrollbar-thumb { background: #253047; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #4263eb; }
        .glass { background: rgba(13,18,37,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.06); }
        .glass-card { background: rgba(26,34,54,0.9); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.05); }
        .glow-blue { box-shadow: 0 0 20px rgba(66,99,235,0.15); }
        .glow-red { box-shadow: 0 0 20px rgba(239,68,68,0.15); }
        .gradient-text { background: linear-gradient(135deg, #5c7cfa, #748ffc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(66,99,235,0.15); border-left: 2px solid #4263eb; }
        .sidebar-link.active { color: #748ffc; }
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
        .alert-row:hover { background: rgba(255,255,255,0.03); }
        .btn-primary { background: linear-gradient(135deg, #4263eb, #5c7cfa); transition: all 0.2s ease; }
        .btn-primary:hover { background: linear-gradient(135deg, #3b5bdb, #4c6ef5); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(66,99,235,0.4); }
        .live-dot { animation: livePulse 2s infinite; }
        @keyframes livePulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }
        input, select, textarea { background: rgba(26,34,54,0.8) !important; border-color: rgba(255,255,255,0.08) !important; color: #e2e8f0 !important; }
        input:focus, select:focus, textarea:focus { border-color: #4263eb !important; box-shadow: 0 0 0 3px rgba(66,99,235,0.15) !important; }
        .modal-overlay { backdrop-filter: blur(4px); }
        .tag { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
    </style>
</head>
<body class="bg-surface-900 text-slate-200 min-h-screen antialiased">
