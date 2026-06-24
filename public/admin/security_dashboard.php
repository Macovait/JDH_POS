<?php
/**
 * Security Dashboard - Monitor Security Events and System Status
 * 
 * @package Jakababa\Admin
 * @version 1.0.0
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';

// Initialize security system
SecurityBootstrap::initialize();

// Require admin access (super admin or system.admin permission)
require_login();
if (!is_super_admin() && !check_permission('system.admin')) {
    redirect(base_url('dashboard/home.php?error=unauthorized'));
}

$page_title = 'Security Dashboard';
ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-shield-halved text-amber-400"></i> Security Dashboard
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Monitor security events and system status</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="refreshDashboard()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-refresh text-xs"></i> Refresh
        </button>
        <button onclick="exportReport()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-download text-xs"></i> Export Report
        </button>
    </div>
</div>

<!-- Security Overview Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-users text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400 truncate" id="activeUsersCount">-</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Active Users</div>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-database text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400 truncate" id="dataAccessCount">-</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Data Access</div>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-shield-alt text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400 truncate" id="securityEventsCount">-</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Security Events</div>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-exclamation-triangle text-red-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-red-400 truncate" id="violationsCount">-</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Violations</div>
        </div>
    </div>
</div>

<!-- Recent Security Events -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-2 mb-4">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-clock text-amber-400 text-sm"></i> Recent Security Events
        </h2>
        <div id="recentEvents" class="space-y-2">
            <div class="text-center text-slate-500 py-6">
                <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
                <p class="text-sm">Loading security events...</p>
            </div>
        </div>
    </div>

    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-chart-line text-amber-400 text-sm"></i> Top Accessed Tables
        </h2>
        <div id="topTables" class="space-y-2">
            <div class="text-center text-slate-500 py-6">
                <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
                <p class="text-sm">Loading access statistics...</p>
            </div>
        </div>
    </div>
</div>

<!-- Permission Denials & System Status -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-2 mb-4">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-ban text-red-400 text-sm"></i> Recent Permission Denials
        </h2>
        <div id="permissionDenials" class="space-y-2">
            <div class="text-center text-slate-500 py-6">
                <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
                <p class="text-sm">Loading permission data...</p>
            </div>
        </div>
    </div>

    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-cogs text-amber-400 text-sm"></i> System Status
        </h2>
        <div class="space-y-2">
            <div class="flex items-center justify-between py-1.5 border-b border-slate-700/50">
                <span class="text-sm text-slate-300">Security System</span>
                <span class="px-2 py-0.5 bg-emerald-500/15 text-emerald-400 text-xs rounded-full ring-1 ring-emerald-500/30">Active</span>
            </div>
            <div class="flex items-center justify-between py-1.5 border-b border-slate-700/50">
                <span class="text-sm text-slate-300">Tenant Isolation</span>
                <span class="px-2 py-0.5 bg-emerald-500/15 text-emerald-400 text-xs rounded-full ring-1 ring-emerald-500/30">Enforced</span>
            </div>
            <div class="flex items-center justify-between py-1.5 border-b border-slate-700/50">
                <span class="text-sm text-slate-300">Audit Logging</span>
                <span class="px-2 py-0.5 bg-emerald-500/15 text-emerald-400 text-xs rounded-full ring-1 ring-emerald-500/30">Active</span>
            </div>
            <div class="flex items-center justify-between py-1.5">
                <span class="text-sm text-slate-300">Database Security</span>
                <span class="px-2 py-0.5 bg-emerald-500/15 text-emerald-400 text-xs rounded-full ring-1 ring-emerald-500/30">Secure</span>
            </div>
        </div>
    </div>
</div>

<!-- Security Actions -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
        <i class="fas fa-tools text-amber-400 text-sm"></i> Security Actions
    </h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        <button onclick="runSecurityTests()" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-400 text-sm font-medium hover:bg-blue-500/20 transition-colors">
            <i class="fas fa-vial text-xs"></i> Run Security Tests
        </button>
        <button onclick="cleanAuditLogs()" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/50 border border-slate-600 text-slate-400 text-sm font-medium hover:bg-slate-600 hover:text-white transition-colors">
            <i class="fas fa-broom text-xs"></i> Clean Audit Logs
        </button>
        <button onclick="generateReport()" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-file-alt text-xs"></i> Generate Report
        </button>
        <button onclick="viewAuditLogs()" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/50 border border-slate-600 text-slate-400 text-sm font-medium hover:bg-slate-600 hover:text-white transition-colors">
            <i class="fas fa-list text-xs"></i> View Audit Logs
        </button>
    </div>
</div>

<script>
// Load dashboard data
function loadDashboardData() {
    fetch('../api/security_dashboard.php')
        .then(response => response.json())
        .then(data => {
            updateOverviewCards(data);
            updateRecentEvents(data.security_events || []);
            updateTopTables(data.top_accessed_tables || []);
            updatePermissionDenials(data.permission_denials || []);
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
            showError('Failed to load dashboard data');
        });
}

// Update overview cards
function updateOverviewCards(data) {
    document.getElementById('activeUsersCount').textContent = data.active_users || 0;
    document.getElementById('dataAccessCount').textContent = data.data_access_count || 0;
    document.getElementById('securityEventsCount').textContent = data.security_events_count || 0;
    document.getElementById('violationsCount').textContent = data.violations_count || 0;
}

// Update recent events
function updateRecentEvents(events) {
    const container = document.getElementById('recentEvents');
    
    if (events.length === 0) {
        container.innerHTML = '<div class="text-center text-slate-500 py-4">No recent security events</div>';
        return;
    }
    
    container.innerHTML = events.map(event => `
        <div class="flex items-start gap-3 p-3 bg-slate-900/30 rounded-lg border border-slate-700/30">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center ${getSeverityColor(event.severity)}">
                <i class="fas ${getSeverityIcon(event.severity)} text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-white">${event.event_type}</span>
                    <span class="text-xs text-slate-500">${formatTime(event.created_at)}</span>
                </div>
                <p class="text-xs text-slate-400">${event.description}</p>
            </div>
        </div>
    `).join('');
}

// Update top tables
function updateTopTables(tables) {
    const container = document.getElementById('topTables');
    
    if (tables.length === 0) {
        container.innerHTML = '<div class="text-center text-slate-500 py-4">No data access recorded</div>';
        return;
    }
    
    container.innerHTML = tables.map(table => `
        <div class="flex items-center justify-between p-3 bg-slate-900/30 rounded-lg border border-slate-700/30">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-500/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-table text-blue-400 text-xs"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-white">${table.table_name}</div>
                    <div class="text-xs text-slate-400">${table.operation} operations</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-medium text-white">${table.access_count}</div>
                <div class="text-xs text-slate-500">${table.unique_users} users</div>
            </div>
        </div>
    `).join('');
}

// Update permission denials
function updatePermissionDenials(denials) {
    const container = document.getElementById('permissionDenials');
    
    if (denials.length === 0) {
        container.innerHTML = '<div class="text-center text-slate-500 py-4">No permission denials</div>';
        return;
    }
    
    container.innerHTML = denials.map(denial => `
        <div class="flex items-center justify-between p-3 bg-slate-900/30 rounded-lg border border-slate-700/30">
            <div>
                <div class="text-sm font-medium text-white">${denial.permission}</div>
                <div class="text-xs text-slate-400">${denial.denial_count} denials</div>
            </div>
            <div class="px-2 py-0.5 bg-red-500/15 text-red-400 text-xs rounded-full ring-1 ring-red-500/30">
                Denied
            </div>
        </div>
    `).join('');
}

// Helper functions
function getSeverityColor(severity) {
    const colors = {
        'low': 'bg-blue-500/20 text-blue-400',
        'medium': 'bg-amber-500/20 text-amber-400',
        'high': 'bg-orange-500/20 text-orange-400',
        'critical': 'bg-red-500/20 text-red-400'
    };
    return colors[severity] || 'bg-slate-500/20 text-slate-400';
}

function getSeverityIcon(severity) {
    const icons = {
        'low': 'fa-info',
        'medium': 'fa-exclamation',
        'high': 'fa-exclamation-triangle',
        'critical': 'fa-times-circle'
    };
    return icons[severity] || 'fa-info';
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' min ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
    return date.toLocaleDateString();
}

// Action functions
function refreshDashboard() {
    loadDashboardData();
}

function runSecurityTests() {
    window.open('../tests/SecurityTestSuite.php', '_blank');
}

function cleanAuditLogs() {
    if (confirm('Are you sure you want to clean old audit logs?')) {
        fetch('../api/clean_audit_logs.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Audit logs cleaned successfully');
                    loadDashboardData();
                } else {
                    showError('Failed to clean audit logs');
                }
            })
            .catch(error => {
                console.error('Error cleaning audit logs:', error);
                showError('Failed to clean audit logs');
            });
    }
}

function generateReport() {
    window.open('../api/generate_security_report.php', '_blank');
}

function viewAuditLogs() {
    window.open('audit_logs.php', '_blank');
}

function exportReport() {
    fetch('../api/export_security_report.php')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'security_report_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            console.error('Error exporting report:', error);
            showError('Failed to export report');
        });
}

// Notification functions
function showSuccess(message) {
    // Implement success notification
    console.log('Success:', message);
}

function showError(message) {
    // Implement error notification
    console.error('Error:', message);
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    
    // Auto-refresh every 30 seconds
    setInterval(loadDashboardData, 30000);
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
