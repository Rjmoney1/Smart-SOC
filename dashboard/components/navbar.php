<?php
/**
 * Top Navbar Component
 */
?>
<header class="fixed top-0 right-0 left-0 lg:left-64 z-30 glass border-b border-white/5 h-16 flex items-center px-6 gap-4">
    <!-- Mobile Menu Toggle -->
    <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/5">
        <i data-lucide="menu" class="w-5 h-5"></i>
    </button>

    <!-- Page Title -->
    <div class="flex items-center gap-3">
        <h1 class="text-base font-semibold text-white"><?= htmlspecialchars($pageTitle) ?></h1>
        <div class="hidden sm:flex items-center gap-1 text-xs text-slate-500">
            <i data-lucide="chevron-right" class="w-3 h-3"></i>
            <span class="capitalize"><?= htmlspecialchars($activePage ?: 'overview') ?></span>
        </div>
    </div>

    <div class="flex-1"></div>

    <!-- Search -->
    <div class="hidden md:flex items-center gap-2 bg-surface-600/50 border border-white/5 rounded-lg px-3 py-1.5 w-64 hover:border-brand-500/30 transition-colors">
        <i data-lucide="search" class="w-4 h-4 text-slate-500"></i>
        <input type="text" id="global-search" placeholder="Search logs, alerts..." 
               class="bg-transparent border-none outline-none text-sm text-slate-300 placeholder-slate-600 flex-1 !bg-transparent !border-0 !shadow-none">
    </div>

    <!-- Real-time Clock -->
    <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500 font-mono">
        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
        <span id="live-clock"></span>
    </div>

    <!-- Notifications -->
    <div class="relative">
        <button id="notif-btn" class="relative p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
                onclick="toggleNotifications()">
            <i data-lucide="bell" class="w-5 h-5"></i>
            <span id="notif-badge" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full hidden"></span>
        </button>

        <!-- Notification Dropdown -->
        <div id="notif-dropdown" class="hidden absolute right-0 top-full mt-2 w-80 glass-card rounded-xl shadow-2xl border border-white/5 overflow-hidden z-50">
            <div class="px-4 py-3 border-b border-white/5 flex items-center justify-between">
                <span class="text-sm font-semibold text-white">Notifications</span>
                <button class="text-xs text-brand-400 hover:text-brand-300" onclick="markAllRead()">Mark all read</button>
            </div>
            <div id="notif-list" class="max-h-72 overflow-y-auto divide-y divide-white/5">
                <div class="px-4 py-8 text-center text-slate-500 text-sm">Loading...</div>
            </div>
            <div class="px-4 py-3 border-t border-white/5 text-center">
                <a href="<?= APP_URL ?>/dashboard/alerts.php" class="text-xs text-brand-400 hover:text-brand-300">View all alerts →</a>
            </div>
        </div>
    </div>

    <!-- Theme / Profile -->
    <div class="flex items-center gap-2">
        <a href="<?= APP_URL ?>/dashboard/profile.php" class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-semibold text-sm hover:scale-105 transition-transform">
            <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'] ?? 'U', 0, 1)) ?>
        </a>
    </div>
</header>

<!-- Toast Container -->
<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-3 w-96 max-w-[calc(100vw-2rem)] pointer-events-none"></div>

<script>
// Live Clock
function updateClock() {
    const el = document.getElementById('live-clock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-US', { hour12: false });
}
updateClock();
setInterval(updateClock, 1000);

// Notifications
function toggleNotifications() {
    const dd = document.getElementById('notif-dropdown');
    dd.classList.toggle('hidden');
    if (!dd.classList.contains('hidden')) loadNotifications();
}

function loadNotifications() {
    fetch('<?= APP_URL ?>/dashboard/api/get_alerts.php?limit=5&status=open')
        .then(r => r.json())
        .then(d => {
            const list = document.getElementById('notif-list');
            const badge = document.getElementById('notif-badge');
            if (!d.alerts || d.alerts.length === 0) {
                list.innerHTML = '<div class="px-4 py-8 text-center text-slate-500 text-sm">No new alerts</div>';
                badge.classList.add('hidden');
                return;
            }
            badge.classList.remove('hidden');
            list.innerHTML = d.alerts.map(a => `
                <div class="px-4 py-3 hover:bg-white/3 transition-colors cursor-pointer" onclick="window.location='<?= APP_URL ?>/dashboard/alerts.php?id=${a.id}'">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 ${severityDot(a.severity)}"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-slate-200 font-medium truncate">${escHtml(a.title)}</div>
                            <div class="text-xs text-slate-500 mt-0.5">${escHtml(a.source_ip || 'Unknown')} · ${timeAgo(a.created_at)}</div>
                        </div>
                    </div>
                </div>
            `).join('');
        }).catch(() => {});
}

// Check if open alerts exist to show badge on initial load
function checkInitialNotifications() {
    fetch('<?= APP_URL ?>/dashboard/api/get_alerts.php?count=1')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('notif-badge');
            if (badge && d.open_count > 0) {
                badge.classList.remove('hidden');
            }
        }).catch(() => {});
}
checkInitialNotifications();

function severityDot(s) {
    const m = { critical: 'bg-red-500', high: 'bg-orange-400', medium: 'bg-yellow-400', low: 'bg-blue-400', info: 'bg-slate-400' };
    return m[s] || 'bg-slate-400';
}

