<?php
/**
 * Automated Marketing Campaigns
 * Email and SMS marketing with automation and analytics
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
require_login();
if (!check_permission('marketing.manage') && !is_super_admin()) {
    enforce_permission('marketing.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_campaign'])) {
        $campaign_id = create_campaign($pdo, $tenant_id, $_POST);
        if ($campaign_id) {
            $success = 'Campaign created successfully';
            // Redirect to campaign builder
            header('Location: ' . base_url('marketing/campaign_builder.php?id=' . $campaign_id));
            exit;
        } else {
            $error = 'Failed to create campaign';
        }
    } elseif (isset($_POST['send_test_email'])) {
        $result = send_test_email($pdo, $tenant_id, $_POST);
        if ($result['success']) {
            $success = 'Test email sent successfully';
        } else {
            $error = 'Failed to send test email: ' . $result['message'];
        }
    } elseif (isset($_POST['send_test_sms'])) {
        $result = send_test_sms($pdo, $tenant_id, $_POST);
        if ($result['success']) {
            $success = 'Test SMS sent successfully';
        } else {
            $error = 'Failed to send test SMS: ' . $result['message'];
        }
    } elseif (isset($_POST['schedule_campaign'])) {
        $scheduled = schedule_campaign($pdo, $tenant_id, $_POST['campaign_id']);
        if ($scheduled) {
            $success = 'Campaign scheduled successfully';
        } else {
            $error = 'Failed to schedule campaign';
        }
    } elseif (isset($_POST['pause_campaign'])) {
        $paused = update_campaign_status($pdo, $tenant_id, $_POST['campaign_id'], 'paused');
        if ($paused) {
            $success = 'Campaign paused successfully';
        } else {
            $error = 'Failed to pause campaign';
        }
    } elseif (isset($_POST['resume_campaign'])) {
        $resumed = update_campaign_status($pdo, $tenant_id, $_POST['campaign_id'], 'active');
        if ($resumed) {
            $success = 'Campaign resumed successfully';
        } else {
            $error = 'Failed to resume campaign';
        }
    }
}

// Get data
$campaigns = get_campaigns($pdo, $tenant_id);
$campaign_stats = get_campaign_stats($pdo, $tenant_id);
$customer_segments = get_customer_segments($pdo, $tenant_id);
$email_templates = get_email_templates($pdo, $tenant_id);
$sms_templates = get_sms_templates($pdo, $tenant_id);

$page_title = 'Marketing Automation | JDH POS';
ob_start();
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-amber-400">Marketing Automation</h1>
            <p class="text-gray-400 mt-1">Automated email and SMS campaigns</p>
        </div>
        <div class="flex gap-3">
            <button onclick="createCampaign()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-plus mr-2"></i>New Campaign
            </button>
            <button onclick="openTemplates()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                <i class="fas fa-file-alt mr-2"></i>Templates
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div class="bg-emerald-500/10 border border-emerald-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-emerald-400">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Marketing Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Active Campaigns</p>
                    <p class="text-2xl font-bold text-white"><?php echo $campaign_stats['active_campaigns']; ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                    <i class="fas fa-bullhorn text-green-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Emails Sent</p>
                    <p class="text-2xl font-bold text-blue-400"><?php echo number_format($campaign_stats['total_emails_sent']); ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <i class="fas fa-envelope text-blue-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">SMS Sent</p>
                    <p class="text-2xl font-bold text-purple-400"><?php echo number_format($campaign_stats['total_sms_sent']); ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <i class="fas fa-sms text-purple-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Open Rate</p>
                    <p class="text-2xl font-bold text-amber-400"><?php echo $campaign_stats['avg_open_rate']; ?>%</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-eye text-amber-400"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Automated Campaigns -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-robot text-blue-400"></i>
                Automated Campaigns
            </h3>

            <div class="space-y-3">
                <button onclick="createAutomation('welcome')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Welcome Series</div>
                    <div class="text-gray-400 text-xs">Send welcome emails to new customers</div>
                </button>

                <button onclick="createAutomation('birthday')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Birthday Messages</div>
                    <div class="text-gray-400 text-xs">Automated birthday SMS campaigns</div>
                </button>

                <button onclick="createAutomation('abandoned_cart')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Abandoned Cart</div>
                    <div class="text-gray-400 text-xs">Recover lost sales automatically</div>
                </button>

                <button onclick="createAutomation('loyalty_reminder')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Loyalty Reminders</div>
                    <div class="text-gray-400 text-xs">Encourage customers to use points</div>
                </button>
            </div>
        </div>

        <!-- Campaign Templates -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-file-alt text-purple-400"></i>
                Campaign Templates
            </h3>

            <div class="space-y-3">
                <button onclick="useTemplate('promotional')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Promotional Email</div>
                    <div class="text-gray-400 text-xs">Announce sales and special offers</div>
                </button>

                <button onclick="useTemplate('newsletter')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Monthly Newsletter</div>
                    <div class="text-gray-400 text-xs">Keep customers updated</div>
                </button>

                <button onclick="useTemplate('product_launch')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Product Launch</div>
                    <div class="text-gray-400 text-xs">Introduce new products</div>
                </button>

                <button onclick="useTemplate('feedback')" class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-3 transition-colors">
                    <div class="text-white font-medium text-sm">Feedback Request</div>
                    <div class="text-gray-400 text-xs">Gather customer insights</div>
                </button>
            </div>
        </div>

        <!-- Customer Segments -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-users text-green-400"></i>
                Customer Segments
            </h3>

            <div class="space-y-3">
                <?php foreach (array_slice($customer_segments, 0, 4) as $segment): ?>
                    <div class="flex items-center justify-between bg-gray-700/50 rounded-lg p-3">
                        <div>
                            <div class="text-white font-medium text-sm"><?php echo htmlspecialchars($segment['name']); ?></div>
                            <div class="text-gray-400 text-xs"><?php echo number_format($segment['count']); ?> customers</div>
                        </div>
                        <button onclick="createSegmentCampaign('<?php echo $segment['id']; ?>')" class="text-green-400 hover:text-green-300 text-xs">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fas fa-list text-amber-400"></i>
                Your Campaigns
            </h3>
            <div class="flex gap-2">
                <select id="status_filter" onchange="filterCampaigns()" class="form-input px-3 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white text-sm">
                    <option value="all">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="text-center py-8">
                <i class="fas fa-bullhorn text-gray-600 text-4xl mb-4"></i>
                <p class="text-gray-400">No campaigns yet</p>
                <p class="text-sm text-gray-500 mt-2">Create your first marketing campaign to get started</p>
                <button onclick="createCampaign()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors mt-4">
                    <i class="fas fa-plus mr-2"></i>Create Campaign
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="bg-gray-700/50 rounded-lg p-4 campaign-item"
                         data-status="<?php echo $campaign['status']; ?>">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-<?php echo get_status_color($campaign['status']); ?>-500/20 flex items-center justify-center">
                                    <i class="fas <?php echo get_campaign_icon($campaign['type']); ?> text-<?php echo get_status_color($campaign['status']); ?>-400"></i>
                                </div>
                                <div>
                                    <h4 class="text-white font-medium"><?php echo htmlspecialchars($campaign['name']); ?></h4>
                                    <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($campaign['description'] ?: 'No description'); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($campaign['status'] === 'draft'): ?>
                                    <button onclick="editCampaign(<?php echo $campaign['id']; ?>)" class="text-blue-400 hover:text-blue-300 text-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="scheduleCampaign(<?php echo $campaign['id']; ?>)" class="text-green-400 hover:text-green-300 text-sm">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php elseif ($campaign['status'] === 'active'): ?>
                                    <button onclick="pauseCampaign(<?php echo $campaign['id']; ?>)" class="text-amber-400 hover:text-amber-300 text-sm">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                <?php elseif ($campaign['status'] === 'paused'): ?>
                                    <button onclick="resumeCampaign(<?php echo $campaign['id']; ?>)" class="text-green-400 hover:text-green-300 text-sm">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php endif; ?>
                                <button onclick="viewCampaignStats(<?php echo $campaign['id']; ?>)" class="text-purple-400 hover:text-purple-300 text-sm">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <button onclick="duplicateCampaign(<?php echo $campaign['id']; ?>)" class="text-gray-400 hover:text-gray-300 text-sm">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-400">
                                    <i class="fas fa-<?php echo $campaign['channel'] === 'email' ? 'envelope' : 'sms'; ?> mr-1"></i>
                                    <?php echo ucfirst($campaign['channel']); ?>
                                </span>
                                <span class="text-gray-400">
                                    <i class="fas fa-users mr-1"></i>
                                    <?php echo number_format($campaign['target_count']); ?> recipients
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo get_status_badge_class($campaign['status']); ?>">
                                    <?php echo ucfirst($campaign['status']); ?>
                                </span>
                            </div>
                            <div class="text-gray-400">
                                Created <?php echo date('M d, Y', strtotime($campaign['created_at'])); ?>
                            </div>
                        </div>

                        <!-- Campaign Progress (if active/scheduled) -->
                        <?php if (in_array($campaign['status'], ['active', 'scheduled'])): ?>
                            <div class="mt-3">
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $campaign['sent_count']; ?>/<?php echo $campaign['target_count']; ?></span>
                                </div>
                                <div class="w-full bg-gray-600 rounded-full h-2">
                                    <div class="bg-<?php echo get_status_color($campaign['status']); ?>-500 h-2 rounded-full"
                                         style="width: <?php echo $campaign['target_count'] > 0 ? ($campaign['sent_count'] / $campaign['target_count'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Campaign Modal -->
    <div id="campaign-modal" class="fixed inset-0 bg-black/50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-xl max-w-lg w-full">
                <div class="p-6 border-b border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Create New Campaign</h3>
                        <button onclick="closeCampaignModal()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <form method="post" class="p-6 space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Campaign Name</label>
                        <input type="text" name="campaign_name" id="campaign_name" required
                               class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Campaign Type</label>
                        <select name="campaign_type" id="campaign_type" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <option value="one-time">One-time Campaign</option>
                            <option value="automated">Automated Campaign</option>
                            <option value="drip">Drip Campaign</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Channel</label>
                        <select name="channel" id="channel" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Target Segment</label>
                        <select name="target_segment" id="target_segment" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <option value="all">All Customers</option>
                            <?php foreach ($customer_segments as $segment): ?>
                                <option value="<?php echo $segment['id']; ?>"><?php echo htmlspecialchars($segment['name']); ?> (<?php echo number_format($segment['count']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description (Optional)</label>
                        <textarea name="description" id="campaign_description" rows="3"
                                  class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
                        <button type="button" onclick="closeCampaignModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">Cancel</button>
                        <button type="submit" name="create_campaign" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">Create Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions
function createCampaign() {
    document.getElementById('campaign-modal').classList.remove('hidden');
}

function closeCampaignModal() {
    document.getElementById('campaign-modal').classList.add('hidden');
}

// Campaign actions
function createAutomation(type) {
    // Map automation types to campaign templates
    const templates = {
        'welcome': { name: 'Welcome Series', type: 'automated', channel: 'email' },
        'birthday': { name: 'Birthday Messages', type: 'automated', channel: 'sms' },
        'abandoned_cart': { name: 'Abandoned Cart Recovery', type: 'automated', channel: 'email' },
        'loyalty_reminder': { name: 'Loyalty Points Reminder', type: 'automated', channel: 'sms' }
    };

    const template = templates[type];
    if (template) {
        document.getElementById('campaign_name').value = template.name;
        document.getElementById('campaign_type').value = template.type;
        document.getElementById('channel').value = template.channel;
        createCampaign();
    }
}

function useTemplate(template) {
    // Map templates to campaign settings
    const templates = {
        'promotional': { name: 'Promotional Campaign', type: 'one-time', channel: 'email' },
        'newsletter': { name: 'Monthly Newsletter', type: 'one-time', channel: 'email' },
        'product_launch': { name: 'New Product Launch', type: 'one-time', channel: 'email' },
        'feedback': { name: 'Customer Feedback', type: 'one-time', channel: 'email' }
    };

    const tpl = templates[template];
    if (tpl) {
        document.getElementById('campaign_name').value = tpl.name;
        document.getElementById('campaign_type').value = tpl.type;
        document.getElementById('channel').value = tpl.channel;
        createCampaign();
    }
}

function createSegmentCampaign(segmentId) {
    document.getElementById('target_segment').value = segmentId;
    createCampaign();
}

function editCampaign(campaignId) {
    // Redirect to campaign builder
    window.location.href = 'campaign_builder.php?id=' + campaignId;
}

function scheduleCampaign(campaignId) {
    if (confirm('Schedule this campaign to run?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="schedule_campaign" value="1">
            <input type="hidden" name="campaign_id" value="${campaignId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function pauseCampaign(campaignId) {
    if (confirm('Pause this campaign?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="pause_campaign" value="1">
            <input type="hidden" name="campaign_id" value="${campaignId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function resumeCampaign(campaignId) {
    if (confirm('Resume this campaign?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="resume_campaign" value="1">
            <input type="hidden" name="campaign_id" value="${campaignId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewCampaignStats(campaignId) {
    // Redirect to campaign stats
    window.location.href = 'campaign_stats.php?id=' + campaignId;
}

function duplicateCampaign(campaignId) {
    if (confirm('Duplicate this campaign?')) {
        // Implementation for duplicating campaigns
        alert('Campaign duplication would be implemented here');
    }
}

function openTemplates() {
    alert('Templates management would be implemented here');
}

function filterCampaigns() {
    const status = document.getElementById('status_filter').value;
    const campaigns = document.querySelectorAll('.campaign-item');

    campaigns.forEach(campaign => {
        if (status === 'all' || campaign.dataset.status === status) {
            campaign.style.display = 'block';
        } else {
            campaign.style.display = 'none';
        }
    });
}

// Modal close handler
document.getElementById('campaign-modal').addEventListener('click', function(e) {
    if (e.target === this) closeCampaignModal();
});

// Helper functions for styling
function get_status_color(status) {
    const colors = {
        'draft': 'gray',
        'scheduled': 'blue',
        'active': 'green',
        'paused': 'amber',
        'completed': 'purple'
    };
    return colors[status] || 'gray';
}

function get_campaign_icon(type) {
    const icons = {
        'one-time': 'bullhorn',
        'automated': 'robot',
        'drip': 'tint'
    };
    return icons[type] || 'bullhorn';
}

function get_status_badge_class(status) {
    const classes = {
        'draft': 'bg-gray-500/20 text-gray-400',
        'scheduled': 'bg-blue-500/20 text-blue-400',
        'active': 'bg-green-500/20 text-green-400',
        'paused': 'bg-amber-500/20 text-amber-400',
        'completed': 'bg-purple-500/20 text-purple-400'
    };
    return classes[status] || 'bg-gray-500/20 text-gray-400';
}
</script>

<?php
// Helper functions
function get_campaigns($pdo, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT c.*,
               COUNT(cm.id) as sent_count,
               COUNT(DISTINCT ct.customer_id) as target_count
        FROM marketing_campaigns c
        LEFT JOIN campaign_messages cm ON c.id = cm.campaign_id
        LEFT JOIN campaign_targets ct ON c.id = ct.campaign_id
        WHERE c.tenant_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_campaign_stats($pdo, $tenant_id) {
    // Get basic stats - in a real implementation, these would come from campaign analytics tables
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_campaigns FROM marketing_campaigns WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $active = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'active_campaigns' => $active['active_campaigns'] ?? 0,
        'total_emails_sent' => 0, // Would be calculated from campaign_messages
        'total_sms_sent' => 0, // Would be calculated from campaign_messages
        'avg_open_rate' => 0 // Would be calculated from campaign analytics
    ];
}

function get_customer_segments($pdo, $tenant_id) {
    // Define some basic segments - in a real implementation, these would be dynamic
    return [
        ['id' => 'all', 'name' => 'All Customers', 'count' => 0],
        ['id' => 'active', 'name' => 'Active Customers', 'count' => 0],
        ['id' => 'inactive', 'name' => 'Inactive Customers', 'count' => 0],
        ['id' => 'vip', 'name' => 'VIP Customers', 'count' => 0],
        ['id' => 'new', 'name' => 'New Customers', 'count' => 0]
    ];
}

function get_email_templates($pdo, $tenant_id) {
    // Basic email templates
    return [
        ['id' => 'welcome', 'name' => 'Welcome Email', 'subject' => 'Welcome to our store!'],
        ['id' => 'promotional', 'name' => 'Promotional Email', 'subject' => 'Special Offer Just for You'],
        ['id' => 'newsletter', 'name' => 'Newsletter', 'subject' => 'Monthly Updates']
    ];
}

function get_sms_templates($pdo, $tenant_id) {
    // Basic SMS templates
    return [
        ['id' => 'welcome', 'name' => 'Welcome SMS', 'message' => 'Welcome! Thanks for joining us.'],
        ['id' => 'birthday', 'name' => 'Birthday SMS', 'message' => 'Happy Birthday! Enjoy 10% off today.'],
        ['id' => 'reminder', 'name' => 'Appointment Reminder', 'message' => 'Don\'t forget your appointment tomorrow.']
    ];
}

function create_campaign($pdo, $tenant_id, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO marketing_campaigns (
                tenant_id, name, description, type, channel, target_segment, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW())
        ");
        $stmt->execute([
            $tenant_id,
            trim($data['campaign_name']),
            trim($data['description'] ?? ''),
            $data['campaign_type'],
            $data['channel'],
            $data['target_segment']
        ]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function send_test_email($pdo, $tenant_id, $data) {
    // Implementation for sending test emails
    return ['success' => false, 'message' => 'Email functionality not yet implemented'];
}

function send_test_sms($pdo, $tenant_id, $data) {
    // Implementation for sending test SMS
    return ['success' => false, 'message' => 'SMS functionality not yet implemented'];
}

function schedule_campaign($pdo, $tenant_id, $campaign_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE marketing_campaigns
            SET status = 'scheduled', scheduled_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        return $stmt->execute([$campaign_id, $tenant_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function update_campaign_status($pdo, $tenant_id, $campaign_id, $status) {
    try {
        $stmt = $pdo->prepare("
            UPDATE marketing_campaigns
            SET status = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        return $stmt->execute([$status, $campaign_id, $tenant_id]);
    } catch (PDOException $e) {
        return false;
    }
}

$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
?>