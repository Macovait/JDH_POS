<?php
/**
 * Custom Report Builder - PURE TAILWIND CSS
 * Drag-and-drop report creation with advanced filtering and visualization
 */

$page_title = 'Custom Report Builder';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
if (!check_permission('reports.view') && !is_super_admin()) {
    enforce_permission('reports.view');
}

$pdo = get_db_connection();
$tenant_id = (int) get_current_tenant_id();
$user_id = (int) get_current_user_id();
$current_branch_id = (int) get_current_branch_id();
$currency = get_tenant_currency() ?? 'KES';
$success = null;
$error = null;
$current_report = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_report'])) {
        $report_id = save_custom_report($pdo, $tenant_id, $user_id, $_POST);
        if ($report_id) {
            $success = 'Report saved successfully';
        } else {
            $error = 'Failed to save report';
        }
    } elseif (isset($_POST['delete_report'])) {
        $deleted = delete_custom_report($pdo, $tenant_id, $_POST['report_id'], $user_id);
        if ($deleted) {
            $success = 'Report deleted successfully';
        } else {
            $error = 'Failed to delete report';
        }
    } elseif (isset($_POST['run_report'])) {
        $report_data = run_custom_report($pdo, $tenant_id, $_POST);
        $current_report = $_POST;
    }
}

// Get saved reports
$saved_reports = get_saved_reports($pdo, $tenant_id, $user_id, $current_branch_id);