function escHtml(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
function timeAgo(dt) {
    const diff = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function markAllRead() {
    document.getElementById('notif-badge').classList.add('hidden');
    document.getElementById('notif-dropdown').classList.add('hidden');
}

document.addEventListener('click', e => {
    const btn = document.getElementById('notif-btn');
    const dd  = document.getElementById('notif-dropdown');
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) dd.classList.add('hidden');
});

// --- Sound Synthesizer (Chime) ---
function playSecurityChime(severity) {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        
        const playTone = (freq, type, startTime, duration, vol) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.type = type;
            osc.frequency.setValueAtTime(freq, startTime);
            
            gain.gain.setValueAtTime(vol, startTime);
            gain.gain.exponentialRampToValueAtTime(0.00001, startTime + duration);
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.start(startTime);
            osc.stop(startTime + duration);
        };
        
        const now = ctx.currentTime;
        if (severity === 'critical') {
            playTone(880, 'triangle', now, 0.4, 0.15); // A5
            playTone(1320, 'sine', now + 0.1, 0.6, 0.1); // E6
        } else if (severity === 'high') {
            playTone(523.25, 'sine', now, 0.3, 0.15); // C5
            playTone(783.99, 'sine', now + 0.12, 0.5, 0.1); // G5
        } else {
            playTone(523.25, 'sine', now, 0.4, 0.10); // C5
            playTone(659.25, 'sine', now + 0.15, 0.5, 0.05); // E5
        }
    } catch (e) {
        console.warn('Web Audio API chime blocked or unsupported:', e);
    }
}

// --- Dynamic Toast System ---
function showNotificationToast(alert) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    // Toast element
    const toast = document.createElement('div');
    
    // Severity border classes
    const borderColors = {
        critical: 'border-l-4 border-l-red-500',
        high:     'border-l-4 border-l-orange-500',
        medium:   'border-l-4 border-l-yellow-500',
        low:      'border-l-4 border-l-blue-500',
        info:     'border-l-4 border-l-slate-500'
    };
    
    const colors = {
        critical: 'text-red-400 bg-red-500/10 border-red-500/20',
        high:     'text-orange-400 bg-orange-500/10 border-orange-500/20',
        medium:   'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
        low:      'text-blue-400 bg-blue-500/10 border-blue-500/20',
        info:     'text-slate-400 bg-slate-500/10 border-slate-500/20'
    };

    const borderCls = borderColors[alert.severity] || 'border-l-4 border-l-slate-500';
    const tagCls = colors[alert.severity] || 'text-slate-400 bg-slate-500/10';

    toast.className = `glass-card ${borderCls} rounded-2xl p-4 shadow-2xl flex gap-3.5 animate-slide-up relative overflow-hidden pointer-events-auto cursor-pointer max-w-sm transition-all duration-300 hover:scale-[1.02]`;
    toast.onclick = () => {
        window.location.href = `<?= APP_URL ?>/dashboard/alerts.php?id=${alert.id}`;
    };

    const alertIcon = alert.severity === 'critical' || alert.severity === 'high' ? 'shield-alert' : 'bell';

    toast.innerHTML = `
        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-xl bg-white/5 border border-white/5 text-slate-300">
            <i data-lucide="${alertIcon}" class="w-5 h-5 ${alert.severity === 'critical' ? 'text-red-400' : 'text-slate-300'}"></i>
        </div>
        <div class="flex-1 min-w-0 pr-6">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border ${tagCls}">${alert.severity}</span>
                <span class="text-[10px] font-mono text-slate-500">${escHtml(alert.source_ip || '0.0.0.0')}</span>
            </div>
            <h5 class="text-xs font-semibold text-white truncate">${escHtml(alert.title)}</h5>
            <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-2">${escHtml(alert.description || '')}</p>
        </div>
        <button class="absolute top-3 right-3 p-1 rounded-lg text-slate-500 hover:text-white hover:bg-white/5 transition-colors" onclick="event.stopPropagation(); this.closest('.glass-card').remove();">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </button>
    `;

    container.appendChild(toast);
    lucide.createIcons({ attrs: { class: 'lucide' } });

    // Play chime sound
    playSecurityChime(alert.severity);

    // Auto-dismiss after 6 seconds with fade out animation
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 6000);
}

// --- SSE Event Stream Setup ---
let sseSource = null;
let lastAlertId = parseInt(localStorage.getItem('last_seen_alert_id') || '0');

function connectSSE() {
    if (sseSource) {
        sseSource.close();
    }
    
    // Connect to endpoint
    const url = `<?= APP_URL ?>/dashboard/api/sse_alerts.php?last_id=${lastAlertId}`;
    sseSource = new EventSource(url);

    sseSource.addEventListener('new_alert', e => {
        try {
            const alert = JSON.parse(e.data);
            
            // Deduplicate and update ID
            if (alert.id > lastAlertId) {
                lastAlertId = alert.id;
                localStorage.setItem('last_seen_alert_id', lastAlertId);
                
                // Show notification bell badge
                const badge = document.getElementById('notif-badge');
                if (badge) badge.classList.remove('hidden');

                // Trigger toast notification
                showNotificationToast(alert);

                // Dispatch global browser event for page-level scripts
                window.dispatchEvent(new CustomEvent('new-security-alert', { detail: alert }));
                
                // If notification dropdown is open, reload notifications list
                const dd = document.getElementById('notif-dropdown');
                if (dd && !dd.classList.contains('hidden')) {
                    loadNotifications();
                }
            }
        } catch (err) {
            console.error('Error parsing SSE event data:', err);
        }
    });

    sseSource.onerror = () => {
        console.warn('SSE connection lost. Attempting reconnection...');
        sseSource.close();
        // Retry connection in 5 seconds
        setTimeout(connectSSE, 5000);
    };
}

// Start connection
connectSSE();
</script>
