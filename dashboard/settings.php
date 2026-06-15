<?php
/**
 * Platform Settings — Full Limits Control
 * Admin-only: configure limits for every platform operation
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireRole(ROLE_ADMIN);

$pageTitle  = 'Platform Settings';
$activePage = 'settings';

$message = '';
$error   = '';

// ── Handle POST saves ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $raw = $_POST;
        unset($raw['action']);

        // Sanitise & cast every field
        $intFields = [
            'mail_port','log_retention_days','max_log_size_mb',
            'alert_threshold_critical','alert_threshold_high',
            'alert_threshold_medium','alert_threshold_low',
            'rate_limit_login_attempts','rate_limit_lockout_minutes',
            'rate_limit_api_per_minute','rate_limit_api_per_hour',
            'session_lifetime_minutes','max_sessions_per_user',
            'auto_block_threshold','auto_block_duration_hours',
            'packet_capture_limit','packet_capture_interval_sec',
            'port_scan_threshold','port_scan_window_sec',
            'ai_max_reports_per_day','ai_max_tokens','ai_analysis_interval_min',
            'notif_max_alerts_per_hour','notif_batch_size','notif_cooldown_sec',
            'sim_packets_per_sec','sim_attack_interval_sec','sim_max_concurrent',
            'db_query_timeout_sec','db_max_connections','mem_limit_mb',
            'max_upload_mb','max_blocked_ips',
        ];
        $boolFields = [
            'alert_email_enabled','alert_telegram_enabled',
            'auto_block_enabled','packet_capture_enabled',
            'ai_auto_analysis','sim_enabled','notif_email_digest',
        ];

        $settings = [];
        // Text / password / select fields
        foreach (['gemini_api_key','gemini_model','ai_provider',
                  'mail_host','mail_user','mail_pass','mail_from',
                  'telegram_bot_token','telegram_chat_id',
                  'ollama_api_url','ollama_model'] as $f) {
            $settings[$f] = trim($raw[$f] ?? '');
        }
        // Integer fields
        foreach ($intFields as $f) {
            $settings[$f] = max(0, (int)($raw[$f] ?? 0));
        }
        // Boolean fields
        foreach ($boolFields as $f) {
            $settings[$f] = isset($raw[$f]) ? '1' : '0';
        }

        foreach ($settings as $key => $value) {
            $exists = db()->fetch("SELECT id FROM settings WHERE setting_key=?", [$key]);
            if ($exists) {
                db()->update('settings', ['setting_value'=>$value,'updated_at'=>date('Y-m-d H:i:s')], 'setting_key=?', [$key]);
            } else {
                db()->insert('settings', ['setting_key'=>$key,'setting_value'=>$value,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            }
        }
        $message = 'All settings saved successfully.';
        logActivity('system', 'settings_update', 'Platform settings updated by ' . auth()->getCurrentUser()['username'], 'info');
    }

    if ($action === 'test_gemini') {
        $result = callGeminiApi('Say "Gemini API connection successful!" in exactly those words.');
        header('Content-Type: application/json');
        echo json_encode($result['success']
            ? ['success'=>true, 'message'=>'Connected: '.$result['text']]
            : ['success'=>false,'message'=>$result['message']]);
        exit;
    }

    if ($action === 'reset_section') {
        // Reset specific section to defaults — handled per-section
    }
}

// ── Load all settings from DB ──────────────────────────────────────────────────
$settingsRows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
$cfg = array_column($settingsRows, 'setting_value', 'setting_key');

// ── Helper: get setting with default ──────────────────────────────────────────
function s(string $key, $default = '') {
    global $cfg;
    return $cfg[$key] ?? $default;
}
function si(string $key, int $default = 0): int {
    return (int) s($key, $default);
}
function sb(string $key): bool {
    return s($key, '0') === '1';
}

include __DIR__ . '/includes/header.php';
?>

<div class="flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>

    <div class="flex-1 lg:ml-64">
        <?php include __DIR__ . '/components/navbar.php'; ?>

        <main class="pt-16 min-h-screen">
            <div class="p-6 max-w-5xl space-y-2">

                <!-- Page Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <i data-lucide="sliders-horizontal" class="w-5 h-5 text-brand-400"></i>
                            Platform Settings &amp; Limits
                        </h2>
                        <p class="text-slate-500 text-sm mt-0.5">Full control over every operational limit and threshold</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-2 text-xs text-slate-600">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        Last saved: <?= s('_last_saved') ?: 'Never' ?>
                    </div>
                </div>

                <!-- Alert Banner -->
                <?php if ($message): ?>
                <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/20 text-green-400 rounded-xl px-4 py-3 text-sm mb-4 animate-in" id="save-banner">
                    <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="flex gap-1 p-1 bg-surface-800/60 border border-white/5 rounded-xl mb-6 flex-wrap">
                    <?php $tabs = [
                        ['id'=>'auth',      'icon'=>'lock',             'label'=>'Auth & Sessions'],
                        ['id'=>'alerts',    'icon'=>'bell-ring',        'label'=>'Alert Limits'],
                        ['id'=>'network',   'icon'=>'network',          'label'=>'Network'],
                        ['id'=>'ai',        'icon'=>'sparkles',         'label'=>'AI Engine'],
                        ['id'=>'notif',     'icon'=>'send',             'label'=>'Notifications'],
                        ['id'=>'data',      'icon'=>'database',         'label'=>'Data & Logs'],
                        ['id'=>'simulator', 'icon'=>'activity',         'label'=>'Simulator'],
                        ['id'=>'system',    'icon'=>'cpu',              'label'=>'System'],
                        ['id'=>'integrations','icon'=>'plug',           'label'=>'Integrations'],
                    ]; foreach ($tabs as $i => $tab): ?>
                    <button type="button" onclick="switchTab('<?= $tab['id'] ?>')"
                            id="tab-btn-<?= $tab['id'] ?>"
                            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200
                                   <?= $i === 0 ? 'bg-brand-600/20 text-brand-400 border border-brand-500/20' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' ?>">
                        <i data-lucide="<?= $tab['icon'] ?>" class="w-3.5 h-3.5"></i>
                        <?= $tab['label'] ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- FORM wraps all tabs -->
                <form method="POST" action="" id="settings-form">
                    <input type="hidden" name="action" value="save_settings">

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: AUTH & SESSIONS                                -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-auth" class="tab-content space-y-4">

                        <!-- Login Rate Limiting -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-red-500/10 border border-red-500/20 flex items-center justify-center">
                                    <i data-lucide="shield-x" class="w-4 h-4 text-red-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Login Rate Limiting</h3>
                                    <p class="text-xs text-slate-500">Brute-force protection thresholds</p>
                                </div>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $authLimitFields = [
                                    ['rate_limit_login_attempts', 'Max Failed Attempts', 5, 1, 50, 'Attempts before lockout'],
                                    ['rate_limit_lockout_minutes','Lockout Duration (min)', 15, 1, 1440, 'Minutes to lock account'],
                                    ['rate_limit_api_per_minute', 'API Requests / Minute', 60, 1, 9999, 'Per-user API rate limit'],
                                    ['rate_limit_api_per_hour',   'API Requests / Hour',  500, 1, 99999,'Hourly API cap'],
                                    ['max_sessions_per_user',     'Max Sessions / User',   3, 1, 20,    'Concurrent sessions'],
                                    ['session_lifetime_minutes',  'Session Lifetime (min)',120, 5, 10080,'Idle timeout'],
                                ]; foreach ($authLimitFields as [$name, $label, $default, $min, $max, $hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- IP Auto-Block -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-orange-500/10 border border-orange-500/20 flex items-center justify-center">
                                        <i data-lucide="ban" class="w-4 h-4 text-orange-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-white">Auto IP Blocking</h3>
                                        <p class="text-xs text-slate-500">Automatic threat actor blocking</p>
                                    </div>
                                </div>
                                <label class="toggle-wrap">
                                    <input type="checkbox" name="auto_block_enabled" <?= sb('auto_block_enabled') ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                    <span class="text-xs text-slate-400">Enabled</span>
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $blockFields = [
                                    ['auto_block_threshold',      'Alerts to Trigger Block', 10,  1,  500, 'Alerts from same IP'],
                                    ['auto_block_duration_hours', 'Block Duration (hours)',   24,  1,  720, 'Set 0 for permanent'],
                                    ['max_blocked_ips',           'Max Blocked IPs',         500, 10,50000,'Hard cap on block list'],
                                ]; foreach ($blockFields as [$name, $label, $default, $min, $max, $hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: ALERT LIMITS                                   -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-alerts" class="tab-content space-y-4 hidden">

                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-red-500/10 border border-red-500/20 flex items-center justify-center">
                                    <i data-lucide="bell-ring" class="w-4 h-4 text-red-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Alert Severity Thresholds</h3>
                                    <p class="text-xs text-slate-500">Risk scores that trigger each severity level (0–100)</p>
                                </div>
                            </div>
                            <div class="p-6 space-y-5">
                                <!-- Visual severity bar -->
                                <div class="flex items-center gap-1 h-3 rounded-full overflow-hidden">
                                    <div class="flex-1 bg-slate-600/40 rounded-l-full h-full relative group" title="Info: 0+">
                                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-slate-400">INFO</span>
                                    </div>
                                    <div class="flex-1 bg-blue-500/40 h-full relative group" title="Low">
                                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-blue-300">LOW</span>
                                    </div>
                                    <div class="flex-1 bg-yellow-500/40 h-full relative group">
                                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-yellow-300">MED</span>
                                    </div>
                                    <div class="flex-1 bg-orange-500/40 h-full relative group">
                                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-orange-300">HIGH</span>
                                    </div>
                                    <div class="flex-1 bg-red-500/40 rounded-r-full h-full relative group">
                                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-red-300">CRIT</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                                    <?php $severityFields = [
                                        ['alert_threshold_low',      'Low ≥ Score',      20, 'border-blue-500/30 focus:border-blue-500',   'text-blue-400'],
                                        ['alert_threshold_medium',   'Medium ≥ Score',   40, 'border-yellow-500/30 focus:border-yellow-500','text-yellow-400'],
                                        ['alert_threshold_high',     'High ≥ Score',     65, 'border-orange-500/30 focus:border-orange-500','text-orange-400'],
                                        ['alert_threshold_critical', 'Critical ≥ Score', 85, 'border-red-500/30 focus:border-red-500',      'text-red-400'],
                                    ]; foreach ($severityFields as [$name, $label, $default, $border, $textColor]): ?>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-medium <?= $textColor ?>"><?= $label ?></label>
                                        <input type="number" name="<?= $name ?>" min="0" max="100"
                                               value="<?= si($name, $default) ?>"
                                               class="settings-input w-full px-3 py-2.5 rounded-xl text-sm <?= $border ?>">
                                        <input type="range" min="0" max="100" value="<?= si($name, $default) ?>"
                                               oninput="syncRange(this,'<?= $name ?>')"
                                               class="w-full accent-current mt-1">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Processing Limits -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center">
                                    <i data-lucide="filter" class="w-4 h-4 text-yellow-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Alert Processing Limits</h3>
                                    <p class="text-xs text-slate-500">Controls on alert generation, dedup, and batch sizes</p>
                                </div>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $alertProcFields = [
                                    ['notif_max_alerts_per_hour','Max Alerts / Hour',    200, 1, 9999, 'Hard limit on alert generation'],
                                    ['notif_batch_size',         'Notification Batch',    20, 1, 500,  'Alerts per notification send'],
                                    ['notif_cooldown_sec',       'Alert Cooldown (sec)',  60, 0, 3600, 'Dedup window per IP'],
                                ]; foreach ($alertProcFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: NETWORK MONITORING                             -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-network" class="tab-content space-y-4 hidden">

                        <!-- Packet Capture -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center">
                                        <i data-lucide="wifi" class="w-4 h-4 text-cyan-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-white">Packet Capture</h3>
                                        <p class="text-xs text-slate-500">Network traffic inspection limits</p>
                                    </div>
                                </div>
                                <label class="toggle-wrap">
                                    <input type="checkbox" name="packet_capture_enabled" <?= sb('packet_capture_enabled') ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                    <span class="text-xs text-slate-400">Enabled</span>
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $netFields = [
                                    ['packet_capture_limit',        'Packets / Capture',       1000, 100, 100000,'Max packets per snapshot'],
                                    ['packet_capture_interval_sec', 'Capture Interval (sec)',    30,   5,   3600, 'Seconds between captures'],
                                    ['port_scan_threshold',         'Port Scan Threshold',       15,   2,   1000, 'Ports in window = alert'],
                                    ['port_scan_window_sec',        'Port Scan Window (sec)',    60,   5,   600,  'Detection time window'],
                                ]; foreach ($netFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Log Monitor -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-green-500/10 border border-green-500/20 flex items-center justify-center">
                                    <i data-lucide="scroll-text" class="w-4 h-4 text-green-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Log Monitor Engine</h3>
                                    <p class="text-xs text-slate-500">Python log monitoring service limits</p>
                                </div>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $logMonFields = [
                                    ['log_monitor_interval_sec','Monitor Interval (sec)', 30, 5, 3600, 'Python engine poll interval'],
                                    ['log_monitor_batch_lines', 'Lines / Batch',         500, 50,50000,'Log lines per processing batch'],
                                    ['log_monitor_max_errors',  'Max Errors Before Stop', 10, 1, 100,  'Engine restarts on threshold'],
                                ]; foreach ($logMonFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: AI ENGINE                                      -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-ai" class="tab-content space-y-4 hidden">

                        <!-- Provider -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
                                    <i data-lucide="brain-circuit" class="w-4 h-4 text-purple-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">AI Provider &amp; Model</h3>
                                    <p class="text-xs text-slate-500">Select and configure your AI backend</p>
                                </div>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-slate-300 mb-1.5">AI Provider</label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <?php foreach (['local'=>['cpu','Local (Offline)','No API needed'],'gemini'=>['sparkles','Google Gemini','Cloud AI'],'ollama'=>['server','Ollama (Local LLM)','Self-hosted']] as $val=>[$icon,$name,$desc]): ?>
                                        <label class="provider-card cursor-pointer rounded-xl border p-4 text-center transition-all duration-200
                                               <?= s('ai_provider','local')===$val ? 'border-brand-500/40 bg-brand-500/10' : 'border-white/8 hover:border-white/20' ?>">
                                            <input type="radio" name="ai_provider" value="<?= $val ?>" class="sr-only" <?= s('ai_provider','local')===$val?'checked':'' ?>
                                                   onchange="document.querySelectorAll('.provider-card').forEach(c=>c.classList.remove('border-brand-500/40','bg-brand-500/10'));this.closest('.provider-card').classList.add('border-brand-500/40','bg-brand-500/10')">
                                            <i data-lucide="<?= $icon ?>" class="w-5 h-5 mx-auto mb-2 text-brand-400"></i>
                                            <div class="text-xs font-semibold text-white"><?= $name ?></div>
                                            <div class="text-xs text-slate-500 mt-0.5"><?= $desc ?></div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Gemini fields -->
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Gemini API Key</label>
                                    <div class="flex gap-2">
                                        <input type="password" name="gemini_api_key" value="<?= htmlspecialchars(s('gemini_api_key')) ?>"
                                               class="settings-input flex-1 px-3 py-2.5 rounded-xl text-sm font-mono" placeholder="AIza...">
                                        <button type="button" onclick="testGemini()"
                                                class="px-3 py-2.5 rounded-xl text-xs font-medium text-purple-400 bg-purple-500/10 border border-purple-500/20 hover:bg-purple-500/20 transition-colors whitespace-nowrap flex items-center gap-1.5">
                                            <i data-lucide="zap" class="w-3.5 h-3.5"></i> Test
                                        </button>
                                    </div>
                                    <div id="gemini-test-result" class="mt-1.5 text-xs hidden"></div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Gemini Model</label>
                                    <select name="gemini_model" class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                        <?php foreach (['gemini-1.5-pro','gemini-1.5-flash','gemini-2.0-flash','gemini-pro'] as $m): ?>
                                        <option value="<?= $m ?>" <?= s('gemini_model','gemini-1.5-pro')===$m?'selected':''?>><?= $m ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Ollama API URL</label>
                                    <input type="text" name="ollama_api_url" value="<?= htmlspecialchars(s('ollama_api_url','http://host.docker.internal:11434')) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm font-mono" placeholder="http://host.docker.internal:11434">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Ollama Model</label>
                                    <input type="text" name="ollama_model" value="<?= htmlspecialchars(s('ollama_model','llama3')) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm font-mono" placeholder="llama3">
                                </div>
                            </div>
                        </div>

                        <!-- AI Operational Limits -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-violet-500/10 border border-violet-500/20 flex items-center justify-center">
                                        <i data-lucide="gauge" class="w-4 h-4 text-violet-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-white">AI Operational Limits</h3>
                                        <p class="text-xs text-slate-500">Control usage, cost, and frequency</p>
                                    </div>
                                </div>
                                <label class="toggle-wrap">
                                    <input type="checkbox" name="ai_auto_analysis" <?= sb('ai_auto_analysis') ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                    <span class="text-xs text-slate-400">Auto-Analyze</span>
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $aiLimFields = [
                                    ['ai_max_reports_per_day', 'Max AI Reports / Day',  50,   1, 9999,  'Daily AI report cap'],
                                    ['ai_max_tokens',          'Max Tokens / Request',  4096, 256,32768,'Response token limit'],
                                    ['ai_analysis_interval_min','Auto-Analyze Interval (min)', 15, 1, 1440,'Minutes between auto-runs'],
                                ]; foreach ($aiLimFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: NOTIFICATIONS                                  -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-notif" class="tab-content space-y-4 hidden">

                        <!-- Email Alerts -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                                        <i data-lucide="mail" class="w-4 h-4 text-blue-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-white">Email Alerts (SMTP)</h3>
                                        <p class="text-xs text-slate-500">Configure SMTP for email notifications</p>
                                    </div>
                                </div>
                                <label class="toggle-wrap">
                                    <input type="checkbox" name="alert_email_enabled" <?= sb('alert_email_enabled') ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                    <span class="text-xs text-slate-400">Enabled</span>
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <?php $emailFields = [
                                    ['mail_host','SMTP Host','smtp.gmail.com','text'],
                                    ['mail_port','SMTP Port','587','number'],
                                    ['mail_user','SMTP Username','your@gmail.com','email'],
                                    ['mail_pass','SMTP Password','','password'],
                                    ['mail_from','From Address','security@platform.com','email'],
                                ]; foreach ($emailFields as [$name,$label,$placeholder,$type]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="<?= $type ?>" name="<?= $name ?>" value="<?= htmlspecialchars(s($name)) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm" placeholder="<?= $placeholder ?>">
                                </div>
                                <?php endforeach; ?>
                                <div class="sm:col-span-2 flex items-center gap-3">
                                    <label class="toggle-wrap">
                                        <input type="checkbox" name="notif_email_digest" <?= sb('notif_email_digest') ? 'checked' : '' ?>>
                                        <span class="toggle"></span>
                                        <span class="text-xs text-slate-400">Send daily digest instead of real-time</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Telegram -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                                        <i data-lucide="send" class="w-4 h-4 text-sky-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-white">Telegram Alerts</h3>
                                        <p class="text-xs text-slate-500">Instant alerts via Telegram bot</p>
                                    </div>
                                </div>
                                <label class="toggle-wrap">
                                    <input type="checkbox" name="alert_telegram_enabled" <?= sb('alert_telegram_enabled') ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                    <span class="text-xs text-slate-400">Enabled</span>
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300">Bot Token</label>
                                    <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars(s('telegram_bot_token')) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm font-mono" placeholder="123456:ABC-DEF...">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300">Chat ID</label>
                                    <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars(s('telegram_chat_id')) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm font-mono" placeholder="-1001234567890">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: DATA & LOGS                                    -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-data" class="tab-content space-y-4 hidden">

                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                                    <i data-lucide="hard-drive" class="w-4 h-4 text-emerald-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Data Retention &amp; Storage Limits</h3>
                                    <p class="text-xs text-slate-500">Control how long data is kept and how much space is used</p>
                                </div>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $dataFields = [
                                    ['log_retention_days',     'Log Retention (days)',     30,  1, 3650, 'Auto-delete logs older than N days'],
                                    ['max_log_size_mb',        'Max Log File Size (MB)',   512,  1,10000,'Per-file log size cap'],
                                    ['alert_retention_days',   'Alert Retention (days)',   90,  7, 3650, 'Keep resolved alerts for N days'],
                                    ['report_retention_days',  'Report Retention (days)', 180, 30, 3650, 'Keep AI reports for N days'],
                                    ['attack_history_days',    'Attack History (days)',    60,  7, 3650, 'Attack history retention'],
                                    ['db_query_timeout_sec',   'DB Query Timeout (sec)',   30,  1, 300,  'MySQL query timeout limit'],
                                ]; foreach ($dataFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: TRAFFIC SIMULATOR                              -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-simulator" class="tab-content space-y-4 hidden">

                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-pink-500/10 border border-pink-500/20 flex items-center justify-center">
                                        <i data-lucide="activity" class="w-4 h-4 text-pink-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-white">Traffic Simulator Limits</h3>
                                        <p class="text-xs text-slate-500">Control synthetic traffic generation rates</p>
                                    </div>
                                </div>
                                <label class="toggle-wrap">
                                    <input type="checkbox" name="sim_enabled" <?= sb('sim_enabled') ? 'checked' : '' ?>>
                                    <span class="toggle"></span>
                                    <span class="text-xs text-slate-400">Simulator On</span>
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $simFields = [
                                    ['sim_packets_per_sec',      'Packets / Second',       50, 1, 10000,'Simulated traffic rate'],
                                    ['sim_attack_interval_sec',  'Attack Interval (sec)',  30, 5, 3600, 'Time between simulated attacks'],
                                    ['sim_max_concurrent',       'Max Concurrent Flows',   10, 1, 500,  'Parallel simulation streams'],
                                    ['sim_max_ips',              'Max Unique Source IPs', 100, 5, 10000,'IP diversity in simulation'],
                                    ['sim_attack_types_limit',   'Max Attack Types Active',  5, 1, 20,  'Simultaneous attack scenarios'],
                                    ['sim_anomaly_rate_pct',     'Anomaly Rate (%)',        10, 0, 100, 'Percentage of anomalous traffic'],
                                ]; foreach ($simFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Warning banner -->
                            <div class="mx-6 mb-6 flex items-start gap-3 bg-amber-500/8 border border-amber-500/20 rounded-xl px-4 py-3">
                                <i data-lucide="triangle-alert" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                                <p class="text-xs text-amber-400/80">
                                    High simulator rates will increase DB load and trigger real alerts. Use in development only.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: SYSTEM PERFORMANCE                             -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-system" class="tab-content space-y-4 hidden">

                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                                    <i data-lucide="cpu" class="w-4 h-4 text-indigo-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">System Performance Limits</h3>
                                    <p class="text-xs text-slate-500">PHP, MySQL, and server resource caps</p>
                                </div>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <?php $sysFields = [
                                    ['mem_limit_mb',        'PHP Memory Limit (MB)',    256, 64,  4096, 'PHP memory_limit per request'],
                                    ['max_upload_mb',       'Max Upload Size (MB)',      64,  1,   512, 'File upload size cap'],
                                    ['db_max_connections',  'DB Max Connections',       100,  5,  1000, 'MySQL max_connections'],
                                    ['db_query_timeout_sec','DB Query Timeout (sec)',    30,  1,   300, 'Slow query kill threshold'],
                                    ['max_exec_time_sec',   'Max Execution Time (sec)', 120,  5,   600, 'PHP max_execution_time'],
                                    ['api_response_timeout','API Response Timeout (sec)',30,  5,   120, 'External API call timeout'],
                                ]; foreach ($sysFields as [$name,$label,$default,$min,$max,$hint]): ?>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-medium text-slate-300"><?= $label ?></label>
                                    <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>"
                                           value="<?= si($name, $default) ?>"
                                           class="settings-input w-full px-3 py-2.5 rounded-xl text-sm">
                                    <p class="text-xs text-slate-600"><?= $hint ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Live System Info -->
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <div class="px-6 py-4 border-b border-white/5">
                                <h3 class="text-sm font-semibold text-white">Live System Info</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Current runtime values (read-only)</p>
                            </div>
                            <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <?php $phpInfo = [
                                    ['PHP Version',    phpversion()],
                                    ['Memory Limit',   ini_get('memory_limit')],
                                    ['Upload Limit',   ini_get('upload_max_filesize')],
                                    ['Max Exec Time',  ini_get('max_execution_time').'s'],
                                    ['Current Memory', round(memory_get_usage(true)/1048576,1).'MB'],
                                    ['Peak Memory',    round(memory_get_peak_usage(true)/1048576,1).'MB'],
                                    ['Server Time',    date('H:i:s')],
                                    ['Timezone',       date_default_timezone_get()],
                                ]; foreach ($phpInfo as [$k,$v]): ?>
                                <div class="bg-surface-600/30 rounded-xl p-3">
                                    <div class="text-xs text-slate-500 mb-1"><?= $k ?></div>
                                    <div class="text-sm font-mono font-medium text-slate-200"><?= htmlspecialchars($v) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════════ -->
                    <!-- TAB: INTEGRATIONS (summary view)                   -->
                    <!-- ═══════════════════════════════════════════════════ -->
                    <div id="tab-integrations" class="tab-content space-y-4 hidden">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php $integrations = [
                                ['Google Gemini AI',    'sparkles',    'purple', 'gemini_api_key',        'AI-powered threat analysis', 'gemini_api_key'],
                                ['Email (SMTP)',        'mail',        'blue',   'alert_email_enabled',   'Security alert emails',      'mail_host'],
                                ['Telegram Bot',       'send',        'sky',    'alert_telegram_enabled','Real-time push alerts',      'telegram_bot_token'],
                                ['Ollama (Local LLM)', 'server',      'green',  'ollama_api_url',        'Self-hosted AI analysis',    'ollama_model'],
                            ]; foreach ($integrations as [$name,$icon,$color,$key,$desc,$checkKey]): ?>
                            <div class="glass-card rounded-2xl border border-white/5 p-5 flex items-center gap-4">
                                <div class="w-11 h-11 rounded-xl bg-<?= $color ?>-500/10 border border-<?= $color ?>-500/20 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="<?= $icon ?>" class="w-5 h-5 text-<?= $color ?>-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-white"><?= $name ?></div>
                                    <div class="text-xs text-slate-500 mt-0.5"><?= $desc ?></div>
                                </div>
                                <div class="flex-shrink-0">
                                    <?php $active = !empty(s($checkKey)); ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium
                                          <?= $active ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-slate-500/10 text-slate-500 border border-slate-500/20' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $active ? 'bg-green-400' : 'bg-slate-500' ?>"></span>
                                        <?= $active ? 'Configured' : 'Not set' ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="glass-card rounded-2xl border border-white/5 p-6">
                            <h3 class="text-sm font-semibold text-white mb-4">All Settings Quick Overview</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <?php $quickStats = [
                                    ['Login Limit',       si('rate_limit_login_attempts',5).' attempts'],
                                    ['Lockout',           si('rate_limit_lockout_minutes',15).' min'],
                                    ['Session Lifetime',  si('session_lifetime_minutes',120).' min'],
                                    ['Auto-Block',        si('auto_block_threshold',10).' alerts'],
                                    ['Alert Critical',    '≥'.si('alert_threshold_critical',85).' score'],
                                    ['AI Reports/Day',    si('ai_max_reports_per_day',50).' max'],
                                    ['Log Retention',     si('log_retention_days',30).' days'],
                                    ['Packet Limit',      si('packet_capture_limit',1000).' pkts'],
                                    ['Sim Rate',          si('sim_packets_per_sec',50).'/sec'],
                                ]; foreach ($quickStats as [$k,$v]): ?>
                                <div class="flex items-center justify-between bg-surface-600/30 rounded-xl px-3 py-2.5">
                                    <span class="text-xs text-slate-500"><?= $k ?></span>
                                    <span class="text-xs font-semibold text-slate-200 font-mono"><?= $v ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Sticky Save Bar ──────────────────────────────────────── -->
                    <div class="sticky bottom-4 z-30 mt-6">
                        <div class="glass-card rounded-2xl border border-white/8 px-6 py-4 flex items-center justify-between shadow-2xl">
                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                <i data-lucide="info" class="w-3.5 h-3.5"></i>
                                Changes apply immediately after save. Some limits require container restart.
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="location.reload()"
                                        class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 border border-white/8 hover:border-white/15 transition-all">
                                    Discard
                                </button>
                                <button type="submit" id="save-btn"
                                        class="btn-primary flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    Save All Settings
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </main>
    </div>
</div>

<style>
/* Toggle switch */
.toggle-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.toggle-wrap input[type=checkbox] { display: none; }
.toggle {
    position: relative; width: 40px; height: 22px;
    background: rgba(255,255,255,0.1); border-radius: 11px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: background 0.25s, border-color 0.25s;
    flex-shrink: 0;
}
.toggle::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 14px; height: 14px; border-radius: 50%;
    background: rgba(255,255,255,0.4);
    transition: transform 0.25s, background 0.25s;
}
.toggle-wrap input:checked + .toggle { background: rgba(66,99,235,0.5); border-color: rgba(66,99,235,0.6); }
.toggle-wrap input:checked + .toggle::after { transform: translateX(18px); background: #748ffc; }

/* Settings inputs */
.settings-input {
    background: rgba(26,34,54,0.8) !important;
    border: 1px solid rgba(255,255,255,0.07) !important;
    color: #e2e8f0 !important;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.settings-input:focus {
    border-color: #4263eb !important;
    box-shadow: 0 0 0 3px rgba(66,99,235,0.15) !important;
    outline: none;
}
.settings-input option { background: #111827; }

/* Range inputs */
input[type=range] {
    -webkit-appearance: none;
    height: 4px; border-radius: 4px;
    background: rgba(66,99,235,0.2);
    cursor: pointer;
}
input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 14px; height: 14px; border-radius: 50%;
    background: #4263eb; cursor: pointer;
    box-shadow: 0 0 0 3px rgba(66,99,235,0.2);
}

/* Provider card radio */
.provider-card { border: 1px solid; }
</style>

<script>
// ── Tab Switching ───────────────────────────────────────────────────────────
function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-brand-600/20','text-brand-400','border','border-brand-500/20');
        btn.classList.add('text-slate-500');
    });
    document.getElementById('tab-' + id).classList.remove('hidden');
    const btn = document.getElementById('tab-btn-' + id);
    btn.classList.add('bg-brand-600/20','text-brand-400','border','border-brand-500/20');
    btn.classList.remove('text-slate-500');
}

