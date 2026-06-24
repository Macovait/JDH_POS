<?php
/**
 * Campaign Builder - Drag-and-drop email and SMS editor
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
$campaign_id = intval($_GET['id'] ?? 0);

// Get campaign data
$campaign = null;
if ($campaign_id) {
    $stmt = $pdo->prepare("SELECT * FROM marketing_campaigns WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$campaign_id, $tenant_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        die('Campaign not found');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_campaign'])) {
        $saved = save_campaign_content($pdo, $tenant_id, $campaign_id, $_POST);
        if ($saved) {
            $success = 'Campaign saved successfully';
            // Reload campaign data
            $stmt = $pdo->prepare("SELECT * FROM marketing_campaigns WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$campaign_id, $tenant_id]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Failed to save campaign';
        }
    } elseif (isset($_POST['send_test'])) {
        $result = send_campaign_test($pdo, $tenant_id, $campaign_id, $_POST);
        if ($result['success']) {
            $success = 'Test sent successfully';
        } else {
            $error = 'Failed to send test: ' . $result['message'];
        }
    }
}

// Get campaign content
$campaign_content = json_decode($campaign['content'] ?? '{}', true);
$email_subject = $campaign_content['email_subject'] ?? '';
$email_content = $campaign_content['email_content'] ?? '';
$sms_content = $campaign_content['sms_content'] ?? '';

// Get available merge tags
$merge_tags = get_merge_tags($campaign['channel']);

// Get template elements
$template_elements = get_template_elements($campaign['channel']);

$page_title = 'Campaign Builder | JDH POS';
ob_start();
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-amber-400">Campaign Builder</h1>
            <p class="text-gray-400 mt-1">
                <?php echo htmlspecialchars($campaign['name']); ?> -
                <?php echo ucfirst($campaign['channel']); ?> Campaign
            </p>
        </div>
        <div class="flex gap-3">
            <button onclick="sendTest()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Send Test
            </button>
            <button onclick="saveCampaign()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-save mr-2"></i>Save Campaign
            </button>
            <a href="campaigns.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Campaigns
            </a>
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

    <!-- Campaign Builder Interface -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-3">
        <!-- Toolbar -->
        <div class="lg:col-span-1">
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-tools text-blue-400"></i>
                    Elements
                </h3>

                <div class="space-y-3">
                    <?php foreach ($template_elements as $element): ?>
                        <div class="element-item bg-gray-700/50 rounded-lg p-3 cursor-pointer hover:bg-gray-700 transition-colors"
                             data-element="<?php echo $element['type']; ?>"
                             draggable="true">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas <?php echo $element['icon']; ?> text-<?php echo $element['color']; ?>-400"></i>
                                <span class="text-white font-medium text-sm"><?php echo htmlspecialchars($element['name']); ?></span>
                            </div>
                            <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($element['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Merge Tags -->
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 mt-4">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-tags text-purple-400"></i>
                    Merge Tags
                </h3>

                <div class="space-y-2">
                    <?php foreach ($merge_tags as $tag): ?>
                        <button onclick="insertMergeTag('<?php echo $tag['tag']; ?>')"
                                class="w-full text-left bg-gray-700/50 hover:bg-gray-700 rounded-lg p-2 transition-colors">
                            <div class="text-white text-sm"><?php echo htmlspecialchars($tag['tag']); ?></div>
                            <div class="text-gray-400 text-xs"><?php echo htmlspecialchars($tag['description']); ?></div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="lg:col-span-3">
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                <?php if ($campaign['channel'] === 'email'): ?>
                    <!-- Email Builder -->
                    <div class="space-y-4">
                        <!-- Email Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Email Subject</label>
                            <input type="text" id="email_subject" value="<?php echo htmlspecialchars($email_subject); ?>"
                                   placeholder="Enter your email subject line"
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                        </div>

                        <!-- Email Content Builder -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Email Content</label>
                            <div id="email-canvas" class="min-h-[500px] bg-white rounded-xl p-4 border-2 border-dashed border-gray-600 relative">
                                <div id="email-content" class="space-y-4">
                                    <?php if (!empty($email_content)): ?>
                                        <?php echo $email_content; ?>
                                    <?php else: ?>
                                        <div class="text-center py-12 text-gray-500">
                                            <i class="fas fa-plus-circle text-4xl mb-4"></i>
                                            <p>Drag elements here to build your email</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Drop zone overlay -->
                                <div id="drop-overlay" class="absolute inset-0 bg-blue-500/20 border-2 border-blue-500 rounded-xl hidden flex items-center justify-center">
                                    <div class="text-blue-400 text-lg font-medium">Drop here</div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Preview -->
                        <div class="bg-gray-700/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-white font-medium">Email Preview</h4>
                                <div class="flex gap-2">
                                    <button onclick="previewEmail('desktop')" class="text-xs text-gray-400 hover:text-white">Desktop</button>
                                    <button onclick="previewEmail('mobile')" class="text-xs text-gray-400 hover:text-white">Mobile</button>
                                </div>
                            </div>
                            <div id="email-preview" class="bg-white rounded-lg p-4 text-sm">
                                <!-- Email preview will be rendered here -->
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- SMS Builder -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">SMS Message</label>
                            <textarea id="sms_content" rows="6" maxlength="160"
                                      placeholder="Enter your SMS message (160 characters max)"
                                      class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white"><?php echo htmlspecialchars($sms_content); ?></textarea>
                            <div class="flex justify-between mt-2">
                                <p class="text-xs text-gray-400">
                                    Use merge tags like {{first_name}} to personalize messages
                                </p>
                                <p class="text-xs text-gray-400">
                                    <span id="char-count">0</span>/160 characters
                                </p>
                            </div>
                        </div>

                        <!-- SMS Templates -->
                        <div class="bg-gray-700/50 rounded-lg p-4">
                            <h4 class="text-white font-medium mb-3">Quick Templates</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <button onclick="useSMSTemplate('Welcome to our store! Thanks for joining {{first_name}}.')" class="text-left bg-gray-600 hover:bg-gray-500 rounded-lg p-3 transition-colors">
                                    <div class="text-white text-sm font-medium">Welcome Message</div>
                                    <div class="text-gray-400 text-xs">Personalized welcome</div>
                                </button>
                                <button onclick="useSMSTemplate('Happy Birthday {{first_name}}! Enjoy 10% off your next purchase.')" class="text-left bg-gray-600 hover:bg-gray-500 rounded-lg p-3 transition-colors">
                                    <div class="text-white text-sm font-medium">Birthday Offer</div>
                                    <div class="text-gray-400 text-xs">Birthday discount</div>
                                </button>
                                <button onclick="useSMSTemplate('Hi {{first_name}}, your order #{{order_number}} is ready for pickup!')" class="text-left bg-gray-600 hover:bg-gray-500 rounded-lg p-3 transition-colors">
                                    <div class="text-white text-sm font-medium">Order Ready</div>
                                    <div class="text-gray-400 text-xs">Pickup notification</div>
                                </button>
                                <button onclick="useSMSTemplate('Don\'t forget about items in your cart {{first_name}}! Complete your purchase now.')" class="text-left bg-gray-600 hover:bg-gray-500 rounded-lg p-3 transition-colors">
                                    <div class="text-white text-sm font-medium">Cart Reminder</div>
                                    <div class="text-gray-400 text-xs">Abandoned cart</div>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Campaign Settings -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-cog text-amber-400"></i>
            Campaign Settings
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Campaign Name</label>
                <input type="text" id="campaign_name" value="<?php echo htmlspecialchars($campaign['name']); ?>"
                       class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Target Segment</label>
                <select id="target_segment" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                    <option value="all" <?php echo $campaign['target_segment'] === 'all' ? 'selected' : ''; ?>>All Customers</option>
                    <option value="active" <?php echo $campaign['target_segment'] === 'active' ? 'selected' : ''; ?>>Active Customers</option>
                    <option value="inactive" <?php echo $campaign['target_segment'] === 'inactive' ? 'selected' : ''; ?>>Inactive Customers</option>
                    <option value="vip" <?php echo $campaign['target_segment'] === 'vip' ? 'selected' : ''; ?>>VIP Customers</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Schedule</label>
                <select id="schedule_type" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                    <option value="now" <?php echo ($campaign['schedule_type'] ?? 'now') === 'now' ? 'selected' : ''; ?>>Send Now</option>
                    <option value="scheduled" <?php echo ($campaign['schedule_type'] ?? 'now') === 'scheduled' ? 'selected' : ''; ?>>Schedule</option>
                </select>
            </div>
        </div>

        <div id="schedule_options" class="mt-4 <?php echo ($campaign['schedule_type'] ?? 'now') === 'scheduled' ? '' : 'hidden'; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Send Date</label>
                    <input type="date" id="send_date" value="<?php echo $campaign['scheduled_at'] ? date('Y-m-d', strtotime($campaign['scheduled_at'])) : ''; ?>"
                           class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Send Time</label>
                    <input type="time" id="send_time" value="<?php echo $campaign['scheduled_at'] ? date('H:i', strtotime($campaign['scheduled_at'])) : ''; ?>"
                           class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for saving -->
<form id="save-form" method="post" style="display: none;">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="save_campaign" value="1">
    <input type="hidden" name="campaign_name" value="">
    <input type="hidden" name="email_subject" value="">
    <input type="hidden" name="email_content" value="">
    <input type="hidden" name="sms_content" value="">
    <input type="hidden" name="target_segment" value="">
    <input type="hidden" name="schedule_type" value="">
    <input type="hidden" name="scheduled_at" value="">
</form>

<script>
// Global variables
let draggedElement = null;
let canvasContent = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initDragAndDrop();
    initEmailCanvas();
    initSMSBuilder();
    initScheduleOptions();
});

// Drag and drop functionality
function initDragAndDrop() {
    // Make elements draggable
    document.querySelectorAll('.element-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            draggedElement = this.dataset.element;
        });
    });

    // Canvas drop zones
    const canvas = document.getElementById('email-canvas');
    if (canvas) {
        canvas.addEventListener('dragover', function(e) {
            e.preventDefault();
            document.getElementById('drop-overlay').classList.remove('hidden');
        });

        canvas.addEventListener('dragleave', function(e) {
            document.getElementById('drop-overlay').classList.add('hidden');
        });

        canvas.addEventListener('drop', function(e) {
            e.preventDefault();
            document.getElementById('drop-overlay').classList.add('hidden');

            if (draggedElement) {
                addElementToCanvas(draggedElement);
            }
        });
    }
}

function addElementToCanvas(elementType) {
    const canvas = document.getElementById('email-content');
    const emptyState = canvas.querySelector('.text-center');

    if (emptyState) {
        canvas.innerHTML = '';
    }

    const element = createCanvasElement(elementType);
    canvas.appendChild(element);
}

function createCanvasElement(type) {
    const element = document.createElement('div');
    element.className = 'canvas-element bg-gray-50 border border-gray-300 rounded-lg p-4 mb-4 relative group';

    switch (type) {
        case 'heading':
            element.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Heading</span>
                    <button onclick="removeElement(this)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <input type="text" placeholder="Enter your heading" class="w-full bg-transparent border-none text-2xl font-bold text-gray-900 focus:outline-none">
            `;
            break;

        case 'text':
            element.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Text</span>
                    <button onclick="removeElement(this)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div contenteditable="true" class="w-full bg-transparent border-none text-gray-900 focus:outline-none min-h-[60px]">
                    Enter your text here. Click to edit.
                </div>
            `;
            break;

        case 'button':
            element.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Button</span>
                    <button onclick="removeElement(this)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="text-center">
                    <input type="text" placeholder="Button text" class="px-6 py-3 bg-blue-600 text-white rounded-lg border-none text-center focus:outline-none">
                </div>
            `;
            break;

        case 'image':
            element.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Image</span>
                    <button onclick="removeElement(this)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="text-center p-8 border-2 border-dashed border-gray-300 rounded-lg">
                    <i class="fas fa-image text-gray-400 text-2xl mb-2"></i>
                    <p class="text-gray-500">Click to add image</p>
                </div>
            `;
            break;

        case 'spacer':
            element.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Spacer</span>
                    <button onclick="removeElement(this)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="h-8 bg-gray-200 rounded"></div>
            `;
            break;
    }

    return element;
}

function removeElement(button) {
    button.closest('.canvas-element').remove();
}

// Email canvas functionality
function initEmailCanvas() {
    // Update preview when content changes
    document.getElementById('email-content')?.addEventListener('input', updateEmailPreview);
    document.getElementById('email_subject')?.addEventListener('input', updateEmailPreview);

    updateEmailPreview();
}

function updateEmailPreview() {
    const subject = document.getElementById('email_subject').value;
    const content = document.getElementById('email-content').innerHTML;

    const preview = document.getElementById('email-preview');
    preview.innerHTML = `
        <div class="mb-4">
            <strong>Subject:</strong> ${subject || 'No subject'}
        </div>
        <div class="border-t pt-4">
            ${content || 'No content'}
        </div>
    `;
}

// SMS builder functionality
function initSMSBuilder() {
    const smsTextarea = document.getElementById('sms_content');
    if (smsTextarea) {
        smsTextarea.addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('char-count').textContent = count;
        });

        // Initial count
        document.getElementById('char-count').textContent = smsTextarea.value.length;
    }
}

function useSMSTemplate(content) {
    document.getElementById('sms_content').value = content;
    document.getElementById('char-count').textContent = content.length;
}

function insertMergeTag(tag) {
    const activeElement = document.activeElement;
    if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.contentEditable === 'true')) {
        if (activeElement.contentEditable === 'true') {
            // For contenteditable elements
            const selection = window.getSelection();
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(document.createTextNode(tag));
        } else {
            // For input/textarea
            const start = activeElement.selectionStart;
            const end = activeElement.selectionEnd;
            const value = activeElement.value;
            activeElement.value = value.substring(0, start) + tag + value.substring(end);
            activeElement.selectionStart = activeElement.selectionEnd = start + tag.length;
        }
        activeElement.focus();
    }
}

// Schedule options
function initScheduleOptions() {
    document.getElementById('schedule_type').addEventListener('change', function() {
        const scheduleOptions = document.getElementById('schedule_options');
        if (this.value === 'scheduled') {
            scheduleOptions.classList.remove('hidden');
        } else {
            scheduleOptions.classList.add('hidden');
        }
    });
}

// Campaign actions
function saveCampaign() {
    // Collect form data
    const form = document.getElementById('save-form');
    form.campaign_name.value = document.getElementById('campaign_name').value;
    form.email_subject.value = document.getElementById('email_subject').value;
    form.email_content.value = document.getElementById('email-content').innerHTML;
    form.sms_content.value = document.getElementById('sms_content').value;
    form.target_segment.value = document.getElementById('target_segment').value;
    form.schedule_type.value = document.getElementById('schedule_type').value;

    // Handle schedule
    if (form.schedule_type.value === 'scheduled') {
        const date = document.getElementById('send_date').value;
        const time = document.getElementById('send_time').value;
        if (date && time) {
            form.scheduled_at.value = date + ' ' + time + ':00';
        }
    }

    form.submit();
}

function sendTest() {
    // Implementation for sending test campaigns
    alert('Send test functionality would be implemented here');
}

function previewEmail(device) {
    // Implementation for device-specific preview
    alert('Device preview would be implemented here');
}
</script>

<?php
// Helper functions
function get_merge_tags($channel) {
    $common_tags = [
        ['tag' => '{{first_name}}', 'description' => 'Customer first name'],
        ['tag' => '{{last_name}}', 'description' => 'Customer last name'],
        ['tag' => '{{email}}', 'description' => 'Customer email'],
        ['tag' => '{{phone}}', 'description' => 'Customer phone'],
        ['tag' => '{{last_purchase_date}}', 'description' => 'Last purchase date'],
        ['tag' => '{{total_spent}}', 'description' => 'Total amount spent'],
        ['tag' => '{{loyalty_points}}', 'description' => 'Current loyalty points']
    ];

    if ($channel === 'email') {
        $common_tags = array_merge($common_tags, [
            ['tag' => '{{unsubscribe_link}}', 'description' => 'Unsubscribe link'],
            ['tag' => '{{store_name}}', 'description' => 'Store/brand name']
        ]);
    } else {
        $common_tags = array_merge($common_tags, [
            ['tag' => '{{order_number}}', 'description' => 'Order/receipt number'],
            ['tag' => '{{store_name}}', 'description' => 'Store/brand name']
        ]);
    }

    return $common_tags;
}

function get_template_elements($channel) {
    if ($channel === 'email') {
        return [
            ['type' => 'heading', 'name' => 'Heading', 'icon' => 'heading', 'color' => 'blue', 'description' => 'Add a heading to your email'],
            ['type' => 'text', 'name' => 'Text Block', 'icon' => 'paragraph', 'color' => 'green', 'description' => 'Add text content'],
            ['type' => 'button', 'name' => 'Button', 'icon' => 'hand-pointer', 'color' => 'purple', 'description' => 'Add a call-to-action button'],
            ['type' => 'image', 'name' => 'Image', 'icon' => 'image', 'color' => 'amber', 'description' => 'Add an image to your email'],
            ['type' => 'spacer', 'name' => 'Spacer', 'icon' => 'arrows-alt-v', 'color' => 'gray', 'description' => 'Add vertical spacing']
        ];
    } else {
        return [
            ['type' => 'text', 'name' => 'Message', 'icon' => 'comment', 'color' => 'green', 'description' => 'SMS message content']
        ];
    }
}

function save_campaign_content($pdo, $tenant_id, $campaign_id, $data) {
    try {
        $content = [
            'email_subject' => $data['email_subject'] ?? '',
            'email_content' => $data['email_content'] ?? '',
            'sms_content' => $data['sms_content'] ?? ''
        ];

        $stmt = $pdo->prepare("
            UPDATE marketing_campaigns
            SET name = ?, target_segment = ?, content = ?, schedule_type = ?, scheduled_at = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        return $stmt->execute([
            $data['campaign_name'],
            $data['target_segment'],
            json_encode($content),
            $data['schedule_type'],
            $data['scheduled_at'] ?: null,
            $campaign_id,
            $tenant_id
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function send_campaign_test($pdo, $tenant_id, $campaign_id, $data) {
    // Implementation for sending test campaigns
    return ['success' => false, 'message' => 'Test functionality not yet implemented'];
}

$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';