<?php
// Aggressive cache prevention for this page (prevents stale JS causing SyntaxError)
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Professional Barcode Label Printer - Jakababa POS
 * 
 * Offline barcode generation using picqer/php-barcode-generator
 * Generates printable A4 barcode labels with:
 * - Code128 real scannable barcodes
 * - Multiple label sizes (K5 38×16mm, K22 51×25mm, KA2 51×13mm, K38 76×38mm, K11 19×13mm, KA1 25×19mm, K27 51×38mm, K36 76×51mm, K05/K09/K15 circular DIA)
 * - Dynamic product selection
 * - Professional label design
 * - Print preview
 * 
 * Setup: composer require picqer/php-barcode-generator
 * 
 * Features:
 * - Multi-product selection with checkboxes
 * - Multiple label sizes (K-series thermal/roll labels + circular DIA)
 * - Dynamic barcode generation (Code128)
 * - A4 layout with auto-fitting
 * - Print preview and export
 * - Offer badge support
 * - SKU auto-generation
 */

require_once __DIR__ . '/../../src/paths.php';

// Load Composer autoloader early (required for Dompdf and other vendor classes)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Professional Barcode Label System (2026 Enterprise Upgrade)
require_once __DIR__ . '/../../src/Barcode/LabelTemplate.php';
require_once __DIR__ . '/../../src/Barcode/BarcodeService.php';
require_once __DIR__ . '/../../src/Barcode/LabelRenderer.php';
require_once __DIR__ . '/../../src/Barcode/PdfLabelGenerator.php';
require_once __DIR__ . '/../../src/Barcode/LabelPrintService.php';
require_once __DIR__ . '/../../src/Barcode/LabelPrinter.php';
require_once __DIR__ . '/../../src/Barcode/RateLimiter.php';
require_once __DIR__ . '/../../src/Barcode/BarcodeErrorHandler.php';
require_once __DIR__ . '/../../src/Barcode/ProductImporter.php';
require_once __DIR__ . '/../../src/SettingsManager.php';

require_login();

if (!check_permission('products.view') && !is_super_admin()) {
    enforce_permission('products.view');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$branch_name = get_current_branch_name();

$user_id = function_exists('get_current_user_id') ? get_current_user_id() : (int)($_SESSION['user']['id'] ?? 0);

// Initialize enterprise services
$settingsManager = new SettingsManager($pdo, $tenant_id);
$labelPrintService = new \JDH\POS\Barcode\LabelPrintService($pdo, $tenant_id, $branch_id, $user_id);
$rateLimiter = new \JDH\POS\Barcode\RateLimiter($pdo, $tenant_id, $user_id);
$errorHandler = new \JDH\POS\Barcode\BarcodeErrorHandler(false);

// Current label settings (for UI defaults)
$labelSettings = $settingsManager->getLabelSettings();

// ============================================================================
// CHECK BARCODE LIBRARY AVAILABILITY
// ============================================================================
$barcode_available = class_exists('\Picqer\Barcode\BarcodeGenerator');
$library_error = $barcode_available ? null : 'Barcode generator class not found. Run: <code>composer require picqer/php-barcode-generator</code>';

// Additional library checks for enterprise features
$dompdf_available = class_exists('Dompdf\Dompdf');
$escpos_available = class_exists('Mike42\Escpos\Printer');

$dompdf_error = $dompdf_available ? null : 'Professional PDF requires: <code>composer require dompdf/dompdf</code>';
$escpos_error = $escpos_available ? null : 'Thermal printing requires: <code>composer require mike42/escpos-php</code>';

$csrf_token = generate_csrf_token();

$store_name = \JDH\POS\Barcode\LabelPrintService::resolveStoreName($pdo, $tenant_id);
try {
    // Resolved above from current settings/tenant data.
} catch (Exception $e) {
    error_log("Error fetching store name: " . $e->getMessage());
}

// Get categories for filter dropdown
$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// ============================================================================
// All POST/AJAX actions are handled exclusively by barcode_labels_generate.php
// Redirect any stale direct POST to that endpoint.
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Use barcode_labels_generate.php for AJAX requests.']);
    exit;
}