// ── Range ↔ Number sync ─────────────────────────────────────────────────────
function syncRange(rangeEl, name) {
    const numEl = document.querySelector(`input[type=number][name="${name}"]`);
    if (numEl) numEl.value = rangeEl.value;
}
// Also sync range when number changes
document.querySelectorAll('input[type=number]').forEach(numEl => {
    const range = numEl.closest('.space-y-1\\.5')?.querySelector('input[type=range]');
    if (range) numEl.addEventListener('input', () => { range.value = numEl.value; });
});

// ── Save button loader ──────────────────────────────────────────────────────
document.getElementById('settings-form').addEventListener('submit', () => {
    const btn = document.getElementById('save-btn');
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Saving…';
    btn.disabled = true;
});

// ── Auto-dismiss save banner ────────────────────────────────────────────────
const banner = document.getElementById('save-banner');
if (banner) setTimeout(() => { banner.style.opacity='0'; banner.style.transition='opacity 1s'; setTimeout(()=>banner.remove(),1000); }, 4000);

// ── Gemini API Test ─────────────────────────────────────────────────────────
function testGemini() {
    const resultEl = document.getElementById('gemini-test-result');
    resultEl.className = 'mt-1.5 text-xs text-slate-400';
    resultEl.textContent = 'Testing connection…';
    resultEl.classList.remove('hidden');
    const fd = new FormData();
    fd.append('action', 'test_gemini');
    fetch('settings.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            resultEl.className = 'mt-1.5 text-xs ' + (d.success ? 'text-green-400' : 'text-red-400');
            resultEl.textContent = d.message;
        })
        .catch(() => {
            resultEl.className = 'mt-1.5 text-xs text-red-400';
            resultEl.textContent = 'Connection failed.';
        });
}

// ── GSAP entrance ───────────────────────────────────────────────────────────
gsap.from('.tab-content:not(.hidden) > *', {
    duration: 0.4, y: 15, opacity: 0, stagger: 0.06, ease: 'power2.out'
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