// Get available data sources and fields
$data_sources = get_data_sources();
$report_templates = get_report_templates();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="space-y-4">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-sliders-h text-xs"></i>
                <span>Advanced Analytics</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Custom Report Builder</h1>
            <p class="text-sm text-slate-500 mt-1">Create custom reports with drag-and-drop interface</p>
        </div>
        <div class="flex gap-2">
            <button onclick="newReport()" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                <i class="fas fa-plus"></i> New Report
            </button>
            <button onclick="scrollToSavedReports()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-all">
                <i class="fas fa-folder-open"></i> Saved Reports
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4">
            <div class="flex items-center gap-3 text-emerald-400">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4">
            <div class="flex items-center gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Report Builder Interface -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-3">
        
        <!-- Data Sources Panel -->
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-slate-800/40  border border-slate-700 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-database text-blue-400"></i>
                    Data Sources
                </h3>
                <div class="space-y-3">
                    <?php foreach ($data_sources as $source): ?>
                        <div class="data-source-item bg-slate-800/60 rounded-lg p-3 cursor-pointer hover:bg-slate-700 transition-all"
                             data-source="<?php echo $source['id']; ?>">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas <?php echo $source['icon']; ?> text-<?php echo $source['color']; ?>-400"></i>
                                <span class="text-white font-medium text-sm"><?php echo htmlspecialchars($source['name']); ?></span>
                            </div>
                            <p class="text-slate-500 text-xs"><?php echo htmlspecialchars($source['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Report Templates -->
            <div class="bg-slate-800/40  border border-slate-700 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-file-alt text-purple-400"></i>
                    Templates
                </h3>
                <div class="space-y-2">
                    <?php foreach ($report_templates as $template): ?>
                        <button onclick="loadTemplate('<?php echo $template['id']; ?>')"
                                class="w-full text-left bg-slate-800/60 hover:bg-slate-700 rounded-lg p-3 transition-all">
                            <div class="text-white font-medium text-sm"><?php echo htmlspecialchars($template['name']); ?></div>
                            <div class="text-slate-500 text-xs"><?php echo htmlspecialchars($template['description']); ?></div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Report Builder Canvas -->
        <div class="lg:col-span-3">
            <div class="bg-slate-800/40  border border-slate-700 rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-chart-bar text-amber-400"></i>
                        Report Builder
                    </h3>
                    <div class="flex gap-2">
                        <button onclick="previewReport()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-300 text-sm hover:bg-slate-600 transition-all">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button onclick="saveReportForm()" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>

                <!-- Report Configuration -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Report Name</label>
                        <input type="text" id="report_name" placeholder="Enter report name"
                               class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Report Type</label>
                        <select id="report_type" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="table">Data Table</option>
                            <option value="chart">Chart</option>
                            <option value="summary">Summary</option>
                        </select>
                    </div>
                </div>

                <!-- Drag and Drop Areas -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-6">
                    
                    <!-- Available Fields -->
                    <div class="bg-slate-800/60 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                            <i class="fas fa-list text-blue-400"></i>
                            Available Fields
                        </h4>
                        <div id="available-fields" class="space-y-2 min-h-[200px]">
                            <p class="text-slate-500 text-sm text-center py-8">Select a data source to view available fields</p>
                        </div>
                    </div>

                    <!-- Selected Fields -->
                    <div class="bg-slate-800/60 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                            <i class="fas fa-check text-emerald-400"></i>
                            Selected Fields
                        </h4>
                        <div id="selected-fields" class="drop-zone space-y-2 min-h-[200px] border-2 border-dashed border-slate-600 rounded-lg p-4">
                            <p class="text-slate-500 text-sm text-center py-8">Drag fields here</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-slate-800/60 rounded-lg p-4 mb-6">
                    <h4 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-filter text-purple-400"></i>
                        Filters
                    </h4>
                    <div id="filters-container" class="space-y-3">
                        <div class="filter-row flex flex-wrap gap-2 items-center">
                            <select class="filter-field px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                                <option value="">Select field...</option>
                            </select>
                            <select class="filter-operator px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                                <option value="equals">Equals</option>
                                <option value="contains">Contains</option>
                                <option value="greater">Greater than</option>
                                <option value="less">Less than</option>
                            </select>
                            <input type="text" placeholder="Value" class="filter-value flex-1 px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <button onclick="addFilter()" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Report Preview -->
                <div id="report-preview" class="bg-slate-800/60 rounded-lg p-4 hidden">
                    <h4 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-eye text-amber-400"></i>
                        Report Preview
                    </h4>
                    <div id="preview-content" class="bg-slate-900/50 rounded-lg p-4">
                        <!-- Report content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Saved Reports Section -->
    <div id="saved-reports-section" class="bg-slate-800/40  border border-slate-700 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-folder text-emerald-400"></i>
            Saved Reports
        </h3>

        <?php if (empty($saved_reports)): ?>
            <div class="text-center py-12">
                <i class="fas fa-folder-open text-slate-600 text-4xl mb-4 block"></i>
                <p class="text-slate-500">No saved reports yet</p>
                <p class="text-sm text-slate-600 mt-1">Create and save your first custom report</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($saved_reports as $report): ?>
                    <div class="bg-slate-800/60 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-white font-medium text-sm"><?php echo htmlspecialchars($report['name']); ?></h4>
                            <div class="flex gap-2">
                                <button onclick="loadReport(<?php echo $report['id']; ?>)" class="text-blue-400 hover:text-blue-300 text-xs" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="runReport(<?php echo $report['id']; ?>)" class="text-emerald-400 hover:text-emerald-300 text-xs" title="Run">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button onclick="deleteReport(<?php echo $report['id']; ?>)" class="text-red-400 hover:text-red-300 text-xs" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-slate-500 text-xs mb-2"><?php echo htmlspecialchars($report['description'] ?: 'No description'); ?></p>
                        <div class="flex items-center gap-3 text-xs text-slate-600">
                            <span class="capitalize"><?php echo htmlspecialchars($report['type']); ?></span>
                            <span><?php echo date('M d, Y', strtotime($report['updated_at'] ?? $report['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Global variables
let selectedDataSource = null;
let selectedFields = [];
let filters = [];
let draggedElement = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initDragAndDrop();
    initDataSources();
});

function initDataSources() {
    document.querySelectorAll('.data-source-item').forEach(item => {
        item.addEventListener('click', function() {
            const sourceId = this.dataset.source;
            selectDataSource(sourceId);
        });
    });
}

function selectDataSource(sourceId) {
    selectedDataSource = sourceId;

    document.querySelectorAll('.data-source-item').forEach(item => {
        item.classList.remove('ring-2', 'ring-amber-500');
    });
    document.querySelector(`[data-source="${sourceId}"]`).classList.add('ring-2', 'ring-amber-500');

    loadAvailableFields(sourceId);
}

function loadAvailableFields(sourceId) {
    const fieldsContainer = document.getElementById('available-fields');
    
    const mockFields = {
        'sales': [
            { id: 'receipt_number', name: 'Receipt Number', type: 'string' },
            { id: 'final_amount', name: 'Total Amount', type: 'number' },
            { id: 'created_at', name: 'Date', type: 'date' },
            { id: 'customer_name', name: 'Customer', type: 'string' },
            { id: 'payment_method', name: 'Payment Method', type: 'string' }
        ],
        'products': [
            { id: 'name', name: 'Product Name', type: 'string' },
            { id: 'sku', name: 'SKU', type: 'string' },
            { id: 'cost_price', name: 'Cost Price', type: 'number' },
            { id: 'selling_price', name: 'Selling Price', type: 'number' },
            { id: 'stock', name: 'Current Stock', type: 'number' }
        ],
        'customers': [
            { id: 'name', name: 'Customer Name', type: 'string' },
            { id: 'email', name: 'Email', type: 'string' },
            { id: 'phone', name: 'Phone', type: 'string' },
            { id: 'total_orders', name: 'Total Orders', type: 'number' },
            { id: 'total_spent', name: 'Total Spent', type: 'number' }
        ],
        'inventory': [
            { id: 'product_name', name: 'Product Name', type: 'string' },
            { id: 'current_stock', name: 'Current Stock', type: 'number' },
            { id: 'reorder_level', name: 'Reorder Level', type: 'number' },
            { id: 'last_updated', name: 'Last Updated', type: 'date' }
        ]
    };

    const fields = mockFields[sourceId] || [];
    fieldsContainer.innerHTML = '';

    fields.forEach(field => {
        const fieldElement = document.createElement('div');
        fieldElement.className = 'field-item draggable bg-slate-700 rounded-lg p-2 cursor-move hover:bg-slate-600 transition-all';
        fieldElement.setAttribute('draggable', 'true');
        fieldElement.dataset.fieldId = field.id;
        fieldElement.dataset.fieldName = field.name;
        fieldElement.dataset.fieldType = field.type;

        fieldElement.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-grip-vertical text-slate-500"></i>
                <span class="text-white text-sm">${field.name}</span>
                <span class="text-xs text-slate-500 ml-auto capitalize">${field.type}</span>
            </div>
        `;

        fieldsContainer.appendChild(fieldElement);
    });
}

function initDragAndDrop() {
    document.addEventListener('dragstart', function(e) {
        if (e.target.closest('.field-item')) {
            draggedElement = e.target.closest('.field-item');
            draggedElement.classList.add('opacity-50');
        }
    });

    document.addEventListener('dragend', function(e) {
        if (draggedElement) {
            draggedElement.classList.remove('opacity-50');
            draggedElement = null;
        }
        document.querySelectorAll('.drop-zone').forEach(zone => {
            zone.classList.remove('border-amber-500', 'bg-amber-500/10');
        });
    });

    document.addEventListener('dragover', function(e) {
        e.preventDefault();
        if (e.target.closest('.drop-zone')) {
            e.target.closest('.drop-zone').classList.add('border-amber-500', 'bg-amber-500/10');
        }
    });

    document.addEventListener('dragleave', function(e) {
        if (e.target.closest('.drop-zone')) {
            e.target.closest('.drop-zone').classList.remove('border-amber-500', 'bg-amber-500/10');
        }
    });

    document.addEventListener('drop', function(e) {
        e.preventDefault();
        const dropZone = e.target.closest('.drop-zone');
        if (dropZone && draggedElement) {
            dropZone.classList.remove('border-amber-500', 'bg-amber-500/10');
            addSelectedField(draggedElement);
        }
    });
}

function addSelectedField(fieldElement) {
    const fieldId = fieldElement.dataset.fieldId;
    const fieldName = fieldElement.dataset.fieldName;
    const fieldType = fieldElement.dataset.fieldType;

    if (selectedFields.find(f => f.id === fieldId)) return;

    selectedFields.push({ id: fieldId, name: fieldName, type: fieldType });
    renderSelectedFields();
}

function renderSelectedFields() {
    const selectedContainer = document.getElementById('selected-fields');
    selectedContainer.innerHTML = '';

    if (selectedFields.length === 0) {
        selectedContainer.innerHTML = '<p class="text-slate-500 text-sm text-center py-8">Drag fields here</p>';
        return;
    }

    selectedFields.forEach(field => {
        const fieldElement = document.createElement('div');
        fieldElement.className = 'bg-amber-500/10 border border-amber-500/30 rounded-lg p-2 flex items-center justify-between';
        fieldElement.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-grip-vertical text-amber-400"></i>
                <span class="text-white text-sm">${field.name}</span>
                <span class="text-xs text-amber-400 capitalize">${field.type}</span>
            </div>
            <button onclick="removeField('${field.id}')" class="text-red-400 hover:text-red-300">
                <i class="fas fa-times"></i>
            </button>
        `;
        selectedContainer.appendChild(fieldElement);
    });
}

function removeField(fieldId) {
    selectedFields = selectedFields.filter(f => f.id !== fieldId);
    renderSelectedFields();
}

function newReport() {
    document.getElementById('report_name').value = '';
    document.getElementById('report_type').value = 'table';
    selectedDataSource = null;
    selectedFields = [];
    filters = [];

    document.getElementById('available-fields').innerHTML = '<p class="text-slate-500 text-sm text-center py-8">Select a data source to view available fields</p>';
    document.getElementById('selected-fields').innerHTML = '<p class="text-slate-500 text-sm text-center py-8">Drag fields here</p>';
    document.getElementById('report-preview').classList.add('hidden');

    document.querySelectorAll('.data-source-item').forEach(item => {
        item.classList.remove('ring-2', 'ring-amber-500');
    });
}

function saveReportForm() {
    const reportName = document.getElementById('report_name').value;
    if (!reportName) {
        showToast('Please enter a report name', 'error');
        return;
    }

    if (!selectedDataSource || selectedFields.length === 0) {
        showToast('Please select a data source and at least one field', 'error');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="save_report" value="1">
        <input type="hidden" name="report_name" value="${reportName}">
        <input type="hidden" name="report_type" value="${document.getElementById('report_type').value}">
        <input type="hidden" name="data_source" value="${selectedDataSource}">
        <input type="hidden" name="selected_fields" value='${JSON.stringify(selectedFields)}'>
        <input type="hidden" name="filters" value='${JSON.stringify(filters)}'>
    `;
    document.body.appendChild(form);
    form.submit();
}

function previewReport() {
    if (!selectedDataSource || selectedFields.length === 0) {
        showToast('Please select a data source and fields first', 'error');
        return;
    }

    document.getElementById('report-preview').classList.remove('hidden');
    const previewContent = document.getElementById('preview-content');
    const reportType = document.getElementById('report_type').value;

    if (reportType === 'table') {
        previewContent.innerHTML = generateTablePreview();
    } else if (reportType === 'chart') {
        previewContent.innerHTML = generateChartPreview();
    } else {
        previewContent.innerHTML = generateSummaryPreview();
    }
}

function generateTablePreview() {
    let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-800"><tr>';

    selectedFields.forEach(field => {
        html += `<th class="text-left py-2 px-3 text-slate-400 text-xs uppercase tracking-wider">${field.name}</th>`;
    });

    html += '<tr></thead><tbody class="divide-y divide-slate-700">';

    for (let i = 0; i < 5; i++) {
        html += '<tr>';
        selectedFields.forEach(field => {
            let value = '';
            if (field.type === 'string') value = `Sample ${field.name} ${i + 1}`;
            else if (field.type === 'number') value = (Math.random() * 1000).toFixed(2);
            else if (field.type === 'date') value = new Date().toLocaleDateString();
            html += `<td class="py-2 px-3 text-slate-300">${value}</td>`;
        });
        html += '</tr>';
    }

    html += '</tbody></table></div>';
    return html;
}

function generateChartPreview() {
    setTimeout(() => {
        const ctx = document.getElementById('previewChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Sample 1', 'Sample 2', 'Sample 3', 'Sample 4', 'Sample 5'],
                    datasets: [{
                        label: 'Preview Data',
                        data: [65, 59, 80, 81, 56],
                        backgroundColor: '#fbbf24',
                        borderRadius: 4
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { labels: { color: '#94a3b8' } } 
                    } 
                }
            });
        }
    }, 100);
    
    return `
        <div class="text-center py-8">
            <div class="h-64">
                <canvas id="previewChart"></canvas>
            </div>
            <p class="text-slate-500 text-sm mt-4">Chart preview - ${selectedFields.length} fields selected</p>
        </div>
    `;
}

function generateSummaryPreview() {
    return `
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-800 rounded-lg p-4 text-center">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Total Records</div>
                <div class="text-2xl font-bold text-white mt-1">1,234</div>
            </div>
            <div class="bg-slate-800 rounded-lg p-4 text-center">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Total Value</div>
                <div class="text-2xl font-bold text-emerald-400 mt-1">KES 45,678</div>
            </div>
            <div class="bg-slate-800 rounded-lg p-4 text-center">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Average</div>
                <div class="text-2xl font-bold text-amber-400 mt-1">KES 37.02</div>
            </div>
            <div class="bg-slate-800 rounded-lg p-4 text-center">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Date Range</div>
                <div class="text-sm font-bold text-white mt-1"><?php echo date('M d'); ?> - <?php echo date('M d', strtotime('+30 days')); ?></div>
            </div>
        </div>
    `;
}

function addFilter() {
    const filterContainer = document.getElementById('filters-container');
    const filterRow = document.createElement('div');
    filterRow.className = 'filter-row flex flex-wrap gap-2 items-center';
    filterRow.innerHTML = `
        <select class="filter-field px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm">
            <option value="">Select field...</option>
            ${selectedFields.map(f => `<option value="${f.id}">${f.name}</option>`).join('')}
        </select>
        <select class="filter-operator px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm">
            <option value="equals">Equals</option>
            <option value="contains">Contains</option>
            <option value="greater">Greater than</option>
            <option value="less">Less than</option>
        </select>
        <input type="text" placeholder="Value" class="filter-value flex-1 px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-300 text-sm">
        <button onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-500/15 border border-red-500/30 rounded-lg text-red-400 text-sm hover:bg-red-500/25 transition-all">
            <i class="fas fa-trash"></i>
        </button>
    `;
    filterContainer.appendChild(filterRow);
}

function loadTemplate(templateId) {
    showToast('Template loading - feature coming soon', 'info');
}

function scrollToSavedReports() {
    document.getElementById('saved-reports-section').scrollIntoView({ behavior: 'smooth' });
}

function loadReport(reportId) {
    showToast('Loading report - feature coming soon', 'info');
}

function runReport(reportId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="run_report" value="1"><input type="hidden" name="report_id" value="${reportId}">`;
    document.body.appendChild(form);
    form.submit();
}

function deleteReport(reportId) {
    if (confirm('Are you sure you want to delete this report?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_report" value="1">
            <input type="hidden" name="report_id" value="${reportId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-emerald-500/90' : type === 'error' ? 'bg-red-500/90' : 'bg-amber-500/90';
    toast.className = `fixed top-20 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-sm flex items-center gap-2 ${bgColor} text-white`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php
// Helper functions
function get_data_sources(): array {
    return [
        ['id' => 'sales', 'name' => 'Sales', 'description' => 'Sales transactions and receipts', 'icon' => 'fa-shopping-cart', 'color' => 'emerald'],
        ['id' => 'products', 'name' => 'Products', 'description' => 'Product catalog and inventory', 'icon' => 'fa-box', 'color' => 'blue'],
        ['id' => 'customers', 'name' => 'Customers', 'description' => 'Customer data and behavior', 'icon' => 'fa-users', 'color' => 'purple'],
        ['id' => 'inventory', 'name' => 'Inventory', 'description' => 'Stock levels and movements', 'icon' => 'fa-warehouse', 'color' => 'amber']
    ];
}

function get_report_templates(): array {
    return [
        ['id' => 'sales_summary', 'name' => 'Sales Summary', 'description' => 'Daily sales totals and trends'],
        ['id' => 'inventory_status', 'name' => 'Inventory Status', 'description' => 'Current stock levels and alerts'],
        ['id' => 'customer_analysis', 'name' => 'Customer Analysis', 'description' => 'Customer purchase patterns'],
        ['id' => 'profit_loss', 'name' => 'Profit & Loss', 'description' => 'Financial performance report']
    ];
}

function get_saved_reports(PDO $pdo, int $tenant_id, int $user_id, int $branch_id) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'custom_reports'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
        $stmt = $pdo->prepare("SELECT * FROM custom_reports WHERE tenant_id = ? AND (user_id = ? OR is_public = 1) ORDER BY updated_at DESC LIMIT 20");
        $stmt->execute([$tenant_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching saved reports: " . $e->getMessage());
        return [];
    }
}

function save_custom_report(PDO $pdo, int $tenant_id, int $user_id, array $data): int|false {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'custom_reports'");
        if ($stmt->rowCount() == 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS custom_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                type VARCHAR(50) DEFAULT 'table',
                data_source VARCHAR(100),
                selected_fields JSON,
                filters JSON,
                config JSON,
                is_public TINYINT DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id)
            )";
            $pdo->exec($create_sql);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO custom_reports (
                tenant_id, user_id, name, description, type, data_source,
                selected_fields, filters, config, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenant_id, $user_id, $data['report_name'], $data['description'] ?? '',
            $data['report_type'], $data['data_source'],
            json_encode($data['selected_fields'] ?? []),
            json_encode($data['filters'] ?? []),
            json_encode($data['config'] ?? [])
        ]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error saving custom report: " . $e->getMessage());
        return false;
    }
}

function delete_custom_report(PDO $pdo, int $tenant_id, int $report_id, int $user_id): bool {
    try {
        $stmt = $pdo->prepare("DELETE FROM custom_reports WHERE id = ? AND tenant_id = ? AND user_id = ?");
        return $stmt->execute([$report_id, $tenant_id, $user_id]);
    } catch (PDOException $e) {
        error_log("Error deleting custom report: " . $e->getMessage());
        return false;
    }
}

function run_custom_report(PDO $pdo, int $tenant_id, array $data): array {
    return ['data' => [], 'total' => 0];
}

$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>