// All AJAX handled by barcode_labels_generate.php — nothing here.

$page_title = 'Barcode Labels — ' . ($branch_name ?? ($_SESSION['tenant_name'] ?? 'POS System'));
ob_start();
?>

<div class="">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <p class="text-[10px] font-bold text-amber-400 uppercase tracking-widest mb-1">Products</p>
            <h1 class="text-xl font-bold text-white flex items-center gap-3">
                Barcode Label Printer
                <span id="sw-status" class="hidden text-[10px] font-medium px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/25 items-center gap-1">
                    <i id="sw-status-icon" class="fas fa-wifi"></i>
                    <span id="sw-status-text">Offline ready</span>
                </span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">Generate professional barcode labels for your products <span class="text-emerald-400/70">(works offline)</span></p>
        </div>
    </div>

    <!-- System Status Banner — admin-only, no composer commands for regular users -->
    <?php
        $any_missing = (!$barcode_available || !$dompdf_available || !$escpos_available);
        $is_admin = is_super_admin() || check_permission('settings.manage');
    ?>
    <?php if ($any_missing): ?>
    <div class="flex items-start gap-3 bg-amber-500/10 border border-amber-500/25 rounded-xl px-4 py-3 mb-6">
        <i class="fas fa-exclamation-triangle text-amber-400 mt-0.5 flex-shrink-0"></i>
        <div class="text-xs">
            <?php if ($is_admin): ?>
                <p class="text-amber-300 font-semibold mb-1">Some features are unavailable — action required</p>
                <ul class="space-y-1 text-amber-200/80">
                    <?php if (!$barcode_available): ?><li>Barcode generation: <code class="bg-black/30 px-1 rounded font-mono">composer require picqer/php-barcode-generator</code></li><?php endif; ?>
                    <?php if (!$dompdf_available): ?><li>PDF export: <code class="bg-black/30 px-1 rounded font-mono">composer require dompdf/dompdf</code></li><?php endif; ?>
                    <?php if (!$escpos_available): ?><li>Thermal printing: <code class="bg-black/30 px-1 rounded font-mono">composer require mike42/escpos-php</code></li><?php endif; ?>
                </ul>
            <?php else: ?>
                <p class="text-amber-300 font-semibold">Some features are temporarily unavailable.</p>
                <p class="text-amber-200/70 mt-0.5">Please contact your system administrator to resolve this.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <!-- Sidebar Configuration -->
        <div class="lg:col-span-1">
            <div class="bg-slate-900/80 border border-white/8 rounded-xl p-5">
                <h3 class="text-white font-semibold text-sm mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-amber-400"></i>
                    Configuration
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="search" class="text-slate-300 text-xs font-medium uppercase tracking-wider mb-2 block">Search Products</label>
                        <input type="text" id="search" 
                            class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors placeholder-slate-500"
                            placeholder="Name, SKU..." autocomplete="off" 
                            <?= !$barcode_available ? 'disabled' : '' ?>>
                    </div>
                    
                    <div>
                        <label for="category" class="text-slate-300 text-xs font-medium uppercase tracking-wider mb-2 block">Category</label>
                        <select id="category" 
                            class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors"
                            <?= !$barcode_available ? 'disabled' : '' ?>>
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="label_size" class="text-slate-300 text-xs font-medium uppercase tracking-wider mb-2 block">Label Size</label>
                        <select id="label_size" 
                            class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500/50 transition-colors"
                            <?= !$barcode_available ? 'disabled' : '' ?>>
                            <?php 
                                $currentSize = $labelSettings['auto_print_label_size'] ?? 'k22';
                                $sizes = [
                                    'k5'  => 'K5 - 38mm × 16mm (rect)',
                                    'k22' => 'K22 - 51mm × 25mm (rect)',
                                    'KA2' => 'KA2 - 51mm × 13mm (rect)',
                                    'K38' => 'K38 - 76mm × 38mm (rect)',
                                    'K11' => 'K11 - 19mm × 13mm (rect)',
                                    'KA1' => 'KA1 - 25mm × 19mm (rect)',
                                    'K27' => 'K27 - 51mm × 38mm (rect)',
                                    'k36' => 'K36 - 76mm × 51mm (rect)',
                                    'K05' => 'K05 - 13mm DIA (circle)',
                                    'K09' => 'K09 - 22mm DIA (circle)',
                                    'K15' => 'K15 - 49mm DIA (circle)',
                                ];
                                foreach ($sizes as $val => $label): 
                            ?>
                                <option value="<?= $val ?>" <?= $val === $currentSize ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                     <button type="button" id="generateBtn" disabled
                             class="w-full flex items-center justify-center gap-2 mt-4 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                         <i class="fas fa-print"></i><span>Generate & Print (Browser)</span>
                     </button>
                      <button type="button" id="pdfBtn" disabled
                              class="w-full flex items-center justify-center gap-2 mt-2 px-4 py-2.5 rounded-lg bg-emerald-500/15 border border-emerald-500/40 text-emerald-400 font-semibold text-sm hover:bg-emerald-500/25 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                          <i class="fas fa-file-pdf mr-1"></i><span>Download Professional PDF</span>
                      </button>
                      <?php if (!$dompdf_available): ?>
                          <div class="text-[10px] text-amber-400 mt-0.5 px-1">
                              PDF unavailable — <code class="bg-amber-900/40 px-1 rounded">composer require dompdf/dompdf</code>
                          </div>
                      <?php endif; ?>

                      <div id="labelPrinterStatus" class="hidden mt-4 rounded border border-slate-600 bg-slate-900 px-3 py-2 text-xs text-slate-300"></div>

                      <!-- New Enterprise Buttons -->
                      <button type="button" id="reprintBtn"
                          class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-all">
                          <i class="fas fa-history"></i><span>Reprint Last Labels</span>
                      </button>
                       <?php if (!$escpos_available): ?>
                           <div class="text-[10px] text-amber-400 mt-0.5 px-1">
                               Thermal (ESC/POS) unavailable — <code class="bg-amber-900/40 px-1 rounded">composer require mike42/escpos-php</code>
                           </div>
                       <?php endif; ?>

                      <button type="button" id="templateEditorBtn"
                          class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 font-semibold text-sm hover:bg-white/10 hover:text-white transition-all">
                          <i class="fas fa-edit"></i><span>Advanced Template Editor</span>
                      </button>

                     <details class="mt-3">
                         <summary class="cursor-pointer text-xs text-amber-400 hover:text-amber-300 font-medium">Batch Print (Enterprise)</summary>
                         <div class="mt-2 space-y-2">
                             <select id="batch_filter" class="w-full text-xs bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-slate-300">
                                 <option value="low_stock">Low Stock Items</option>
                                 <option value="out_of_stock">Out of Stock</option>
                                 <option value="new_this_month">New This Month</option>
                             </select>
                             <button type="button" id="batchPrintBtn"
                                 class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-blue-500/15 border border-blue-500/30 text-blue-400 text-xs font-semibold hover:bg-blue-500/25 transition-all">
                                 Batch Print Selected Filter
                             </button>
                         </div>
                     </details>

                     <!-- NEW: Batch Import Products from CSV -->
                     <details class="mt-3 text-xs">
                         <summary class="cursor-pointer text-emerald-400 hover:text-emerald-300">📥 Batch Import CSV (Enterprise)</summary>
                         <div class="mt-2 space-y-2">
                             <div class="bg-slate-700/50 border border-slate-600 rounded p-2 text-[10px] text-slate-300">
                                 <p class="mb-1"><strong>Format:</strong> CSV with columns: name, sku, price</p>
                                 <button type="button" id="downloadTemplateBtn" class="text-emerald-400 hover:text-emerald-300 underline text-[10px]">
                                     Download template
                                 </button>
                             </div>
                             
                             <select id="batchImportCategory" class="w-full text-xs bg-slate-800 border border-slate-600 rounded px-2 py-1">
                                 <option value="">No Category (Optional)</option>
                                 <?php foreach ($categories as $cat): ?>
                                 <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                 <?php endforeach; ?>
                             </select>
                             
                             <div class="relative">
                                 <input type="file" id="batchImportFile" accept=".csv" class="hidden" />
                                 <button type="button" id="batchImportFileBtn"
                                     class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 text-xs font-medium hover:bg-white/10 transition-all">
                                     <i class="fas fa-folder-open text-[10px]"></i> Select CSV File
                                 </button>
                             </div>
                             
                             <button type="button" id="batchImportBtn" disabled
                                 class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-600/80 text-white text-xs font-semibold hover:bg-emerald-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                 <i class="fas fa-bolt text-[10px]"></i> Import Products
                             </button>
                             
                             <div id="batchImportStatus" class="hidden bg-blue-600/20 border border-blue-500 rounded p-2 text-xs text-blue-300">
                                 <!-- Status messages -->
                             </div>
                         </div>
                     </details>

                     <!-- Auto-print setting -->
                     <div class="mt-4 pt-3 border-t border-slate-600 text-xs">
                          <?php
                          $autoPrintEnabled = function_exists('get_settings') 
                              ? (get_settings('auto_print_labels_on_sale', '0', $tenant_id) === '1') 
                              : false;
                          ?>
                          <label class="flex items-center gap-2 cursor-pointer">
                              <input type="checkbox" id="autoPrintOnSale" class="w-3 h-3" 
                                     <?= $autoPrintEnabled ? 'checked' : '' ?>>
                              <span class="text-slate-300">Auto-print labels on sale completion</span>
                          </label>
                         <div class="text-[10px] text-slate-500 mt-1">Uses current label size. Can be overridden per product.</div>
                     </div>
                </div>

                <!-- Stats -->
                <div class="mt-5 pt-5 border-t border-slate-600">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <div class="text-amber-400 font-bold text-lg" id="statProducts">0</div>
                            <div class="text-slate-300 text-xs">Products</div>
                        </div>
                        <div>
                            <div class="text-amber-400 font-bold text-lg" id="statLabels">0</div>
                            <div class="text-slate-300 text-xs">Labels</div>
                        </div>
                        <div>
                            <div class="text-amber-400 font-bold text-lg" id="statPages">0</div>
                            <div class="text-slate-300 text-xs">Pages</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product List -->
        <div class="lg:col-span-2">
            <div class="bg-slate-900/80 border border-white/8 rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white font-semibold text-sm flex items-center gap-2">
                        <i class="fas fa-box text-amber-400"></i>
                        Products
                    </h3>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="selectAll" class="w-4 h-4 rounded bg-slate-800 border-slate-600 text-amber-500 focus:ring-amber-500/50" <?= !$barcode_available ? 'disabled' : '' ?>>
                        <span class="text-slate-200 text-sm">Select All</span>
                    </label>
                </div>
                
                <div class="bg-slate-800/60 border border-white/8 rounded-lg overflow-hidden" style="max-height:520px;overflow-y:auto">
                    <?php if ($barcode_available): ?>
                    <div id="productList" class="divide-y divide-slate-600">
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center gap-2 text-slate-300">
                                <div class="w-5 h-5 border-2 border-amber-500/50 border-t-amber-500 rounded-full animate-spin"></div>
                                <span>Loading products...</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-8 text-center text-slate-400">
                        <i class="fas fa-ban text-xl mb-2"></i>
                        <p>Library unavailable</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Label Preview (hidden, used for print) -->
    <div id="labelPreview" style="display: none;"></div>

    <!-- Advanced Template Editor Modal -->
    <div id="templateEditorModal" class="fixed inset-0 bg-black/75  flex items-center justify-center z-[9999]" style="display:none">
        <div class="bg-[#0d1626] border border-white/10 rounded-xl w-full max-w-2xl mx-4 p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-base font-semibold text-white">Label Template Editor — <span id="editorSizeName" class="text-amber-400"></span></h3>
                <button type="button" id="closeTemplateEditorBtn" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-all">&times;</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Visible Fields</h4>
                    <ul id="fieldList" class="space-y-1 text-sm bg-black/30 p-3 rounded-lg border border-white/8 min-h-[160px]"></ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Live Preview</h4>
                    <div id="editorPreview" class="bg-white p-3 rounded-lg border border-slate-200 text-black text-xs overflow-hidden" style="min-height:120px"></div>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" id="cancelTemplateEditorBtn" class="px-4 py-2 text-sm rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition-all">Cancel</button>
                <button type="button" id="saveTemplateEditorBtn" class="px-4 py-2 text-sm rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition-all">Save for this Tenant</button>
            </div>
            <p class="text-[10px] text-slate-500 mt-2">Changes are saved per tenant and label size. Affects PDF and browser printing.</p>
        </div>
    </div>

    <!-- Print Preview Modal -->
    <div id="labelPrintPreviewModal" class="fixed inset-0 bg-slate-950/80  flex items-center justify-center z-[99999]" style="display:none">
        <div class="label-preview-dialog bg-[#0d1626] border border-white/10 rounded-xl w-full max-w-[1280px] mx-4 shadow-2xl overflow-hidden flex flex-col" style="height:min(92vh,920px)">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/8 bg-[#0b1120]/80">
                <div class="flex items-center gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-white">Print Preview &amp; Customizer</h3>
                        <p class="text-xs text-slate-400 mt-0.5" id="previewModalSizeName"></p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/15 border border-amber-500/30 text-amber-300">Live Preview</span>
                </div>
                <button type="button" id="closePreviewHeaderBtn" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white transition-all text-lg leading-none">&times;</button>
            </div>

            <div class="flex flex-1 overflow-hidden">

                <!-- Controls Sidebar -->
                <div id="printPreviewSidebar" class="label-preview-sidebar w-72 border-r border-white/8 bg-[#0b1120]/60 p-5 flex flex-col overflow-auto" style="max-height:calc(92vh - 130px)">
                    
                    <!-- Gap -->
                    <div class="mb-5">
                        <div class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Gap Between Labels</div>
                        <div class="flex items-center gap-3">
                            <input type="range" id="previewGapSlider" 
                                   min="0" max="8" step="0.5" value="2"
                                   class="flex-1 accent-amber-500">
                            <div class="w-14 text-right">
                                <span id="previewGapValue" class="font-mono text-sm font-semibold text-slate-200">2.0</span>
                                <span class="text-xs text-slate-500">mm</span>
                            </div>
                        </div>
                    </div>

                    <!-- Margin -->
                    <div class="mb-5">
                        <div class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Sheet Margin</div>
                        <div class="flex items-center gap-3">
                            <input type="range" id="previewMarginSlider" 
                                   min="0" max="12" step="0.5" value="5"
                                   class="flex-1 accent-amber-500">
                            <div class="w-14 text-right">
                                <span id="previewMarginValue" class="font-mono text-sm font-semibold text-slate-200">5.0</span>
                                <span class="text-xs text-slate-500">mm</span>
                            </div>
                        </div>
                    </div>

                    <!-- Font Scale -->
                    <div class="mb-5">
                        <div class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Font Size Scale</div>
                        <div class="flex items-center gap-3">
                            <input type="range" id="previewFontSlider" 
                                   min="0.6" max="1.8" step="0.1" value="1.0"
                                   class="flex-1 accent-amber-500">
                            <div class="w-14 text-right">
                                <span id="previewFontValue" class="font-mono text-sm font-semibold text-slate-200">1.0</span>
                                <span class="text-xs text-slate-500">×</span>
                            </div>
                        </div>
                    </div>

                    <!-- Field Visibility -->
                    <div class="mb-4">
                        <div class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Show Fields</div>
                        <div class="space-y-1.5 text-sm text-slate-300">
                            <label for="showStore" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="showStore" checked class="w-4 h-4 accent-amber-500">
                                <span>Store Name</span>
                            </label>
                            <label for="showName" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="showName" checked class="w-4 h-4 accent-amber-500">
                                <span>Product Name</span>
                            </label>
                            <label for="showPrice" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="showPrice" checked class="w-4 h-4 accent-amber-500">
                                <span>Price</span>
                            </label>
                            <label for="showSku" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="showSku" checked class="w-4 h-4 accent-amber-500">
                                <span>SKU / Barcode Text</span>
                            </label>
                            <label for="showBarcode" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="showBarcode" checked class="w-4 h-4 accent-amber-500">
                                <span>Barcode Image</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-auto pt-3 border-t border-white/8 text-[10px] text-slate-500">
                        Preview updates are instant. Scroll the sheet to inspect every label before printing.
                    </div>
                </div>

                <!-- Preview Area -->
                <div class="label-preview-canvas flex-1 p-5 overflow-auto bg-slate-200/90" id="printPreviewContainer">
                    <!-- Labels will be injected here by JS -->
                    <div class="text-center text-slate-400 py-12 text-sm" id="printPreviewLoading">
                        Loading preview...
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div id="printPreviewFooter" class="flex items-center justify-between px-6 py-4 border-t border-white/8 bg-[#0b1120]/80">
                <button type="button" id="closePrintPreviewBtn"
                        class="px-5 py-2 text-sm rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 hover:text-white transition-all">
                    Cancel
                </button>
                <div class="flex gap-3">
                    <button type="button" id="resetPreviewSettingsBtn"
                            class="px-4 py-2 text-sm rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition-all">
                        Reset All
                    </button>
                    <button type="button" id="printFromPreviewModalBtn"
                            class="flex items-center gap-2 px-6 py-2 text-sm rounded-lg bg-gradient-to-r from-amber-500 to-yellow-500 text-slate-900 font-bold hover:from-amber-400 hover:to-yellow-400 transition-all shadow-lg shadow-amber-500/25">
                        <i class="fas fa-print"></i> Print Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Physical label styles (mm units — cannot use Tailwind) ── */
/* --label-font-size-pt is unitless (e.g. 7); convert to px: 1pt ≈ 1.333px */
.label-sheet{page-break-after:always;padding:10mm;background:white;display:grid;gap:0;justify-content:start;align-content:start;grid-auto-flow:row;grid-auto-rows:auto;width:100%;box-sizing:border-box}

/* Preview wrapper: fills available width, scrolls if content is wider */
.label-preview-sheet-wrap{width:100%;overflow-x:auto;margin-bottom:12px;border-radius:4px}
.label{border:0.5mm solid #222;padding:1mm;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;gap:0.3mm;background:white;page-break-inside:avoid;position:relative;box-sizing:border-box;overflow:hidden;font-family:Arial,Helvetica,sans-serif}
.label-store{font-size:calc(var(--label-font-size-pt,7) * 1.04px);font-weight:700;text-align:center;word-break:break-word;line-height:1;color:#000;flex:0 0 auto;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.label-name{font-size:calc(var(--label-font-size-pt,7) * 1.15px);font-weight:700;text-align:center;line-height:1.05;margin:0;color:#000;flex:0 0 auto;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.label-price{text-align:center;font-size:calc(var(--label-font-size-pt,7) * 1.53px);font-weight:800;color:#000;line-height:1;flex:0 0 auto}
.label-barcode{text-align:center;margin:0.3mm 0;width:100%;min-height:calc(var(--barcode-slot-height-mm,12) * 1mm);height:calc(var(--barcode-slot-height-mm,12) * 1mm);max-height:calc(var(--barcode-slot-height-mm,12) * 1mm);color:#000;display:flex;align-items:center;justify-content:center;flex:0 0 auto;overflow:hidden}
.label-barcode svg{width:100%;height:100%;display:block}
.label-barcode img{max-width:100%;max-height:100%;width:100%;height:auto;object-fit:contain}
.label-sku{text-align:center;font-size:calc(var(--label-font-size-pt,7) * 0.88px);font-weight:600;color:#000;line-height:1;flex:0 0 auto;letter-spacing:0.3px}
.label-offer{position:absolute;top:-3px;right:-3px;background:#ef4444;color:white;padding:1px 3px;font-size:6px;font-weight:700;border-radius:1px;transform:rotate(15deg)}
.label-circle{border-radius:9999px!important;padding:4px!important;display:flex;align-items:center;justify-content:center;text-align:center}
.label-circle .label-store{font-size:5px;line-height:1}
.label-circle .label-name{font-size:6px;line-height:1;margin:1px 0}
.label-circle .label-price{font-size:8px}
.label-circle .label-barcode{min-height:12px;margin:1px 0}
.label-circle .label-sku{font-size:5px}

/* Screen preview container */
#labelPreview:not([style*="display: none"]){margin-top:1.5rem;padding:1rem;background:#f8fafc;border:2px solid #64748b;border-radius:12px}
body>#labelPrintRoot{display:none}

/* Modal sidebar needs grid since Tailwind's w-72 conflicts with flex layout at mobile */
@media(max-width:1024px){
    #labelPrintPreviewModal>.label-preview-dialog{width:calc(100vw - 1rem);height:calc(100vh - 1rem)!important;border-radius:16px}
    #labelPrintPreviewModal .flex.flex-1.overflow-hidden{flex-direction:column}
    #labelPrintPreviewModal #printPreviewSidebar{width:100%;max-width:none;border-right:0;border-bottom:1px solid rgba(255,255,255,.08);max-height:none!important}
}

/* ── Print media (cannot be Tailwind) ── */
@media print {
    @page {
        size: A4;
        margin: 4mm;
    }

    body, html {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body.printing-labels > * {
        visibility: hidden !important;
    }

    body.printing-labels > #labelPrintRoot,
    body.printing-labels > #labelPrintRoot * {
        visibility: visible !important;
    }

    body.printing-labels > #labelPrintRoot {
        display: block !important;
        position: absolute !important;
        inset: 0 !important;
        margin: 0 !important;
        padding: 4mm !important;
        width: 100%;
        background: white !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
    }

    body.printing-labels > #labelPrintRoot::before {
        display: none !important;
        content: none !important;
    }

    body.printing-labels > #labelPrintRoot .label-sheet {
        display: grid;
        page-break-after: always;
        background: white;
        border: none;
        box-shadow: none !important;
        margin: 0 0 2mm 0 !important;
        width: 100% !important;
    }

    body.printing-labels > #labelPrintRoot .label {
        border: 0.4mm solid #111;
        box-sizing: border-box;
        padding: 1.2mm;
        page-break-inside: avoid;
        break-inside: avoid;
        overflow: hidden;
    }

    body.printing-labels > #labelPrintRoot .label-barcode img {
        image-rendering: crisp-edges;
        image-rendering: -webkit-optimize-contrast;
        width: 100%;
        height: auto;
        max-height: 65%;
    }

    body.printing-labels > #labelPrintRoot .label-offer {
        font-size: 5pt !important;
    }
}
</style>

