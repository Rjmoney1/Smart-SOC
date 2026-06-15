<?php
/**
 * AI Reports Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth()->requireLogin();

$pageTitle  = 'AI Security Reports';
$activePage = 'reports';

$page      = max(1, (int)($_GET['page'] ?? 1));
$sql       = "SELECT r.*, u.username FROM ai_reports r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC";
$paginated = paginate($sql, [], $page);
$reports   = $paginated['data'];

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
                        <h2 class="text-xl font-bold text-white">AI Security Reports</h2>
                        <p class="text-slate-500 text-sm mt-0.5">Gemini AI-powered threat analysis and incident reports</p>
                    </div>
                    <button onclick="document.getElementById('generate-modal').classList.remove('hidden')"
                            class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white">
                        <i data-lucide="sparkles" class="w-4 h-4"></i> Generate Report
                    </button>
                </div>

                <!-- AI Generate Panel -->
                <div class="animate-in glass-card rounded-2xl p-6 border border-white/5 bg-gradient-to-br from-brand-900/20 to-transparent">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-brand-500/20 border border-brand-500/30 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="brain-circuit" class="w-5 h-5 text-brand-400"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-semibold text-white mb-1">Gemini AI Security Assistant</h3>
                            <p class="text-sm text-slate-400 mb-4">Ask Gemini AI to analyze logs, classify threats, generate incident reports, or provide mitigation recommendations.</p>
                            <div class="flex gap-3">
                                <textarea id="ai-prompt" class="flex-1 text-sm text-slate-300 bg-surface-600/50 border border-white/5 rounded-xl p-3 resize-none h-20 focus:border-brand-500/30 focus:outline-none placeholder-slate-600 transition-colors"
                                          placeholder="e.g. Analyze the recent SSH brute-force patterns and suggest mitigation strategies..."></textarea>
                                <div class="flex flex-col gap-2">
                                    <button onclick="generateAiReport()" class="btn-primary px-4 py-2 rounded-xl text-sm font-medium text-white flex items-center gap-2 whitespace-nowrap">
                                        <i data-lucide="zap" class="w-4 h-4"></i> Analyze
                                    </button>
                                    <button onclick="generateSystemReport()" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-300 bg-white/5 border border-white/5 hover:bg-white/10 transition-colors flex items-center gap-2 whitespace-nowrap">
                                        <i data-lucide="file-bar-chart" class="w-4 h-4"></i> Full Report
                                    </button>
                                </div>
                            </div>

                            <!-- Quick Prompts -->
                            <div class="flex flex-wrap gap-2 mt-3">
                                <?php $prompts = [
                                    'Summarize today\'s security incidents',
                                    'Analyze SSH brute-force patterns',
                                    'Top threats this week with risk scores',
                                    'Provide port scan mitigation strategies',
                                ]; foreach ($prompts as $p): ?>
                                <button onclick="document.getElementById('ai-prompt').value=<?= json_encode($p) ?>"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-brand-500/10 border border-brand-500/20 text-brand-400 hover:bg-brand-500/20 transition-colors">
                                    <?= htmlspecialchars($p) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- AI Response -->
                    <div id="ai-response-container" class="hidden mt-5 p-5 bg-surface-600/30 border border-white/5 rounded-xl">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="sparkles" class="w-4 h-4 text-brand-400"></i>
                                <span class="text-sm font-semibold text-white">Gemini Analysis</span>
                            </div>
                            <button onclick="saveReport()" class="text-xs text-brand-400 hover:text-brand-300 flex items-center gap-1 transition-colors">
                                <i data-lucide="save" class="w-3 h-3"></i> Save Report
                            </button>
                        </div>
                        <div id="ai-response-text" class="text-sm text-slate-300 leading-relaxed whitespace-pre-wrap"></div>
                    </div>
                </div>

                <!-- Reports Grid -->
                <?php if (!empty($reports)): ?>
                <div class="animate-in">
                    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Saved Reports</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <?php foreach ($reports as $report): ?>
                        <div class="glass-card rounded-2xl p-5 border border-white/5 hover:border-white/10 transition-colors cursor-pointer"
                             onclick="viewReport(<?= $report['id'] ?>)">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-brand-500/10 border border-brand-500/20 flex items-center justify-center">
                                        <i data-lucide="file-text" class="w-4 h-4 text-brand-400"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-white"><?= htmlspecialchars($report['title']) ?></div>
                                        <div class="text-xs text-slate-500"><?= timeAgo($report['created_at']) ?> by <?= htmlspecialchars($report['username'] ?? 'System') ?></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button onclick="event.stopPropagation(); exportReport(<?= $report['id'] ?>)" class="p-1.5 rounded-lg text-slate-500 hover:text-brand-400 hover:bg-brand-500/10 transition-colors">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <button onclick="event.stopPropagation(); deleteReport(<?= $report['id'] ?>)" class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed line-clamp-3">
                                <?= htmlspecialchars(substr($report['content'] ?? '', 0, 200)) ?>...
                            </p>
                            <div class="flex items-center gap-2 mt-3">
                                <?php if ($report['report_type']): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400">
                                    <?= htmlspecialchars($report['report_type']) ?>
                                </span>
                                <?php endif; ?>
                                <span class="text-xs text-slate-600"><?= date('M j, Y', strtotime($report['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="animate-in glass-card rounded-2xl p-12 border border-white/5 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="file-bar-chart" class="w-8 h-8 text-brand-400"></i>
                    </div>
                    <h3 class="text-base font-semibold text-white mb-2">No Reports Yet</h3>
                    <p class="text-sm text-slate-500 mb-5">Generate your first AI security report using the panel above.</p>
                    <button onclick="generateSystemReport()" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-medium text-white inline-flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4"></i> Generate First Report
                    </button>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<!-- Report View Modal -->
<div id="report-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay bg-black/60 p-4">
    <div class="glass-card rounded-2xl border border-white/10 w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
            <h3 id="report-modal-title" class="text-base font-semibold text-white">Report</h3>
            <div class="flex items-center gap-2">
                <button id="report-export-btn" class="flex items-center gap-1.5 text-xs text-brand-400 hover:text-brand-300 px-3 py-1.5 rounded-lg bg-brand-500/10 border border-brand-500/20 transition-colors">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export PDF
                </button>
                <button onclick="document.getElementById('report-modal').classList.add('hidden')" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
        <div id="report-modal-content" class="p-6 text-sm text-slate-300 leading-relaxed whitespace-pre-wrap"></div>
    </div>
</div>

<script>
let currentReportText = '';

function generateAiReport() {
    const prompt = document.getElementById('ai-prompt').value.trim();
    if (!prompt) { alert('Please enter a prompt.'); return; }
    
    const container = document.getElementById('ai-response-container');
    const text = document.getElementById('ai-response-text');
    container.classList.remove('hidden');
    text.textContent = 'Analyzing with Gemini AI... This may take a moment.';
    
    fetch('<?= APP_URL ?>/dashboard/api/ai_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt, type: 'custom' })
    }).then(r => r.json()).then(d => {
        currentReportText = d.analysis || d.message || 'Analysis complete.';
        text.textContent = currentReportText;
    }).catch(() => {
        text.textContent = 'Error: Failed to connect to Gemini AI. Please check your API key configuration.';
    });
}

function generateSystemReport() {
    const container = document.getElementById('ai-response-container');
    const text = document.getElementById('ai-response-text');
    container.classList.remove('hidden');
    text.textContent = 'Generating comprehensive security report...';
    document.getElementById('ai-prompt').value = 'Generate a comprehensive security incident report for today\'s activities.';
    
    fetch('<?= APP_URL ?>/dashboard/api/ai_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'full_report' })
    }).then(r => r.json()).then(d => {
        currentReportText = d.analysis || d.message || 'Report generated.';
        text.textContent = currentReportText;
    }).catch(() => {
        text.textContent = 'Error: Failed to generate report.';
    });
}

function saveReport() {
    if (!currentReportText) return;
    const title = prompt('Report title:', 'Security Analysis - ' + new Date().toLocaleDateString());
    if (!title) return;
    fetch('<?= APP_URL ?>/dashboard/api/ai_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'save', title, content: currentReportText })
    }).then(r => r.json()).then(d => {
        if (d.success) { alert('Report saved!'); location.reload(); }
    });
}

function viewReport(id) {
    document.getElementById('report-modal').classList.remove('hidden');
    fetch(`<?= APP_URL ?>/dashboard/api/ai_analysis.php?id=${id}`)
        .then(r => r.json()).then(d => {
            document.getElementById('report-modal-title').textContent = d.report?.title || 'Report';
            document.getElementById('report-modal-content').textContent = d.report?.content || '';
        });
}

function deleteReport(id) {
    if (!confirm('Delete this report?')) return;
    fetch('<?= APP_URL ?>/dashboard/api/ai_analysis.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

function exportReport(id) {
    window.open(`<?= APP_URL ?>/dashboard/api/ai_analysis.php?id=${id}&export=pdf`);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
