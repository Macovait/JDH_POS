// assets/js/label-printer.js
// Modern Label Printer App - delegates to LabelPrintService / barcode_labels_generate.php
// Replaces the monolithic inline JS previously in barcode_labels.php

/**
 * Utility: Ensure any form field (input/select/textarea) has a unique id and name.
 * Prevents Chrome autofill + a11y warnings when creating dynamic fields.
 */
function ensureFormFieldAttributes(el, baseName = 'field') {
    if (!el.id) {
        el.id = baseName + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    if (!el.name) {
        el.name = el.id;
    }
    return el;
}

function getLabelPrinterEndpoint() {
    return window.LABEL_PRINTER_ENDPOINT || 'barcode_labels_generate.php';
}

async function postLabelPrinterJson(params) {
    const response = await fetch(getLabelPrinterEndpoint(), {
        method: 'POST',
        body: params
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        const message = data && data.error ? data.error : 'Request failed';
        throw new Error(message);
    }

    return data || {};
}

// Label size definitions (moved here from inline script for self-containment)
const LABEL_SIZES = {
    'k5':  { w: 38, h: 16, cols: 5,  perPage: 40,  shape: 'rect',   label: 'K5 - 38×16mm' },
    'k22': { w: 51, h: 25, cols: 4,  perPage: 24,  shape: 'rect',   label: 'K22 - 51×25mm' },
    'KA2': { w: 51, h: 13, cols: 4,  perPage: 32,  shape: 'rect',   label: 'KA2 - 51×13mm' },
    'K38': { w: 76, h: 38, cols: 2,  perPage: 12,  shape: 'rect',   label: 'K38 - 76×38mm' },
    'K11': { w: 19, h: 13, cols: 10, perPage: 80,  shape: 'rect',   label: 'K11 - 19×13mm' },
    'KA1': { w: 25, h: 19, cols: 8,  perPage: 48,  shape: 'rect',   label: 'KA1 - 25×19mm' },
    'K27': { w: 51, h: 38, cols: 4,  perPage: 20,  shape: 'rect',   label: 'K27 - 51×38mm' },
    'k36': { w: 76, h: 51, cols: 2,  perPage: 8,   shape: 'rect',   label: 'K36 - 76×51mm' },
    'K05': { w: 13, h: 13, cols: 14, perPage: 140, shape: 'circle', label: 'K05 - 13mm DIA' },
    'K09': { w: 22, h: 22, cols: 9,  perPage: 72,  shape: 'circle', label: 'K09 - 22mm DIA' },
    'K15': { w: 49, h: 49, cols: 4,  perPage: 16,  shape: 'circle', label: 'K15 - 49mm DIA' }
};

class LabelPrinterApp {
    constructor() {
        this.selectedProducts = new Map();
        this.products = [];
        this.barcodes = {};
        this.config = window.labelPrinterConfig || {};
        this.tenantSettings = window.TENANT_LABEL_SETTINGS || {};
        this.previewMirrorTargetId = 'labelPrintRoot';
        this.isPrintModeActive = false;
        this.init();
    }

    init() {
        this.mountPreviewModal();
        this.mountPrintPreview();
        this.bindPrintLifecycle();
        this.bindEvents();
        // Wait for DOM and then load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.loadProducts());
        } else {
            this.loadProducts();
        }
    }

    mountPreviewModal() {
        const modal = document.getElementById('labelPrintPreviewModal');
        if (!modal || !document.body || modal.parentElement === document.body) {
            return;
        }

        document.body.appendChild(modal);
    }

    mountPrintPreview() {
        if (!document.body) {
            return null;
        }

        let preview = document.getElementById(this.previewMirrorTargetId);
        if (!preview) {
            preview = document.createElement('div');
            preview.id = this.previewMirrorTargetId;
            preview.style.display = 'none';
            document.body.appendChild(preview);
        } else if (preview.parentElement !== document.body) {
            document.body.appendChild(preview);
        }

        return preview;
    }

    bindPrintLifecycle() {
        if (this._printLifecycleBound) {
            return;
        }

        this._printLifecycleBound = true;
        window.addEventListener('afterprint', () => this.finishPrintMode());
    }

    setPreviewModalState(isOpen) {
        const modal = document.getElementById('labelPrintPreviewModal');
        if (!modal) {
            return;
        }

        this.mountPreviewModal();

        modal.style.position = 'fixed';
        modal.style.left = '0';
        modal.style.top = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.width = '100vw';
        modal.style.height = '100vh';
        modal.style.zIndex = '2147483647';

        if (isOpen) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            modal.style.display = 'none';
            if (!this.isPrintModeActive) {
                document.body.style.overflow = '';
            }
        }
    }

    preparePrintablePreview(container) {
        const target = this.mountPrintPreview();
        if (!target || !container) {
            return null;
        }

        target.innerHTML = '';

        const sheets = Array.from(container.querySelectorAll('.label-sheet'));
        sheets.forEach(sheet => {
            const clone = sheet.cloneNode(true);
            clone.style.transform = 'none';
            clone.style.transformOrigin = '';
            clone.style.width = '';
            clone.style.maxWidth = '';
            clone.style.marginBottom = '0';
            target.appendChild(clone);
        });

        return target;
    }

    debounce(fn, delay = 200) {
        let timeoutId = null;
        return (...args) => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            timeoutId = window.setTimeout(() => fn.apply(this, args), delay);
        };
    }

    startPrintMode(container) {
        const preview = this.preparePrintablePreview(container);
        if (!preview) {
            return false;
        }

        document.body.classList.add('printing-labels');
        document.body.style.overflow = 'hidden';
        preview.style.display = 'block';
        this.isPrintModeActive = true;
        return true;
    }

    finishPrintMode() {
        const preview = document.getElementById(this.previewMirrorTargetId);

        document.body.classList.remove('printing-labels');
        if (!document.getElementById('labelPrintPreviewModal') || document.getElementById('labelPrintPreviewModal').style.display === 'none') {
            document.body.style.overflow = '';
        }

        if (preview) {
            preview.style.display = 'none';
        }

        this.isPrintModeActive = false;
    }

    bindEvents() {
        const search = document.getElementById('search');
        const category = document.getElementById('category');
        const labelSize = document.getElementById('label_size');

        if (search) {
            search.addEventListener('input', this.debounce(() => this.loadProducts(), 400));
        }
        if (category) {
            category.addEventListener('change', () => this.loadProducts());
        }
        if (labelSize) {
            labelSize.addEventListener('change', () => {
                // Persist choice to tenant settings if desired
                if (window.updateLabelSetting) {
                    window.updateLabelSetting('auto_print_label_size', labelSize.value);
                }
            });
        }

        // Delegate buttons
        const generateBtn = document.getElementById('generateBtn');
        const pdfBtn = document.getElementById('pdfBtn');
        const reprintBtn = document.getElementById('reprintBtn');
        const templateEditorBtn = document.getElementById('templateEditorBtn');

        if (generateBtn) generateBtn.addEventListener('click', () => this.openPrintPreviewModal());

        if (pdfBtn) {
            pdfBtn.addEventListener('click', (e) => {
                if (typeof window.DOMPDF_AVAILABLE !== 'undefined' && window.DOMPDF_AVAILABLE === false) {
                    e.preventDefault();
                    if (typeof window.showToast === 'function') {
                        window.showToast('PDF export is unavailable. Please contact your administrator.', 'error');
                    } else {
                        const el = document.getElementById('labelPrinterStatus');
                        if (el) { el.textContent = 'PDF export unavailable. Contact your administrator.'; el.classList.remove('hidden'); }
                    }
                    return;
                }
                this.printSelected('pdf');
            });
        }

        if (reprintBtn) reprintBtn.addEventListener('click', () => this.reprintLastLabels());
        if (templateEditorBtn) templateEditorBtn.addEventListener('click', () => this.openTemplateEditor());

        const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
        const batchImportFile = document.getElementById('batchImportFile');
        const batchImportFileBtn = document.getElementById('batchImportFileBtn');
        const batchImportBtn = document.getElementById('batchImportBtn');
        const batchImportCategory = document.getElementById('batchImportCategory');
        const batchPrintBtn = document.getElementById('batchPrintBtn');
        const autoPrintOnSale = document.getElementById('autoPrintOnSale');

        if (downloadTemplateBtn) {
            downloadTemplateBtn.addEventListener('click', () => this.downloadCsvTemplate());
        }
        if (batchImportFileBtn && batchImportFile) {
            batchImportFileBtn.addEventListener('click', () => batchImportFile.click());
            batchImportFile.addEventListener('change', (event) => this.handleBatchImportFileSelected(event));
        }
        if (batchImportBtn) {
            batchImportBtn.addEventListener('click', () => this.submitBatchImport(batchImportCategory));
        }
        if (batchPrintBtn) {
            batchPrintBtn.addEventListener('click', () => this.batchPrintSelected());
        }
        if (autoPrintOnSale) {
            autoPrintOnSale.addEventListener('change', (e) => {
                if (typeof window.saveAutoPrintSetting === 'function') {
                    window.saveAutoPrintSetting(e.target.checked);
                }
            });
        }

        const closeTemplateEditorBtn = document.getElementById('closeTemplateEditorBtn');
        const cancelTemplateEditorBtn = document.getElementById('cancelTemplateEditorBtn');
        const saveTemplateEditorBtn = document.getElementById('saveTemplateEditorBtn');
        const closePrintPreviewBtn = document.getElementById('closePrintPreviewBtn');
        const closePreviewHeaderBtn = document.getElementById('closePreviewHeaderBtn');
        const resetPreviewSettingsBtn = document.getElementById('resetPreviewSettingsBtn');
        const printFromPreviewModalBtn = document.getElementById('printFromPreviewModalBtn');
        const copyPicqerBtn = document.getElementById('copyPicqerBtn');
        const copyDompdfBtn = document.getElementById('copyDompdfBtn');
        const copyEscposBtn = document.getElementById('copyEscposBtn');

        if (closeTemplateEditorBtn) closeTemplateEditorBtn.addEventListener('click', () => this.closeTemplateEditor());
        if (cancelTemplateEditorBtn) cancelTemplateEditorBtn.addEventListener('click', () => this.closeTemplateEditor());
        if (saveTemplateEditorBtn) saveTemplateEditorBtn.addEventListener('click', () => this.saveTemplateConfig());
        if (closePrintPreviewBtn) closePrintPreviewBtn.addEventListener('click', () => this.closePrintPreviewModal());
        if (closePreviewHeaderBtn) closePreviewHeaderBtn.addEventListener('click', () => this.closePrintPreviewModal());
        if (resetPreviewSettingsBtn) resetPreviewSettingsBtn.addEventListener('click', () => this.resetPreviewSettings());
        if (printFromPreviewModalBtn) printFromPreviewModalBtn.addEventListener('click', () => this.printFromPreviewModal());

        // Copy-to-clipboard helpers for quick install commands
        const attachCopyHandler = (btn) => {
            if (!btn) return;
            btn.addEventListener('click', (e) => {
                const txt = btn.dataset.copyText || '';
                if (!txt) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(txt).then(() => {
                        const old = btn.textContent;
                        btn.textContent = 'Copied!';
                        setTimeout(() => btn.textContent = old, 800);
                    }).catch(() => {
                        window.prompt('Copy this command', txt);
                    });
                } else {
                    window.prompt('Copy this command', txt);
                }
            });
        };

        attachCopyHandler(copyPicqerBtn);
        attachCopyHandler(copyDompdfBtn);
        attachCopyHandler(copyEscposBtn);

        // Fallback delegated click handlers for modal controls in case direct listeners fail
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (target.closest && target.closest('#resetPreviewSettingsBtn')) {
                event.preventDefault();
                this.resetPreviewSettings();
            }
            if (target.closest && target.closest('#printFromPreviewModalBtn')) {
                event.preventDefault();
                this.printFromPreviewModal();
            }
            if (target.closest && target.closest('#closePrintPreviewBtn')) {
                event.preventDefault();
                this.closePrintPreviewModal();
            }
            if (target.closest && target.closest('.retry-load-products')) {
                event.preventDefault();
                this.loadProducts();
            }
        });

        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', (e) => this.toggleSelectAll(e.target.checked));
        }

        window.addEventListener('resize', this.debounce(() => {
            const container = document.getElementById('printPreviewContainer');
            if (container && container.children.length) {
                this.refreshPreviewPresentation(container);
            }
        }, 120));
    }

    getStatusElement() {
        return document.getElementById('labelPrinterStatus');
    }

    showStatusMessage(message, type = 'info', timeout = 6000) {
        const status = this.getStatusElement();
        if (!status) return;

        const typeClasses = {
            info: 'border-slate-600 bg-slate-900 text-slate-300',
            success: 'border-emerald-500 bg-emerald-950 text-emerald-300',
            warning: 'border-amber-500 bg-amber-950 text-amber-300',
            error: 'border-rose-500 bg-rose-950 text-rose-300',
        };

        status.className = 'mt-4 rounded border px-3 py-2 text-xs ' + (typeClasses[type] || typeClasses.info);
        status.innerHTML = message;
        status.classList.remove('hidden');

        if (timeout > 0) {
            clearTimeout(this._statusTimeout);
            this._statusTimeout = setTimeout(() => this.clearStatusMessage(), timeout);
        }
    }

    clearStatusMessage() {
        const status = this.getStatusElement();
        if (!status) return;
        status.classList.add('hidden');
        status.innerHTML = '';
    }

    async downloadCsvTemplate() {
        try {
            const body = new URLSearchParams();
            body.append('action', 'download_csv_template');
            body.append('csrf_token', this.getCsrfToken());

            const response = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body });
            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || 'Failed to download template');
            }
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'products-template.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            this.showStatusMessage('CSV template downloaded.', 'success', 4000);
        } catch (err) {
            console.error('downloadCsvTemplate error', err);
            this.showStatusMessage('Error downloading template: ' + err.message, 'error', 8000);
        }
    }

    handleBatchImportFileSelected(event) {
        const file = event.target.files[0];
        const fileBtn = document.getElementById('batchImportFileBtn');
        const importBtn = document.getElementById('batchImportBtn');
        if (!file || !fileBtn || !importBtn) return;

        fileBtn.textContent = '✓ ' + file.name.substring(0, 20);
        fileBtn.classList.add('border-emerald-500');
        importBtn.disabled = false;
    }

    async submitBatchImport(categorySelect) {
        const fileInput = document.getElementById('batchImportFile');
        const importBtn = document.getElementById('batchImportBtn');
        const statusDiv = document.getElementById('batchImportStatus');

        if (!fileInput || !importBtn || !statusDiv) return;

        const file = fileInput.files[0];
        if (!file) {
            alert('Please select a CSV file.');
            return;
        }

        statusDiv.classList.remove('hidden');
        statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing import...';
        importBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'batch_import');
            formData.append('csrf_token', this.getCsrfToken());
            formData.append('file', file);
            formData.append('category_id', categorySelect ? categorySelect.value : '');

            const response = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                statusDiv.innerHTML = `
                    <div class="text-emerald-300">
                        <i class="fas fa-check-circle"></i>
                        <strong> Success! </strong>${data.message || 'Imported successfully.'}
                    </div>
                `;
                fileInput.value = '';
                const fileBtn = document.getElementById('batchImportFileBtn');
                if (fileBtn) {
                    fileBtn.textContent = '📁 Select CSV File';
                    fileBtn.classList.remove('border-emerald-500');
                }
                this.showStatusMessage('Batch import queued successfully.', 'success', 5000);
                setTimeout(() => this.loadProducts(), 1500);
            } else {
                const errorHtml = data.errors && data.errors.length > 0
                    ? data.errors.map(e => '• ' + (e.message || e.error || JSON.stringify(e))).join('<br/>')
                    : (data.message || 'Import failed');
                statusDiv.innerHTML = `
                    <div class="text-red-300">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong> Error: </strong>${errorHtml}
                    </div>
                `;
            }
        } catch (err) {
            statusDiv.innerHTML = `
                <div class="text-red-300">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong> Error: </strong>${err.message}
                </div>
            `;
        } finally {
            importBtn.disabled = false;
            setTimeout(() => {
                if (statusDiv.querySelector('.text-emerald-300')) {
                    statusDiv.classList.add('hidden');
                }
            }, 6000);
        }
    }

    batchPrintSelected() {
        const filter = document.getElementById('batch_filter') ? document.getElementById('batch_filter').value : 'low_stock';
        const body = new URLSearchParams();
        body.append('action', 'batch_print');
        body.append('filter', filter);
        body.append('csrf_token', this.getCsrfToken());

        fetch(getLabelPrinterEndpoint(), { method: 'POST', body })
            .then((res) => res.json())
            .then((data) => {
                if (data.success) {
                    this.showStatusMessage('Batch print started successfully.', 'success', 5000);
                } else {
                    this.showStatusMessage('Batch print failed: ' + (data.error || 'unknown'), 'error', 8000);
                }
            })
            .catch((err) => {
                console.error('batchPrintSelected error', err);
                this.showStatusMessage('Batch print error: ' + err.message, 'error', 8000);
            });
    }

    async pollPdfJob(jobId) {
        const statusBody = new URLSearchParams();
        statusBody.append('action', 'pdf_job_status');
        statusBody.append('job_id', jobId);
        statusBody.append('csrf_token', this.getCsrfToken());

        try {
            const response = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body: statusBody });
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to get job status');
            }

            const job = data.job || {};
            if (job.status === 'done' && job.output_file) {
                const url = getLabelPrinterEndpoint() + '?action=get_pdf&job_id=' + encodeURIComponent(jobId);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'barcode-labels-' + new Date().toISOString().slice(0,19).replace(/[:T]/g, '-') + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                this.showStatusMessage('PDF is ready for download.', 'success', 6000);
                return;
            }

            if (job.status === 'failed') {
                throw new Error(job.error || 'PDF generation failed');
            }

            this.showStatusMessage('PDF generation in progress…', 'info', 3000);
            setTimeout(() => this.pollPdfJob(jobId), 2000);
        } catch (err) {
            console.error('pollPdfJob failed', err);
            this.showStatusMessage('PDF job failed: ' + err.message, 'error', 8000);
        }
    }

    async loadProducts() {
        const searchEl = document.getElementById('search');
        const categoryEl = document.getElementById('category');

        // Support the actual #productList div used in the page (not just table tbody)
        const container = document.getElementById('productList') || document.getElementById('products-tbody');
        if (!container) return;

        const search = searchEl ? searchEl.value.trim() : '';
        const category = categoryEl ? categoryEl.value : '';

        const body = new URLSearchParams();
        body.append('action', 'get_products');
        body.append('search', search);
        body.append('category', category);
        body.append('label_size', this.getCurrentLabelSize());
        body.append('csrf_token', this.getCsrfToken());

        try {
            const res = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                this.products = data.products || [];
                this.renderProductTable();
            } else {
                console.error('Failed to load products', data.error);
                container.innerHTML = `
                    <div class="p-8 text-center text-rose-400">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <div class="text-sm">Failed to load products</div>
                        <div class="text-xs text-slate-400 mt-1">${data.error || 'Unknown error'}</div>
                        <button type="button" class="retry-load-products mt-3 px-3 py-1 text-xs bg-rose-500/10 border border-rose-500/30 rounded hover:bg-rose-500/20">
                            Try again
                        </button>
                    </div>
                `;
            }
        } catch (e) {
            console.error('Network error loading products', e);
            container.innerHTML = `
                <div class="p-8 text-center text-rose-400">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <div class="text-sm">Network error loading products</div>
                    <button type="button" class="retry-load-products mt-3 px-3 py-1 text-xs bg-rose-500/10 border border-rose-500/30 rounded hover:bg-rose-500/20">
                        Try again
                    </button>
                </div>
            `;
        }
    }

    renderProductTable() {
        // Support both the new table structure and the existing div-based #productList
        const container = document.getElementById('productList') || document.getElementById('products-tbody');
        if (!container) return;

        container.innerHTML = '';

        if (this.products.length === 0) {
            container.innerHTML = '<div class="p-8 text-center text-slate-300">No products found</div>';
            this.updateSelectedCount();
            return;
        }

        this.products.forEach(product => {
            const isSelected = this.selectedProducts.has(product.id);
            const qty = this.selectedProducts.get(product.id) || 1;

            const div = document.createElement('div');
            div.className = 'p-3 hover:bg-slate-700 cursor-pointer transition-colors flex items-center gap-3 border-b border-slate-600';
            div.innerHTML = `
                <input type="checkbox" id="cb_${product.id}" name="cb_${product.id}" class="product-checkbox w-4 h-4 rounded bg-slate-700 border-slate-500 text-amber-500 focus:ring-amber-500/50" 
                       data-id="${product.id}" ${isSelected ? 'checked' : ''}>
                <div class="flex-1 min-w-0">
                    <div class="text-white font-medium text-sm truncate">${product.name}</div>
                    <div class="text-slate-300 text-xs">SKU: ${product.sku || ''}</div>
                </div>
                <div class="text-amber-400 font-semibold text-sm">KES ${parseFloat(product.price || 0).toFixed(0)}</div>
                <div class="flex items-center gap-2 ml-3">
                    <input type="number" id="qty_${product.id}" name="qty_${product.id}" class="qty-input w-16 bg-slate-800 border border-slate-600 rounded px-2 py-1 text-sm text-center" 
                           value="${qty}" min="1" data-id="${product.id}">
                </div>
            `;

            // Click on the row toggles selection
            div.addEventListener('click', () => {
                const checkbox = div.querySelector('.product-checkbox');
                checkbox.checked = !checkbox.checked;
                this.toggleProductSelection(product.id, parseInt(div.querySelector('.qty-input').value) || 1);
            });

            // Checkbox direct handler
            const checkbox = div.querySelector('.product-checkbox');
            checkbox.addEventListener('click', (e) => e.stopPropagation());
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                const q = parseInt(div.querySelector('.qty-input').value) || 1;
                this.toggleProductSelection(product.id, q, checkbox.checked);
            });

            // Qty input
            const qtyInput = div.querySelector('.qty-input');
            qtyInput.addEventListener('click', (e) => e.stopPropagation());
            qtyInput.addEventListener('input', () => {
                if (this.selectedProducts.has(product.id)) {
                    this.selectedProducts.set(product.id, parseInt(qtyInput.value) || 1);
                    this.updateSelectedCount();
                }
            });

            container.appendChild(div);

            // Ensure id/name for autofill/a11y compliance on dynamic form controls (product rows)
            const newCb = div.querySelector('.product-checkbox');
            const newQty = div.querySelector('.qty-input');
            if (typeof ensureFormFieldAttributes === 'function') {
                ensureFormFieldAttributes(newCb, 'product_select');
                ensureFormFieldAttributes(newQty, 'product_qty');
            }
        });

        this.updateSelectedCount();
    }

    toggleProductSelection(id, qty, forceState = null) {
        if (forceState === true || (forceState === null && !this.selectedProducts.has(id))) {
            this.selectedProducts.set(id, qty);
        } else {
            this.selectedProducts.delete(id);
        }
        this.updateSelectedCount();
    }

    updateSelectedCount() {
        const countEl = document.getElementById('selected-count');
        if (countEl) {
            countEl.textContent = this.selectedProducts.size;
        }

        const generateBtn = document.getElementById('generateBtn');
        const pdfBtn = document.getElementById('pdfBtn');

        const hasSelection = this.selectedProducts.size > 0;

        if (generateBtn) generateBtn.disabled = !hasSelection;

        // PDF button: disabled if no selection OR if dompdf library is missing
        if (pdfBtn) {
            const dompdfMissing = typeof window.DOMPDF_AVAILABLE !== 'undefined' && window.DOMPDF_AVAILABLE === false;
            pdfBtn.disabled = !hasSelection || dompdfMissing;

            if (dompdfMissing) {
                pdfBtn.title = 'Professional PDF requires: composer require dompdf/dompdf';
            }
        }

        this.syncSelectAll();

        // Delegate preview to the new enterprise backend
        if (hasSelection && typeof this.loadPreview === 'function') {
            this.loadPreview();
        }
    }

    toggleSelectAll(checked) {
        const container = document.getElementById('productList') || document.getElementById('products-tbody');
        if (!container) return;

        const checkboxes = container.querySelectorAll('.product-checkbox');
        checkboxes.forEach(cb => {
            const id = parseInt(cb.dataset.id);
            if (!id) return;
            cb.checked = checked;

            const qtyEl = container.querySelector(`.qty-input[data-id="${id}"]`);
            const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;

            if (checked) {
                this.selectedProducts.set(id, qty);
            } else {
                this.selectedProducts.delete(id);
            }
        });
        this.updateSelectedCount();
    }

    syncSelectAll() {
        const selectAll = document.getElementById('selectAll');
        if (!selectAll) return;

        const container = document.getElementById('productList') || document.getElementById('products-tbody');
        if (!container) return;

        const cbs = container.querySelectorAll('.product-checkbox');
        if (cbs.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }

        const checkedCount = Array.from(cbs).filter(cb => cb.checked).length;
        if (checkedCount === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checkedCount === cbs.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    getCurrentLabelSize() {
        const select = document.getElementById('label_size');
        return select ? select.value : (this.tenantSettings.auto_print_label_size || 'k22');
    }

    getCsrfToken() {
        const tokenEl = document.querySelector('input[name="csrf_token"]');
        if (tokenEl && tokenEl.value) {
            return tokenEl.value;
        }

        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken && metaToken.content) {
            return metaToken.content;
        }

        return window.CSRF_TOKEN || '';
    }

    getSelectedProductsArray() {
        const arr = [];
        this.selectedProducts.forEach((qty, id) => {
            const product = this.products.find(p => p.id == id);
            if (product) {
                arr.push({
                    id: product.id,
                    name: product.name,
                    sku: product.sku,
                    price: product.price,
                    qty: qty
                });
            }
        });
        return arr;
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    renderPreviewWorkspace(html, meta = {}) {
        const sizeLabel = this.escapeHtml(meta.sizeLabel || 'Label preview');
        const productCount = Number(meta.productCount || 0);
        const totalLabels = Number(meta.totalLabels || 0);

        return `
            <div class="preview-workspace-bar">
                <div class="preview-workspace-copy">
                    <div class="preview-workspace-eyebrow">Live workspace</div>
                    <div class="preview-workspace-title">${sizeLabel}</div>
                </div>
                <div class="preview-workspace-meta">
                    <span class="preview-workspace-pill">${productCount} product${productCount === 1 ? '' : 's'}</span>
                    <span class="preview-workspace-pill">${totalLabels} label${totalLabels === 1 ? '' : 's'}</span>
                    <span class="preview-workspace-pill">Scroll to inspect</span>
                </div>
            </div>
            <div class="preview-workspace-content">${html}</div>
        `;
    }

    async printSelected(method = 'pdf') {
        const products = this.getSelectedProductsArray();
        if (products.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        const labelSize = this.getCurrentLabelSize();
        const csrf = this.getCsrfToken();

        if (method === 'pdf') {
            // Enqueue async PDF generation job and poll for completion (SaaS-friendly)
            try {
                const enqueueBody = new URLSearchParams();
                enqueueBody.append('action', 'enqueue_pdf');
                enqueueBody.append('products', JSON.stringify(products));
                enqueueBody.append('label_size', labelSize);
                enqueueBody.append('csrf_token', csrf);

                const enqueueRes = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body: enqueueBody });
                const enqueueData = await enqueueRes.json();

                if (enqueueData.success && enqueueData.job_id) {
                    const jobId = enqueueData.job_id;
                    this.showStatusMessage('PDF job queued. Waiting for completion...', 'info', 0);

                    const pollStatus = async () => {
                        const statusBody = new URLSearchParams();
                        statusBody.append('action', 'pdf_job_status');
                        statusBody.append('job_id', jobId);
                        statusBody.append('csrf_token', csrf);

                        try {
                            const statusRes = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body: statusBody });
                            const statusData = await statusRes.json();
                            if (statusData.success && statusData.job) {
                                const job = statusData.job;
                                if (job.status === 'done' && job.output_file) {
                                    const url = getLabelPrinterEndpoint() + '?action=get_pdf&job_id=' + encodeURIComponent(jobId);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = 'barcode-labels-' + new Date().toISOString().slice(0,19).replace(/[:T]/g, '-') + '.pdf';
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    this.showStatusMessage('PDF download started.', 'success', 6000);
                                    return;
                                } else if (job.status === 'failed') {
                                    this.showStatusMessage('PDF job failed: ' + (job.error || 'Unknown error'), 'error', 8000);
                                    return;
                                } else {
                                    this.showStatusMessage('PDF generation in progress…', 'info', 3000);
                                    setTimeout(pollStatus, 1500);
                                }
                            } else {
                                this.showStatusMessage('Failed to check job status: ' + (statusData.error || 'Unknown error'), 'error', 8000);
                                return;
                            }
                        } catch (e) {
                            console.error('PDF status poll error', e);
                            setTimeout(pollStatus, 2000);
                        }
                    };

                    pollStatus();
                    return;
                } else {
                    this.showStatusMessage('Failed to enqueue PDF job: ' + (enqueueData.error || 'Unknown error'), 'error', 8000);
                    return;
                }
            } catch (e) {
                console.error('enqueue error', e);
                alert('Failed to start PDF generation: ' + e.message);
                return;
            }
        }

        const body = new URLSearchParams();
        body.append('action', 'get_preview');
        body.append('products', JSON.stringify(products));
        body.append('label_size', labelSize);
        body.append('csrf_token', csrf);

        try {
            const res = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body });

            if (method === 'pdf') {
                // PDF endpoint streams the file directly (binary response)
                if (res.ok) {
                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'barcode-labels-' + new Date().toISOString().slice(0,19).replace(/[:T]/g, '-') + '.pdf';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    const text = await res.text();
                    alert('PDF generation failed: ' + (text || 'Unknown error'));
                }
                return;
            }

            // Browser: show customized preview FIRST — user clicks Print when ready
            const data = await res.json();
            if (data.success && data.html) {
                const preview = document.getElementById('labelPreview') || document.getElementById('preview');
                if (preview) {
                    preview.innerHTML = `
                        <div class="print-preview-controls mb-4 flex items-center gap-3">
                            <button type="button" class="btn-laravel btn-primary px-5 py-2 text-sm print-now-btn">
                                <i class="fas fa-print mr-2"></i> Print Labels Now
                            </button>
                            <button type="button" class="btn-laravel btn-secondary px-4 py-2 text-sm close-preview-btn">
                                Cancel
                            </button>
                            <span class="ml-auto text-xs text-slate-500">Review the labels above. Click "Print Labels Now" when ready.</span>
                        </div>
                        ${data.html}
                    `;
                    preview.style.display = 'block';
                    this.refreshPreviewPresentation(preview);

                    // Scroll so user sees the full customized preview
                    preview.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    // Wire up the manual print buttons
                    const printBtn = preview.querySelector('.print-now-btn');
                    const closeBtn = preview.querySelector('.close-preview-btn');

                    const cleanup = () => {
                        preview.style.display = 'none';
                        preview.innerHTML = '';
                    };

                    if (printBtn) {
                        printBtn.onclick = () => {
                            if (this.startPrintMode(preview)) {
                                window.print();
                            }
                        };
                    }
                    if (closeBtn) {
                        closeBtn.onclick = cleanup;
                    }
                }
            } else {
                alert('Print failed: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            console.error(e);
            alert('Network error while printing labels.');
        }
    }

    async loadPreview() {
        const products = this.getSelectedProductsArray();
        if (products.length === 0) return;

        const labelSize = this.getCurrentLabelSize();
        const csrf = this.getCsrfToken();

        // Support both the legacy ID and a clean "preview" ID for future cleanup
        const previewContainer = document.getElementById('labelPreview') || document.getElementById('preview');

        if (!previewContainer) return;

        const body = new URLSearchParams();
        body.append('action', 'get_preview');
        body.append('products', JSON.stringify(products));
        body.append('label_size', labelSize);
        body.append('csrf_token', csrf);

        try {
            const res = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body });
            const data = await res.json();

            if (data.success && data.html) {
                previewContainer.innerHTML = data.html;
                this.refreshPreviewPresentation(previewContainer);
            } else {
                console.warn('Preview failed:', data.error);
            }
        } catch (e) {
            console.error('Preview error', e);
        }
    }

    // Additional helper to use the high-level LabelPrinter facade via a dedicated endpoint action if added later
    async printViaLabelPrinter(products, labelSize, method) {
        // This can be wired when a 'print_via_labelprinter' action is added to the generate endpoint
        console.log('Delegating to LabelPrinter facade (to be implemented in endpoint)', { products, labelSize, method });
    }

    // ==================== NEW: Professional Print Preview Modal (Option A) ====================
    async openPrintPreviewModal() {
        const products = this.getSelectedProductsArray();
        if (products.length === 0) {
            alert('Please select at least one product first.');
            return;
        }

        const labelSize = this.getCurrentLabelSize();
        const csrf = this.getCsrfToken();

        const modal = document.getElementById('labelPrintPreviewModal');
        const container = document.getElementById('printPreviewContainer');
        const sizeNameEl = document.getElementById('previewModalSizeName');
        const sidebar = document.getElementById('printPreviewSidebar');

        if (!modal || !container) {
            alert('Preview modal not found');
            return;
        }

        // Show loading state
        container.innerHTML = `<div class="text-center text-slate-400 py-12">Loading professional preview...</div>`;
        this.setPreviewModalState(true);
        modal.scrollTop = 0;
        if (sidebar) {
            sidebar.scrollTop = 0;
        }

        // Set size name
        const template = LABEL_SIZES[labelSize] || {};
        const totalLabels = products.reduce((sum, product) => sum + (parseInt(product.qty, 10) || 0), 0);
        const previewLabel = template.label || labelSize.toUpperCase();
        if (sizeNameEl) {
            sizeNameEl.textContent = `${previewLabel} - ${products.length} product${products.length === 1 ? '' : 's'} - ${totalLabels} label${totalLabels === 1 ? '' : 's'}`;
        }

        const body = new URLSearchParams();
        body.append('action', 'get_preview');
        body.append('products', JSON.stringify(products));
        body.append('label_size', labelSize);
        body.append('csrf_token', csrf);

        try {
            const res = await fetch(getLabelPrinterEndpoint(), { method: 'POST', body });
            const data = await res.json();

            if (data.success && data.html) {
                container.innerHTML = this.renderPreviewWorkspace(data.html, {
                    sizeLabel: previewLabel,
                    productCount: products.length,
                    totalLabels
                });
                container.scrollTop = 0;

                // Initialize all customizers
                this.initPreviewCustomizers(container, labelSize);
                this.refreshPreviewPresentation(container);

            } else {
                container.innerHTML = `<div class="text-red-500 p-8 text-center">Failed to load preview: ${data.error || 'Unknown error'}</div>`;
            }
        } catch (e) {
            console.error(e);
            container.innerHTML = `<div class="text-red-500 p-8 text-center">Network error while loading preview.</div>`;
        }
    }

    initPreviewCustomizers(container, labelSize) {
        const defaultGap = 2;
        const defaultMargin = 5;
        const defaultFontScale = 1.0;

        // Gap
        const gapSlider = document.getElementById('previewGapSlider');
        const gapValue = document.getElementById('previewGapValue');

        if (gapSlider && gapValue) {
            gapSlider.value = defaultGap;
            gapValue.textContent = defaultGap.toFixed(1);

            gapSlider.oninput = () => {
                const val = parseFloat(gapSlider.value);
                gapValue.textContent = val.toFixed(1);
                this.applyPreviewStyles(container, { gap: val });
            };
        }

        // Margin
        const marginSlider = document.getElementById('previewMarginSlider');
        const marginValue = document.getElementById('previewMarginValue');

        if (marginSlider && marginValue) {
            marginSlider.value = defaultMargin;
            marginValue.textContent = defaultMargin.toFixed(1);

            marginSlider.oninput = () => {
                const val = parseFloat(marginSlider.value);
                marginValue.textContent = val.toFixed(1);
                this.applyPreviewStyles(container, { margin: val });
            };
        }

        // Font Scale — capture original base fonts once, then scale from them
        const fontSlider = document.getElementById('previewFontSlider');
        const fontValue = document.getElementById('previewFontValue');

        if (fontSlider && fontValue) {
            fontSlider.value = defaultFontScale;
            fontValue.textContent = defaultFontScale.toFixed(1);

            // Store original --label-font-size-pt per label on first run
            const labels = container.querySelectorAll('.label');
            labels.forEach(label => {
                const base = parseFloat(
                    getComputedStyle(label).getPropertyValue('--label-font-size-pt') || '7'
                ) || 7;
                label.dataset.baseFontPt = base;
            });

            fontSlider.oninput = () => {
                const val = parseFloat(fontSlider.value);
                fontValue.textContent = val.toFixed(1);
                container.querySelectorAll('.label').forEach(label => {
                    const base = parseFloat(label.dataset.baseFontPt || '7');
                    label.style.setProperty('--label-font-size-pt', base * val);
                });
                this.refreshPreviewPresentation(container);
            };
        }

        // Field toggles
        const toggles = {
            showStore: '.label-store',
            showName: '.label-name',
            showPrice: '.label-price',
            showSku: '.label-sku',
            showBarcode: '.label-barcode'
        };

        Object.keys(toggles).forEach(id => {
            const cb = document.getElementById(id);
            if (cb) {
                cb.onchange = () => {
                    const selector = toggles[id];
                    const show = cb.checked;
                    container.querySelectorAll(selector).forEach(el => {
                        el.style.display = show ? '' : 'none';
                    });
                    this.refreshPreviewPresentation(container);
                };
            }
        });

        // Apply initial styles
        this.applyPreviewStyles(container, {
            gap: defaultGap,
            margin: defaultMargin,
            fontScale: defaultFontScale
        });
    }

    applyPreviewStyles(container, settings = {}) {
        const sheets = container.querySelectorAll('.label-sheet');

        sheets.forEach(sheet => {
            if (settings.gap !== undefined) {
                sheet.style.gap = settings.gap + 'mm';
            }
            if (settings.margin !== undefined) {
                sheet.style.padding = settings.margin + 'mm';
            }
        });

        if (settings.fontScale !== undefined) {
            const labels = container.querySelectorAll('.label');
            labels.forEach(label => {
                // Read the per-sheet base font size from the CSS variable (default 7)
                const baseFont = parseFloat(
                    getComputedStyle(label).getPropertyValue('--label-font-size-pt') || '7'
                ) || 7;
                // Apply scale by overriding the variable on each label
                label.style.setProperty('--label-font-size-pt', baseFont * settings.fontScale);
            });
        }

        this.refreshPreviewPresentation(container);
    }

    ensurePreviewSheetWrappers(container) {
        const sheets = Array.from(container.querySelectorAll('.label-sheet'));

        sheets.forEach(sheet => {
            const parent = sheet.parentElement;
            if (parent && parent.classList.contains('label-preview-sheet-wrap')) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'label-preview-sheet-wrap';
            if (parent) {
                parent.insertBefore(wrapper, sheet);
                wrapper.appendChild(sheet);
            }
        });
    }

    fitPreviewSheets(container) {
        const wrappers = Array.from(container.querySelectorAll('.label-preview-sheet-wrap'));

        wrappers.forEach(wrapper => {
            const sheet = wrapper.querySelector('.label-sheet');
            if (!sheet) return;

            // Reset zoom to measure natural size
            sheet.style.zoom = '1';

            const availableWidth = Math.max(wrapper.clientWidth - 2, 0);
            const naturalWidth = sheet.scrollWidth;

            if (!availableWidth || !naturalWidth) return;

            // Use zoom (not transform:scale) so layout footprint shrinks with the element,
            // making the white background fill the container correctly.
            const scale = naturalWidth > availableWidth ? availableWidth / naturalWidth : 1;
            sheet.style.zoom = scale;
        });
    }

    syncPreviewMirror(container) {
        if (!container) {
            return;
        }

        this.preparePrintablePreview(container);
    }

    refreshPreviewPresentation(container) {
        if (!container) {
            return;
        }

        this.ensurePreviewSheetWrappers(container);
        this.fitPreviewSheets(container);
        this.syncPreviewMirror(container);
    }

    closePrintPreviewModal() {
        this.setPreviewModalState(false);
    }

    resetPreviewSettings() {
        const container = document.getElementById('printPreviewContainer');
        if (!container) return;

        // Reset sliders to defaults
        const gapSlider = document.getElementById('previewGapSlider');
        const marginSlider = document.getElementById('previewMarginSlider');
        const fontSlider = document.getElementById('previewFontSlider');

        if (gapSlider) gapSlider.value = 2;
        if (marginSlider) marginSlider.value = 5;
        if (fontSlider) fontSlider.value = 1.0;

        // Update displays
        const gapValue = document.getElementById('previewGapValue');
        const marginValue = document.getElementById('previewMarginValue');
        const fontValue = document.getElementById('previewFontValue');

        if (gapValue) gapValue.textContent = '2.0';
        if (marginValue) marginValue.textContent = '5.0';
        if (fontValue) fontValue.textContent = '1.0';

        // Re-apply defaults
        this.applyPreviewStyles(container, { gap: 2, margin: 5, fontScale: 1.0 });

        // Reset all field toggles to checked
        ['showStore', 'showName', 'showPrice', 'showSku', 'showBarcode'].forEach(id => {
            const cb = document.getElementById(id);
            if (cb) {
                cb.checked = true;
                const selector = {
                    showStore: '.label-store',
                    showName: '.label-name',
                    showPrice: '.label-price',
                    showSku: '.label-sku',
                    showBarcode: '.label-barcode'
                }[id];

                container.querySelectorAll(selector).forEach(el => el.style.display = '');
            }
        });
    }

    printFromPreviewModal() {
        const modal = document.getElementById('labelPrintPreviewModal');
        if (!modal) return;

        const container = document.getElementById('printPreviewContainer');

        if (container && this.startPrintMode(container)) {
            window.print();
        }
    }

    async reprintLastLabels() {
        try {
            const labelSize = this.getCurrentLabelSize();
            const body = new URLSearchParams();
            body.append('action', 'reprint_last');
            body.append('label_size', labelSize);
            body.append('csrf_token', this.getCsrfToken());

            const response = await fetch(getLabelPrinterEndpoint(), {
                method: 'POST',
                body
            });

            if (response.status === 429) {
                alert('⏳ Rate limit exceeded. Please wait before reprinting again.');
                return;
            }

            const data = await response.json();

            if (data.success) {
                alert('✓ Reprinting last labels...\n' + (data.message || ''));
                if (data.html) {
                    const preview = document.getElementById('labelPreview');
                    if (preview) {
                        preview.innerHTML = data.html;
                        preview.style.display = 'block';
                        setTimeout(() => {
                            window.print();
                        }, 500);
                        return;
                    }
                }
                setTimeout(() => window.print(), 500);
            } else {
                alert('✗ Error: ' + (data.error?.message || data.message || 'Reprint failed'));
            }
        } catch (err) {
            alert('✗ Error reprinting labels: ' + err.message);
        }
    }

    async loadTemplateConfig(labelSize) {
        try {
            const body = new URLSearchParams();
            body.append('action', 'get_label_template');
            body.append('label_size', labelSize);
            body.append('csrf_token', this.getCsrfToken());

            const response = await fetch(getLabelPrinterEndpoint(), {
                method: 'POST',
                body
            });

            const data = await response.json();
            if (data.success && data.config) {
                try {
                    return typeof data.config === 'string' ? JSON.parse(data.config) : data.config;
                } catch (_) {
                    return data.config;
                }
            }
        } catch (err) {
            console.warn('Unable to load template config:', err);
        }
        return null;
    }

    getTemplateFields() {
        return [
            { key: 'name', label: 'Product Name', default: true },
            { key: 'sku', label: 'SKU / Barcode Text', default: true },
            { key: 'price', label: 'Price', default: true },
            { key: 'barcode', label: 'Barcode Graphic', default: true },
            { key: 'store', label: 'Store Name', default: false }
        ];
    }

    renderTemplateEditor(config = {}) {
        const list = document.getElementById('fieldList');
        const preview = document.getElementById('editorPreview');
        if (!list || !preview) return;

        const selectedFields = Array.isArray(config.fields) ? config.fields : [];
        const fields = this.getTemplateFields();

        list.innerHTML = fields.map(field => {
            const isChecked = selectedFields.length ? selectedFields.includes(field.key) : field.default;
            return `
                <li class="flex items-center justify-between gap-3 rounded border border-slate-700 bg-slate-900 px-3 py-2">
                    <label class="flex-1 cursor-pointer text-sm text-slate-200">
                        <input type="checkbox" data-field-key="${field.key}" class="template-field-checkbox mr-2 leading-tight" ${isChecked ? 'checked' : ''}>
                        ${field.label}
                    </label>
                    <span class="text-[10px] text-slate-500">${field.key}</span>
                </li>
            `;
        }).join('');

        list.querySelectorAll('.template-field-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateTemplatePreview());
        });

        this.updateTemplatePreview(config);
    }

    updateTemplatePreview(config = {}) {
        const preview = document.getElementById('editorPreview');
        if (!preview) return;

        const fields = this.getTemplateFields();
        const enabledKeys = Array.from(document.querySelectorAll('#fieldList input[type="checkbox"]:checked'))
            .map(el => el.dataset.fieldKey);

        const sample = {
            name: 'Example Product',
            sku: 'SKU12345678',
            price: 'KES 350',
            barcode: '|||| |||| |||',
            store: window.STORE_NAME || 'My Store'
        };

        preview.innerHTML = fields.map(field => {
            if (!enabledKeys.includes(field.key)) {
                return '';
            }
            switch (field.key) {
                case 'name':
                    return `<div class="text-sm font-semibold text-slate-900">${sample.name}</div>`;
                case 'sku':
                    return `<div class="text-xs text-slate-700">${sample.sku}</div>`;
                case 'price':
                    return `<div class="text-sm text-amber-600">${sample.price}</div>`;
                case 'barcode':
                    return `<div class="mt-2 p-2 bg-slate-200 text-center rounded text-[10px] tracking-[0.4em]">${sample.barcode}</div>`;
                case 'store':
                    return `<div class="text-[10px] uppercase text-slate-500">${sample.store}</div>`;
                default:
                    return '';
            }
        }).join('');
    }

    async openTemplateEditor() {
        const modal = document.getElementById('templateEditorModal');
        if (!modal) {
            alert('Template editor not available');
            return;
        }

        const labelSize = this.getCurrentLabelSize();
        const sizeName = {
            'k5': 'K5 (38×16mm)',
            'k22': 'K22 (51×25mm)',
            'KA2': 'KA2 (51×13mm)',
            'K38': 'K38 (76×38mm)',
            'K11': 'K11 (19×13mm)',
            'KA1': 'KA1 (25×19mm)',
            'K27': 'K27 (51×38mm)',
            'k36': 'K36 (76×51mm)',
            'K05': 'K05 (13mm DIA)',
            'K09': 'K09 (22mm DIA)',
            'K15': 'K15 (49mm DIA)'
        }[labelSize] || labelSize;

        const sizeName_el = document.getElementById('editorSizeName');
        if (sizeName_el) {
            sizeName_el.textContent = sizeName;
        }

        const config = await this.loadTemplateConfig(labelSize) || {};
        this.renderTemplateEditor(config);

        modal.style.display = 'flex';
    }

    closeTemplateEditor() {
        const modal = document.getElementById('templateEditorModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    async saveTemplateConfig() {
        try {
            const labelSize = this.getCurrentLabelSize();
            const fieldKeys = Array.from(document.querySelectorAll('#fieldList input[type="checkbox"]:checked'))
                .map((checkbox) => checkbox.dataset.fieldKey);

            const config = {
                fields: fieldKeys
            };

            const body = new URLSearchParams();
            body.append('action', 'save_label_template');
            body.append('label_size', labelSize);
            body.append('config', JSON.stringify(config));
            body.append('csrf_token', this.getCsrfToken());

            const response = await fetch(getLabelPrinterEndpoint(), {
                method: 'POST',
                body
            });

            const data = await response.json();

            if (data.success) {
                alert('✓ Template saved successfully');
                this.closeTemplateEditor();
            } else {
                alert('✗ Error saving template: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert('✗ Error: ' + err.message);
        }
    }
}

// Auto-initialize if the page has the required DOM elements (current structure uses #productList)
if (document.getElementById('productList') || document.getElementById('label_size')) {
    window.labelPrinterApp = new LabelPrinterApp();
}

if (typeof window.updateLabelSetting !== 'function') {
    window.updateLabelSetting = async function(key, value) {
        const body = new URLSearchParams();
        body.append('action', 'save_setting');
        body.append('key', key);
        body.append('value', value);
        body.append('csrf_token', window.CSRF_TOKEN || '');

        try {
            const data = await postLabelPrinterJson(body);
            if (!data.success) {
                throw new Error(data.error || 'Unable to save label setting');
            }

            if (window.showToast) {
                window.showToast('Label setting saved', 'success');
            }

            return true;
        } catch (error) {
            console.error('[LabelPrinter] Failed to save setting', error);
            if (window.showToast) {
                window.showToast(error.message || 'Failed to save label setting', 'error');
            }
            return false;
        }
    };
}

if (typeof window.saveAutoPrintSetting !== 'function') {
    window.saveAutoPrintSetting = function(enabled) {
        return window.updateLabelSetting('auto_print_labels_on_sale', enabled ? '1' : '0');
    };
}