<script>
const BARCODE_AVAILABLE = <?= json_encode($barcode_available) ?>;
const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token) ?>';
const STORE_NAME = '<?= htmlspecialchars($store_name) ?>';

const DOMPDF_AVAILABLE = <?= json_encode($dompdf_available) ?>;
const ESCPOS_AVAILABLE = <?= json_encode($escpos_available) ?>;

// Tenant label settings from SettingsManager (used to delegate to LabelPrinter / LabelPrintService)
const TENANT_LABEL_SETTINGS = <?= json_encode($labelSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// Flag to indicate we are in delegation mode (new services + modern JS)
window.LABEL_PRINTER_DELEGATION_MODE = true;
window.LABEL_PRINTER_ENDPOINT = <?= json_encode(base_url('products/barcode_labels_generate.php')) ?>;

if (BARCODE_AVAILABLE) {
    // Delegation mode: the new LabelPrinterApp in label-printer.js is the only active controller
    console.log('%c[LabelPrinter] Delegation mode active - using enterprise services (LabelPrintService + SettingsManager)', 'color:#10b981');
}

// Expose CSRF for the new LabelPrinterApp
window.CSRF_TOKEN = '<?= htmlspecialchars($csrf_token ?? '') ?>';

// Expose library availability for UI warnings
window.DOMPDF_AVAILABLE = <?= json_encode($dompdf_available) ?>;
window.ESCPOS_AVAILABLE = <?= json_encode($escpos_available) ?>;

</script>

<!-- Modern Label Printer App (delegates to LabelPrintService / SettingsManager) -->
<script src="<?php echo base_url('assets/js/label-printer.js'); ?>?v=<?php echo filemtime(__DIR__ . '/../../assets/js/label-printer.js') ?: time(); ?>"></script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
