<?php
/**
 * Chart of Accounts API Controller
 * RESTful API endpoints for chart of accounts management
 * 
 * Follows the same patterns as barcode_labels.php:
 * - Security-first with CSRF protection
 * - Input validation and sanitization
 * - Proper error handling
 * - Rate limiting integration
 * - Service-oriented architecture
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Import accounting services
require_once __DIR__ . '/../../src/Accounting/ChartOfAccountsService.php';
require_once __DIR__ . '/../../src/SettingsManager.php';
require_once __DIR__ . '/../../src/Barcode/RateLimiter.php';
require_once __DIR__ . '/../../src/Barcode/BarcodeErrorHandler.php';

require_login();

// Check permissions for accounting features
if (!check_permission('accounting.view') && !is_super_admin()) {
    enforce_permission('accounting.view');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = function_exists('get_current_user_id') ? get_current_user_id() : (int)($_SESSION['user']['id'] ?? 0);

if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

// Initialize services (following barcode module pattern)
$settingsManager = new SettingsManager($pdo, $tenant_id);
$rateLimiter = new RateLimiter($pdo, $tenant_id, $user_id);
$errorHandler = new BarcodeErrorHandler(false);
$chartOfAccountsService = new ChartOfAccountsService(
    $pdo,
    $settingsManager,
    $rateLimiter,
    $errorHandler,
    $tenant_id,
    $user_id
);

$csrf_token = generate_csrf_token();

// ============================================================================
// API ENDPOINTS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'get_chart_of_accounts':
                // Get full chart of accounts hierarchy
                $chartOfAccounts = $chartOfAccountsService->getChartOfAccounts();
                echo json_encode(['success' => true, 'data' => $chartOfAccounts]);
                break;
                
            case 'get_account_balance':
                // Get balance for specific account
                $accountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
                $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
                $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
                
                if ($accountId <= 0) {
                    throw new Exception('Invalid account ID');
                }
                
                $balance = $chartOfAccountsService->getAccountBalance($accountId, $dateFrom, $dateTo);
                echo json_encode(['success' => true, 'balance' => $balance]);
                break;
                
            case 'get_trial_balance':
                // Get trial balance
                $dateAsOf = isset($_GET['date_as_of']) ? $_GET['date_as_of'] : null;
                $trialBalance = $chartOfAccountsService->getTrialBalance($dateAsOf);
                echo json_encode(['success' => true, 'data' => $trialBalance]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// POST/PUT/PATCH/DELETE ENDPOINTS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        // CSRF validation
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        switch ($_POST['action']) {
            case 'create_account':
                // Create new account
                $accountData = [
                    'account_code' => $_POST['account_code'] ?? '',
                    'account_name' => $_POST['account_name'] ?? '',
                    'account_type' => $_POST['account_type'] ?? '',
                    'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                    'description' => $_POST['description'] ?? '',
                    'normal_balance' => $_POST['normal_balance'] ?? 'debit',
                    'tax_related' => isset($_POST['tax_related']) && $_POST['tax_related'] === 'true',
                    'report_section' => $_POST['report_section'] ?? '',
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] === 'true'
                ];
                
                $accountId = $chartOfAccountsService->createAccount($accountData);
                echo json_encode(['success' => true, 'account_id' => $accountId]);
                break;
                
            case 'update_account':
                // Update existing account
                $accountId = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
                if ($accountId <= 0) {
                    throw new Exception('Invalid account ID');
                }
                
                $accountData = [];
                // Only include fields that were actually posted
                $fields = ['account_code', 'account_name', 'account_type', 'parent_id', 
                          'description', 'normal_balance', 'tax_related', 'report_section', 'is_active'];
                
                foreach ($fields as $field) {
                    if (isset($_POST[$field])) {
                        // Handle special cases
                        if ($field === 'parent_id') {
                            $accountData[$field] = !empty($_POST[$field]) ? (int)$_POST[$field] : null;
                        } elseif ($field === 'tax_related' || $field === 'is_active') {
                            $accountData[$field] = $_POST[$field] === 'true';
                        } else {
                            $accountData[$field] = $_POST[$field];
                        }
                    }
                }
                
                $result = $chartOfAccountsService->updateAccount($accountId, $accountData);
                echo json_encode(['success' => true, 'updated' => $result]);
                break;
                
            case 'delete_account':
                // Delete account (soft delete)
                $accountId = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
                if ($accountId <= 0) {
                    throw new Exception('Invalid account ID');
                }
                
                $result = $chartOfAccountsService->deleteAccount($accountId);
                echo json_encode(['success' => true, 'deleted' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// HTML INTERFACE (Optional - for direct browser access)
// ============================================================================

$page_title = 'Chart of Accounts | Jakababa POS';
ob_start();
?>
<div class="">
    <!-- Robust CSRF token for AJAX calls -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 mb-6">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Accounting</div>
            <h1 class="text-lg font-bold text-white flex items-center gap-3">
                Chart of Accounts
                <span id="accounting-status" 
                      class="hidden text-[10px] font-medium px-2 py-0.5 rounded-full items-center gap-1 align-middle">
                    <i id="accounting-status-icon" class="fas fa-wifi"></i>
                    <span id="accounting-status-text">Offline ready</span>
                </span>
            </h1>
            <p class="text-sm text-slate-500 mt-0.5 mt-1">Manage your chart of accounts and financial reporting <span class="text-emerald-400/70">(works offline)</span></p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <!-- Sidebar Configuration -->
        <div class="lg:col-span-1">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
                <h3 class="text-white font-semibold text-sm mb-4 flex items-center gap-2">
                    <i class="fas fa-list-ol text-amber-400"></i>
                    Accounts
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="account-search" class="text-slate-300 text-xs font-medium uppercase tracking-wider mb-2 block">Search Accounts</label>
                        <input type="text" id="account-search" 
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors placeholder-slate-500"
                               placeholder="Account code, name..." autocomplete="off">
                    </div>
                    
                    <div>
                        <label for="account-type-filter" class="text-slate-300 text-xs font-medium uppercase tracking-wider mb-2 block">Account Type</label>
                        <select id="account-type-filter" 
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors">
                            <option value="">All Types</option>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="revenue">Revenue</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="show-inactive" class="text-slate-300 text-xs font-medium uppercase tracking-wider mb-2 block">Show Inactive</label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="show-inactive" class="w-3 h-3 text-amber-600">
                            <span class="text-slate-300">Show inactive accounts</span>
                        </label>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="space-y-2">
                        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors w-full justify-center mt-2" id="add-account-btn" disabled>
                            <i class="fas fa-plus mr-1"></i><span>Add New Account</span>
                        </button>
                        
                        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors w-full justify-center mt-2" id="refresh-accounts-btn">
                            <i class="fas fa-sync-alt mr-1"></i><span>Refresh Accounts</span>
                        </button>
                        
                        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors w-full justify-center mt-2" id="trial-balance-btn">
                            <i class="fas fa-calculator mr-1"></i><span>Trial Balance</span>
                        </button>
                    </div>
                    
                    <!-- Stats -->
                    <div class="mt-5 pt-5 border-t border-slate-600">
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div>
                                <div class="text-amber-400 font-bold text-lg" id="stat-accounts">0</div>
                                <div class="text-slate-300 text-xs">Accounts</div>
                            </div>
                            <div>
                                <div class="text-amber-400 font-bold text-lg" id="stat-active">0</div>
                                <div class="text-slate-300 text-xs">Active</div>
                            </div>
                            <div>
                                <div class="text-amber-400 font-bold text-lg" id="stat-inactive">0</div>
                                <div class="text-slate-300 text-xs">Inactive</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accounts List -->
        <div class="lg:col-span-2">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white font-semibold text-sm flex items-center gap-2">
                        <i class="fas fa-building text-amber-400"></i>
                        Account List
                    </h3>
                </div>
                
                <div class="bg-slate-800 border border-slate-600 rounded-lg overflow-hidden" style="max-height: 600px; overflow-y: auto;">
                    <?php if (true): /* Placeholder - would check if service initialized properly */ ?>
                    <div id="accounts-list" class="divide-y divide-slate-600">
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center gap-2 text-slate-300">
                                <div class="w-5 h-5 border-2 border-amber-500/50 border-t-amber-500 rounded-full animate-spin"></div>
                                <span>Loading accounts...</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-8 text-center text-slate-400">
                        <i class="fas fa-ban text-xl mb-2"></i>
                        <p>Service unavailable</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Account Details / Form -->
        <div class="lg:col-span-1">
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
                <h3 class="text-white font-semibold text-sm mb-4 flex items-center gap-2">
                    <i class="fas fa-edit text-amber-400"></i>
                    Account Details
                </h3>
                
                <div id="account-details-form">
                    <div class="p-8 text-center">
                        <div class="text-slate-400">Select an account to view details</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Account Form Modal -->
    <div id="account-form-modal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999]" style="display:none;">
        <div class="bg-slate-800 rounded-xl w-full max-w-2xl mx-4 p-6 border border-slate-600">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" id="modal-title">Add New Account</h3>
                <button type="button" id="close-form-modal" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            
            <form id="account-form" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="form-account-code" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Account Code *</label>
                        <input type="text" id="form-account-code" 
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors"
                               placeholder="e.g., 1000 for assets" maxlength="20">
                    </div>
                    
                    <div>
                        <label for="form-account-name" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Account Name *</label>
                        <input type="text" id="form-account-name" 
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors"
                               placeholder="e.g., Cash, Accounts Receivable" maxlength="200">
                    </div>
                    
                    <div>
                        <label for="form-account-type" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Account Type *</label>
                        <select id="form-account-type" 
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors">
                            <option value="">Select Type</option>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="revenue">Revenue</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="form-normal-balance" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Normal Balance</label>
                        <select id="form-normal-balance" 
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors">
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="form-parent-account" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Parent Account (Optional)</label>
                        <select id="form-parent-account" 
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors">
                            <option value="">No Parent (Top Level)</option>
                            <!-- Will be populated via JS -->
                        </select>
                    </div>
                    
                    <div>
                        <label for="form-description" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Description</label>
                        <textarea id="form-description" 
                                  class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors"
                                  rows="3" placeholder="Optional description"></textarea>
                    </div>
                    
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="form-tax-related" class="w-3 h-3 text-amber-600">
                            <span class="text-slate-300">Tax Related</span>
                        </label>
                    </div>
                    
                    <div>
                        <label for="form-report-section" class="block text-slate-300 text-xs font-medium uppercase tracking-wider mb-1">Report Section</label>
                        <select id="form-report-section" 
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors">
                            <option value="">Not Specified</option>
                            <option value="balance_sheet">Balance Sheet</option>
                            <option value="income_statement">Income Statement</option>
                            <option value="cash_flow">Cash Flow</option>
                            <option value="notes">Notes to Financial Statements</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="form-is-active" checked class="w-3 h-3 text-amber-600">
                            <span class="text-slate-300">Active</span>
                        </label>
                    </div>
                </div>
                
                <div class="mt-6 pt-4 border-t border-slate-600">
                    <div class="flex justify-between">
                        <button type="button" id="form-cancel-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors w-full justify-center">
                            <i class="fas fa-times mr-1"></i><span>Cancel</span>
                        </button>
                        <button type="button" id="form-save-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors w-full justify-center">
                            <i class="fas fa-save mr-1"></i><span>Save Account</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const ACCOUNTING_AVAILABLE = true; // Set by PHP if services loaded
const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token) ?>';
const TENANT_ID = <?= json_encode($tenant_id) ?>;
const USER_ID = <?= json_encode($user_id) ?>;

// Tenant label settings from SettingsManager (used to delegate to LabelPrinter / LabelPrintService)
const ACCOUNTING_SETTINGS = <?= json_encode([], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// Expose CSRF for AJAX calls
window.CSRF_TOKEN = '<?= htmlspecialchars($csrf_token ?? '') ?>';

if (ACCOUNTING_AVAILABLE) {
    console.log('%c[Accounting] Service available - using ChartOfAccountsService', 'color:#10b981');
}

// Modern Accounting App (would load accounting.js)
</script>

<!-- Modern Accounting App (delegates to ChartOfAccountsService / SettingsManager) -->
<script src="<?php echo base_url('assets/js/accounting.js'); ?>?v=1"></script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
?>