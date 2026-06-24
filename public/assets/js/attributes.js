/**
 * Product Attributes Module - JavaScript
 * Jakababa POS - SaaS Version
 */

let currentPage = 1;
let perPage = 20;
let currentFilters = { search: '', type: '', group_id: '', status: '' };
let currentSort = 'sort_order_asc';
let selectedAttributes = new Set();
let currentAttributeId = null;
let deleteTargetId = null;
let debounceTimer = null;
let valueRows = [];

const allColumns = [
    { key: 'name', label: 'Name', default: true },
    { key: 'code', label: 'Slug', default: true },
    { key: 'type', label: 'Type', default: true },
    { key: 'group', label: 'Group', default: true },
    { key: 'order_by', label: 'Order by', default: true },
    { key: 'values', label: 'Terms', default: true },
    { key: 'products', label: 'Products', default: false },
    { key: 'status', label: 'Status', default: true }
];
let visibleColumns = JSON.parse(localStorage.getItem('attr_visible_cols') || 'null') || allColumns.map(c => c.key);
visibleColumns = visibleColumns.filter(k => allColumns.some(c => c.key === k));
let savedFilters = JSON.parse(localStorage.getItem('attr_saved_filters') || '[]');
let recentAttributes = JSON.parse(localStorage.getItem('attr_recent') || '[]');

/**
 * Utility: Ensure any form field has unique id and name to avoid autofill/a11y warnings.
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

function init() {
    loadAttributes();
    loadStats();
    renderSavedFilters();
    renderRecentAttributes();
    renderColVisMenu();
    syncColumnHeaders();
    setupKeyboardShortcuts();

    const searchInput = document.getElementById('attr-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentFilters.search = this.value;
                currentPage = 1;
                loadAttributes();
            }, 300);
        });
    }

    document.addEventListener('click', function(e) {
        const colVisMenu = document.getElementById('colVisMenu');
        if (colVisMenu && !colVisMenu.classList.contains('hidden') && !e.target.closest('.col-vis-dropdown')) {
            colVisMenu.classList.add('hidden');
        }
        document.querySelectorAll('.row-actions-menu').forEach(m => m.remove());
    });
}

document.addEventListener('DOMContentLoaded', init);

function showToast(message, type) {
    const toast = document.getElementById('attr-toast');
    const msgSpan = document.getElementById('attr-toast-msg');
    msgSpan.textContent = message;
    toast.className = 'fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 rounded-lg shadow-xl transition-all duration-300 border-l-4 ' +
        (type === 'error' ? 'bg-red-500/90 text-white border-red-300' : type === 'warning' ? 'bg-amber-500/90 text-white border-amber-300' : 'bg-green-500/90 text-white border-green-300');
    toast.classList.remove('hidden');
    toast.style.display = 'flex';
    setTimeout(() => { toast.classList.add('show'); }, 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => { toast.style.display = 'none'; }, 300);
    }, 3000);
}

function loadAttributes() {
    const tbody = document.getElementById('attr-tbody');
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-spinner fa-spin text-gray-500 text-xl"></i></div><p class="text-gray-400 font-medium">Loading attributes...</p></div></td></tr>';

    currentFilters.type = document.getElementById('attr-filter-type').value;
    currentFilters.group_id = document.getElementById('attr-filter-group').value;
    // status is managed by tab buttons, not a select element
    perPage = parseInt(document.getElementById('attr-per-page').value) || 20;
    currentSort = document.getElementById('attr-sort').value;

    const params = new URLSearchParams({
        action: 'list',
        ajax: '1',
        page: currentPage,
        per_page: perPage,
        search: currentFilters.search,
        type: currentFilters.type,
        group_id: currentFilters.group_id,
        status: currentFilters.status,
        sort: currentSort
    });

    fetch(window.ATTR_CONFIG.ajax_url + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderAttributesTable(data.data);
                renderPagination(data.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="10" class="empty-state"><div class="empty-state-icon"><i class="fas fa-exclamation-triangle text-red-500"></i></div><p class="text-red-400">' + (data.error || 'Failed to load') + '</p></td></tr>';
            }
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="10" class="empty-state"><div class="empty-state-icon"><i class="fas fa-exclamation-triangle text-red-500"></i></div><p class="text-red-400">Network error</p></td></tr>';
        });
}

function renderAttributesTable(data) {
    const tbody = document.getElementById('attr-tbody');
    if (!data.rows || data.rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="empty-state"><div class="empty-state-icon"><i class="fas fa-inbox text-gray-500 text-xl"></i></div><p class="text-gray-400 font-medium">No attributes found</p><p class="text-gray-500 text-sm mt-1">Try adjusting your filters or create a new attribute.</p></td></tr>';
        return;
    }

    tbody.innerHTML = data.rows.map(attr => {
        const isNameVisible = visibleColumns.includes('name');
        const isCodeVisible = visibleColumns.includes('code');
        const isTypeVisible = visibleColumns.includes('type');
        const isGroupVisible = visibleColumns.includes('group');
        const isOrderByVisible = visibleColumns.includes('order_by');
        const isValuesVisible = visibleColumns.includes('values');
        const isProductsVisible = visibleColumns.includes('products');
        const isStatusVisible = visibleColumns.includes('status');

        let cells = '';
        cells += '<td><input type="checkbox" id="attr_cb_' + attr.id + '" name="attr_cb_' + attr.id + '" class="attr-checkbox w-4 h-4 rounded accent-amber-500 cursor-pointer" value="' + attr.id + '" onchange="toggleAttributeSelection(this)"></td>';
        if (isNameVisible) {
            cells += '<td>' +
                '<div class="flex flex-col">' +
                    '<div class="flex items-center gap-2">' +
                        '<span class="font-medium text-white">' + escapeHtml(attr.name) + '</span>' +
                        (attr.is_variant_forming ? '<span class="variant-icon" title="Variant Forming"><i class="fas fa-code-branch"></i></span>' : '') +
                    '</div>' +
                    '<div class="wc-row-actions">' +
                        '<a href="attribute_form.php?id=' + attr.id + '" onclick="event.stopPropagation()">Edit</a>' +
                        '<span class="sep">|</span>' +
                        '<a href="attribute_terms.php?attribute_id=' + attr.id + '" onclick="event.stopPropagation()">Configure terms</a>' +
                        '<span class="sep">|</span>' +
                        '<button type="button" onclick="event.stopPropagation(); deleteAttribute(' + attr.id + ', ' + escapeJs(attr.name) + ')">Delete</button>' +
                    '</div>' +
                '</div></td>';
        }
        if (isCodeVisible) cells += '<td><code class="text-xs text-gray-400">' + escapeHtml(attr.code) + '</code></td>';
        if (isTypeVisible) cells += '<td><span class="type-badge"><i class="fas ' + getTypeIcon(attr.type) + '"></i> ' + getTypeLabel(attr.type) + getScopeLabel(attr) + '</span></td>';
        if (isGroupVisible) cells += '<td class="text-gray-300">' + (attr.group_name ? escapeHtml(attr.group_name) : '<span class="text-gray-500">—</span>') + '</td>';
        if (isOrderByVisible) cells += '<td class="order-by-label">Custom ordering</td>';
        if (isValuesVisible) {
            const termsPreview = attr.values_preview ? escapeHtml(attr.values_preview) : '<span class="na">—</span>';
            cells += '<td><div class="terms-preview">' + termsPreview + '<br><a href="attribute_terms.php?attribute_id=' + attr.id + '" class="configure-terms" onclick="event.stopPropagation()"><i class="fas fa-cog"></i> Configure terms</a></div></td>';
        }
        if (isProductsVisible) {
            const pc = attr.product_count || 0;
            cells += '<td><span class="text-sm ' + (pc > 0 ? 'text-white' : 'text-gray-500') + '">' + pc + '</span></td>';
        }
        if (isStatusVisible) cells += '<td><button onclick="toggleStatus(' + attr.id + ', ' + attr.status + ')" class="status-badge ' + (attr.status ? 'active' : 'inactive') + '"><i class="fas ' + (attr.status ? 'fa-check-circle' : 'fa-ban') + '"></i> ' + (attr.status ? 'Active' : 'Inactive') + '</button></td>';
        cells += '<td class="text-right"><div class="row-actions"><button onclick="event.stopPropagation(); toggleRowActions(this, ' + attr.id + ', ' + escapeJs(attr.name) + ')" class="text-gray-500 hover:text-amber-400 p-1"><i class="fas fa-ellipsis-v"></i></button></div></td>';

        return '<tr class="hover:bg-gray-800/50 transition-colors" data-id="' + attr.id + '" onclick="showAttributeDetail(' + attr.id + ')">' + cells + '</tr>';
    }).join('');

    // Ensure id/name on dynamic form fields (prevents autofill warnings)
    tbody.querySelectorAll('.attr-checkbox').forEach(el => {
        if (typeof ensureFormFieldAttributes === 'function') ensureFormFieldAttributes(el, 'attr_cb');
    });

    updateBulkBar();
    syncColumnHeaders();
}

function syncColumnHeaders() {
    document.querySelectorAll('#attr-table thead th[data-col]').forEach(th => {
        const col = th.dataset.col;
        th.style.display = visibleColumns.includes(col) ? '' : 'none';
    });
}

function getTypeLabel(type) {
    const types = { text: 'Text', textarea: 'Textarea', dropdown: 'Dropdown', multiselect: 'Multi-Select', number: 'Number', color: 'Color', file: 'File', date: 'Date', boolean: 'Yes/No' };
    return types[type] || type;
}

function getTypeIcon(type) {
    const icons = { text: 'fa-font', textarea: 'fa-align-left', dropdown: 'fa-list', multiselect: 'fa-check-square', number: 'fa-hashtag', color: 'fa-palette', file: 'fa-file', date: 'fa-calendar', boolean: 'fa-toggle-on' };
    return icons[type] || 'fa-circle';
}

function getScopeLabel(attr) {
    const parts = [];
    if (attr.is_filterable) parts.push('Public');
    if (attr.is_variant_forming) parts.push('Variant');
    return parts.length ? ' (' + parts.join(', ') + ')' : ' (Private)';
}

function renderPagination(data) {
    const infoSpan = document.getElementById('pagination-info');
    const btnContainer = document.getElementById('pagination-buttons');
    const from = data.total === 0 ? 0 : (data.page - 1) * data.per_page + 1;
    const to = Math.min(data.page * data.per_page, data.total);
    infoSpan.textContent = 'Showing ' + from + '-' + to + ' of ' + data.total;

    if (data.total_pages <= 1) {
        btnContainer.innerHTML = '';
        return;
    }

    let html = '';
    html += '<button onclick="goToPage(' + (data.page - 1) + ')" ' + (data.page <= 1 ? 'disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';

    for (let i = 1; i <= data.total_pages; i++) {
        if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
            html += '<button onclick="goToPage(' + i + ')" class="' + (i === data.page ? 'active' : '') + '">' + i + '</button>';
        } else if (i === data.page - 3 || i === data.page + 3) {
            html += '<span class="text-gray-500 px-1">...</span>';
        }
    }

    html += '<button onclick="goToPage(' + (data.page + 1) + ')" ' + (data.page >= data.total_pages ? 'disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
    btnContainer.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    loadAttributes();
}

function changePerPage() {
    perPage = parseInt(document.getElementById('attr-per-page').value) || 20;
    currentPage = 1;
    loadAttributes();
}

function loadStats() {
    fetch(window.ATTR_CONFIG.ajax_url + '?action=stats&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                document.getElementById('stat-total').textContent = data.data.total || 0;
                document.getElementById('stat-active').textContent = data.data.active || 0;
                document.getElementById('stat-inactive').textContent = (data.data.total || 0) - (data.data.active || 0);
                document.getElementById('stat-variant').textContent = data.data.variant || 0;
                document.getElementById('stat-terms').textContent = data.data.terms || 0;
                document.getElementById('tab-count-all').textContent = data.data.total || 0;
                document.getElementById('tab-count-active').textContent = data.data.active || 0;
                document.getElementById('tab-count-inactive').textContent = (data.data.total || 0) - (data.data.active || 0);
            }
        })
        .catch(err => console.error('Stats error:', err));
}

function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    fetch(window.ATTR_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'toggle_status', ajax: '1', id: id, status: newStatus, csrf_token: window.ATTR_CONFIG.csrf_token })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Attribute ' + (newStatus ? 'activated' : 'deactivated'), 'success');
            loadAttributes();
            loadStats();
        } else {
            showToast(data.error || 'Failed', 'error');
        }
    });
}

function toggleAttributeSelection(checkbox) {
    if (checkbox.checked) selectedAttributes.add(checkbox.value);
    else selectedAttributes.delete(checkbox.value);
    updateBulkBar();
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.attr-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
        if (checkbox.checked) selectedAttributes.add(cb.value);
        else selectedAttributes.delete(cb.value);
    });
    updateBulkBar();
}

function selectAllOnPage() {
    document.querySelectorAll('.attr-checkbox').forEach(cb => {
        cb.checked = true;
        selectedAttributes.add(cb.value);
    });
    updateBulkBar();
}

function clearSelection() {
    selectedAttributes.clear();
    document.querySelectorAll('.attr-checkbox').forEach(cb => cb.checked = false);
    const selectAllCb = document.getElementById('selectAllCheckbox');
    if (selectAllCb) selectAllCb.checked = false;
    updateBulkBar();
}

function updateBulkBar() {
    const count = selectedAttributes.size;
    const bar = document.getElementById('bulkActionsBar');
    const countSpan = document.getElementById('selectedCount');
    if (count > 0) {
        countSpan.textContent = count;
        bar.classList.remove('hidden');
    } else {
        bar.classList.add('hidden');
    }
}

function bulkStatusUpdate(status) {
    if (selectedAttributes.size === 0) return;
    const ids = Array.from(selectedAttributes);
    fetch(window.ATTR_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'bulk_status', ajax: '1', ids: JSON.stringify(ids), status: status, csrf_token: window.ATTR_CONFIG.csrf_token })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast((data.data.updated_count || ids.length) + ' attributes updated', 'success');
            clearSelection();
            loadAttributes();
            loadStats();
        } else {
            showToast(data.error || 'Bulk update failed', 'error');
        }
    });
}

function openBulkDeleteModal() {
    const count = selectedAttributes.size;
    if (count === 0) return;
    document.getElementById('bulkDeleteCount').textContent = count;
    document.getElementById('bulkDeleteModal').classList.add('active');
}

function closeBulkDeleteModal() {
    document.getElementById('bulkDeleteModal').classList.remove('active');
}

function confirmBulkDelete() {
    const ids = Array.from(selectedAttributes);
    closeBulkDeleteModal();
    fetch(window.ATTR_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'bulk_delete', ajax: '1', ids: JSON.stringify(ids), csrf_token: window.ATTR_CONFIG.csrf_token })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast((data.data.deleted_count || ids.length) + ' attributes deleted', 'success');
            clearSelection();
            loadAttributes();
            loadStats();
        } else {
            showToast(data.error || 'Bulk delete failed', 'error');
        }
    });
}

function deleteAttribute(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteAttrName').textContent = name;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    deleteTargetId = null;
}

function confirmDelete() {
    if (!deleteTargetId) return;
    const id = deleteTargetId;
    closeDeleteModal();
    fetch(window.ATTR_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', ajax: '1', id: id, csrf_token: window.ATTR_CONFIG.csrf_token })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Attribute deleted', 'success');
            loadAttributes();
            loadStats();
        } else {
            showToast(data.error || 'Delete failed', 'error');
        }
    });
}

function toggleRowActions(btn, id, name) {
    const existing = btn.parentElement.querySelector('.row-actions-menu');
    if (existing) { existing.remove(); return; }

    const menu = document.createElement('div');
    menu.className = 'row-actions-menu';
    menu.innerHTML =
        '<a href="attribute_form.php?id=' + id + '"><i class="fas fa-edit"></i> Edit</a>' +
        '<a href="attribute_terms.php?attribute_id=' + id + '"><i class="fas fa-cog"></i> Configure terms</a>' +
        '<button onclick="cloneAttribute(' + id + ')"><i class="fas fa-copy"></i> Clone</button>' +
        '<button onclick="deleteAttribute(' + id + ', ' + escapeJs(name) + ')"><i class="fas fa-trash text-red-400"></i> Delete</button>';
    btn.parentElement.appendChild(menu);
}

function showAttributeDetail(id) {
    fetch(window.ATTR_CONFIG.ajax_url + '?action=get&id=' + id + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const attr = data.data;
                const panel = document.getElementById('attr-detail-panel');
                const content = document.getElementById('attr-detail-content');
                addToRecent(attr);
                content.innerHTML =
                    '<div class="space-y-3">' +
                    '<div class="flex justify-between"><span class="text-gray-500">Name</span><span class="text-white">' + escapeHtml(attr.name) + '</span></div>' +
                    '<div class="flex justify-between"><span class="text-gray-500">Code</span><code class="text-gray-400">' + escapeHtml(attr.code) + '</code></div>' +
                    '<div class="flex justify-between"><span class="text-gray-500">Type</span><span class="text-white">' + getTypeLabel(attr.type) + '</span></div>' +
                    '<div class="flex justify-between"><span class="text-gray-500">Group</span><span class="text-white">' + (attr.group_name || '—') + '</span></div>' +
                    '<div class="flex justify-between"><span class="text-gray-500">Terms</span><span class="text-white">' + (attr.values_count || 0) + '</span></div>' +
                    '<div class="flex justify-between"><span class="text-gray-500">Status</span><span class="' + (attr.status ? 'text-green-400' : 'text-gray-400') + '">' + (attr.status ? 'Active' : 'Inactive') + '</span></div>' +
                    '</div>';
                panel.classList.remove('hidden');
            }
        });
}

function addToRecent(attr) {
    recentAttributes = recentAttributes.filter(a => a.id != attr.id);
    recentAttributes.unshift({ id: attr.id, name: attr.name });
    if (recentAttributes.length > 5) recentAttributes = recentAttributes.slice(0, 5);
    localStorage.setItem('attr_recent', JSON.stringify(recentAttributes));
    renderRecentAttributes();
}

function renderRecentAttributes() {
    const container = document.getElementById('recent-attributes');
    if (!recentAttributes.length) {
        container.innerHTML = '<p class="text-center text-gray-500 text-sm py-2">No recent attributes</p>';
        return;
    }
    container.innerHTML = recentAttributes.map(a =>
        '<div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-800/50 cursor-pointer" onclick="showAttributeDetail(' + a.id + ')">' +
        '<span class="text-sm text-gray-300 truncate">' + escapeHtml(a.name) + '</span>' +
        '<i class="fas fa-chevron-right text-xs text-gray-600"></i></div>'
    ).join('');
}

function filterByGroup(groupId) {
    document.getElementById('attr-filter-group').value = groupId;
    currentPage = 1;
    loadAttributes();
}

function setStatusFilter(status) {
    currentFilters.status = status;
    document.querySelectorAll('#status-tabs button').forEach(btn => {
        if (btn.dataset.status === status) {
            btn.classList.remove('text-gray-400', 'hover:text-white', 'hover:bg-gray-800');
            btn.classList.add('bg-amber-500', 'text-gray-900', 'font-semibold');
        } else {
            btn.classList.remove('bg-amber-500', 'text-gray-900', 'font-semibold');
            btn.classList.add('text-gray-400', 'hover:text-white', 'hover:bg-gray-800');
        }
    });
    currentPage = 1;
    loadAttributes();
}

function clearFilters() {
    currentFilters = { search: '', type: '', group_id: '', status: '' };
    document.getElementById('attr-search').value = '';
    document.getElementById('attr-filter-type').value = '';
    document.getElementById('attr-filter-group').value = '';
    currentFilters.status = '';
    setStatusFilter('');
    currentPage = 1;
    loadAttributes();
}

function saveCurrentFilter() {
    const name = prompt('Name this filter:');
    if (!name) return;
    const filter = {
        name: name,
        search: document.getElementById('attr-search').value,
        type: document.getElementById('attr-filter-type').value,
        group_id: document.getElementById('attr-filter-group').value,
        status: currentFilters.status,
        sort: document.getElementById('attr-sort').value
    };
    savedFilters.push(filter);
    localStorage.setItem('attr_saved_filters', JSON.stringify(savedFilters));
    renderSavedFilters();
    showToast('Filter saved', 'success');
}

function renderSavedFilters() {
    const bar = document.getElementById('saved-filters-bar');
    if (!savedFilters.length) {
        bar.classList.add('hidden');
        return;
    }
    bar.classList.remove('hidden');
    bar.innerHTML = savedFilters.map((f, i) =>
        '<span class="saved-filter-chip" onclick="applySavedFilter(' + i + ')">' + escapeHtml(f.name) +
        ' <i class="fas fa-times hover:text-red-400" onclick="event.stopPropagation(); removeSavedFilter(' + i + ')"></i></span>'
    ).join('');
}

function applySavedFilter(index) {
    const f = savedFilters[index];
    if (!f) return;
    document.getElementById('attr-search').value = f.search || '';
    document.getElementById('attr-filter-type').value = f.type || '';
    document.getElementById('attr-filter-group').value = f.group_id || '';
    document.getElementById('attr-sort').value = f.sort || 'sort_order_asc';
    currentFilters.search = f.search || '';
    currentFilters.type = f.type || '';
    currentFilters.group_id = f.group_id || '';
    if (f.status !== undefined) setStatusFilter(f.status);
    currentPage = 1;
    loadAttributes();
}

function removeSavedFilter(index) {
    savedFilters.splice(index, 1);
    localStorage.setItem('attr_saved_filters', JSON.stringify(savedFilters));
    renderSavedFilters();
}

function toggleColVis() {
    const menu = document.getElementById('colVisMenu');
    menu.classList.toggle('hidden');
}

function renderColVisMenu() {
    const menu = document.getElementById('colVisMenu');
    if (!menu) return;
    menu.innerHTML = allColumns.map(col =>
        '<label><input type="checkbox" id="colvis_' + col.key + '" name="colvis_' + col.key + '" ' + (visibleColumns.includes(col.key) ? 'checked' : '') + ' onchange="toggleColumn(\'' + col.key + '\')"> ' + col.label + '</label>'
    ).join('');

    if (typeof ensureFormFieldAttributes === 'function') {
        menu.querySelectorAll('input[type="checkbox"]').forEach(el => ensureFormFieldAttributes(el, 'colvis'));
    }
}

function toggleColumn(key) {
    if (visibleColumns.includes(key)) visibleColumns = visibleColumns.filter(c => c !== key);
    else visibleColumns.push(key);
    localStorage.setItem('attr_visible_cols', JSON.stringify(visibleColumns));
    renderColVisMenu();
    syncColumnHeaders();
    loadAttributes();
}

function sortTable(column) {
    const map = { name: 'name', code: 'code', type: 'type', group: 'group_name', products: 'product_count', status: 'status' };
    const dbCol = map[column] || column;
    const sortSelect = document.getElementById('attr-sort');
    if (currentSort === dbCol + '_asc') {
        sortSelect.value = dbCol + '_desc';
    } else {
        sortSelect.value = dbCol + '_asc';
    }
    currentSort = sortSelect.value;
    loadAttributes();
}

function exportAttributes() {
    window.location.href = window.ATTR_CONFIG.ajax_url + '?action=export&format=csv';
}

function toggleShortcutsHelp() {
    document.getElementById('shortcutsModal').classList.toggle('active');
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

        if (e.key === 'Escape') {
            closeSlideOver();
            closeGroupModal();
            closeDeleteModal();
            closeBulkDeleteModal();
            const shortcutsModal = document.getElementById('shortcutsModal');
            if (shortcutsModal && shortcutsModal.classList.contains('active')) shortcutsModal.classList.remove('active');
            const colVisMenu = document.getElementById('colVisMenu');
            if (colVisMenu && !colVisMenu.classList.contains('hidden')) colVisMenu.classList.add('hidden');
        }

        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            if (window.ATTR_CONFIG.can_create) window.location.href = 'attribute_form.php';
        }
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportAttributes();
        }
        if (e.key === '/') {
            e.preventDefault();
            document.getElementById('attr-search').focus();
        }
    });
}

function openSlideOver(attributeId) {
    if (!attributeId) {
        window.location.href = 'attribute_form.php';
        return;
    }
    window.location.href = 'attribute_form.php?id=' + attributeId;
}

function closeSlideOver() {
    const overlay = document.getElementById('attr-slide-overlay');
    const slideOver = document.getElementById('attr-slide-over');
    if (overlay) overlay.classList.add('hidden');
    if (slideOver) {
        slideOver.classList.remove('translate-x-0');
        slideOver.classList.add('translate-x-full');
    }
    document.body.style.overflow = '';
    resetForm();
    currentAttributeId = null;
}

function resetForm() {
    const fields = ['attr-form-id', 'attr-name', 'attr-code', 'attr-unit'];
    fields.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    const groupEl = document.getElementById('attr-group');
    if (groupEl) groupEl.value = '';
    const btEl = document.getElementById('attr-bt');
    if (btEl) btEl.value = '';
    const sortEl = document.getElementById('attr-form-sort');
    if (sortEl) sortEl.value = '0';
    const statusEl = document.getElementById('attr-status');
    if (statusEl) statusEl.checked = true;
    ['attr-required', 'attr-filterable', 'attr-variant'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.querySelectorAll('#attr-type-grid input').forEach(radio => { if (radio.value === 'text') radio.checked = true; });
    valueRows = [];
    renderValueManager();
    const fgUnit = document.getElementById('fg-unit');
    const fgValues = document.getElementById('fg-values');
    if (fgUnit) fgUnit.classList.add('hidden');
    if (fgValues) fgValues.classList.add('hidden');
    const nameErr = document.getElementById('name-error');
    const codeErr = document.getElementById('code-error');
    if (nameErr) nameErr.textContent = '';
    if (codeErr) codeErr.textContent = '';
    setupTypeChangeListener();
}

function setupTypeChangeListener() {
    document.querySelectorAll('#attr-type-grid input').forEach(radio => {
        radio.removeEventListener('change', onTypeChange);
        radio.addEventListener('change', onTypeChange);
    });
}

function onTypeChange(e) {
    const type = e.target.value;
    const showValues = ['dropdown', 'multiselect'].includes(type);
    const showUnit = type === 'number';
    const fgValues = document.getElementById('fg-values');
    const fgUnit = document.getElementById('fg-unit');
    if (fgValues) fgValues.classList.toggle('hidden', !showValues);
    if (fgUnit) fgUnit.classList.toggle('hidden', !showUnit);
    if (showValues && valueRows.length === 0) addValueRow();
}

function loadAttributeData(id) {
    fetch(window.ATTR_CONFIG.ajax_url + '?action=get&id=' + id + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const attr = data.data;
                document.getElementById('attr-form-id').value = attr.id;
                document.getElementById('attr-name').value = attr.name;
                document.getElementById('attr-code').value = attr.code;
                document.getElementById('attr-unit').value = attr.unit || '';
                document.getElementById('attr-group').value = attr.group_id || '';
                document.getElementById('attr-bt').value = attr.business_type_id || '';
                document.getElementById('attr-form-sort').value = attr.sort_order || 0;
                document.getElementById('attr-status').checked = attr.status == 1;
                document.getElementById('attr-required').checked = attr.is_required == 1;
                document.getElementById('attr-filterable').checked = attr.is_filterable == 1;
                document.getElementById('attr-variant').checked = attr.is_variant_forming == 1;
                document.querySelectorAll('#attr-type-grid input').forEach(radio => { if (radio.value === attr.type) radio.checked = true; });
                valueRows = (attr.values || []).map(v => ({ value: v.value, label: v.label || '', color_hex: v.color_hex || '' }));
                renderValueManager();
                const showValues = ['dropdown', 'multiselect'].includes(attr.type);
                const showUnit = attr.type === 'number';
                document.getElementById('fg-values').classList.toggle('hidden', !showValues);
                document.getElementById('fg-unit').classList.toggle('hidden', !showUnit);
                setupTypeChangeListener();
            } else {
                showToast(data.error || 'Failed to load', 'error');
            }
        });
}

function renderValueManager() {
    const container = document.getElementById('attr-values-manager');
    if (!container) return;
    if (valueRows.length === 0) {
        container.innerHTML = '<div class="text-center py-6 text-gray-500 text-sm">No values added yet.</div>';
        return;
    }
    container.innerHTML = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="text-gray-400 border-b border-gray-700"><tr><th class="text-left py-2 px-2">Value *</th><th class="text-left py-2 px-2">Label</th><th class="text-left py-2 px-2">Color</th><th class="w-10 py-2 px-2"></th></tr></thead><tbody>' +
        valueRows.map((row, index) =>
            '<tr class="border-b border-gray-800">' +
            '<td class="py-2 px-2"><input type="text" id="val_' + index + '_value" name="val_' + index + '_value" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none" value="' + escapeHtml(row.value) + '" placeholder="e.g. red" onchange="updateValueRow(' + index + ', \'value\', this.value)"></td>' +
            '<td class="py-2 px-2"><input type="text" id="val_' + index + '_label" name="val_' + index + '_label" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none" value="' + escapeHtml(row.label) + '" placeholder="Display label" onchange="updateValueRow(' + index + ', \'label\', this.value)"></td>' +
            '<td class="py-2 px-2"><input type="color" id="val_' + index + '_color" name="val_' + index + '_color" class="w-10 h-8 rounded border border-gray-700 cursor-pointer" value="' + (row.color_hex || '#000000') + '" onchange="updateValueRow(' + index + ', \'color_hex\', this.value)"></td>' +
            '<td class="py-2 px-2"><button type="button" class="text-red-400 hover:text-red-300" onclick="removeValueRow(' + index + ')"><i class="fas fa-trash"></i></button></td>' +
            '</tr>'
        ).join('') +
        '</tbody></table></div>';

    if (typeof ensureFormFieldAttributes === 'function') {
        container.querySelectorAll('input').forEach(el => ensureFormFieldAttributes(el, 'attr_val'));
    }
}

function addValueRow() { valueRows.push({ value: '', label: '', color_hex: '' }); renderValueManager(); }
function removeValueRow(index) { valueRows.splice(index, 1); renderValueManager(); }
function updateValueRow(index, field, value) { if (valueRows[index]) valueRows[index][field] = value; }

function checkName() {
    const name = document.getElementById('attr-name').value;
    const id = document.getElementById('attr-form-id').value;
    const errorSpan = document.getElementById('name-error');
    if (!name) { errorSpan.textContent = 'Name is required'; return; }
    fetch(window.ATTR_CONFIG.ajax_url + '?action=check_name&name=' + encodeURIComponent(name) + '&id=' + id + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { errorSpan.textContent = (data.success && !data.data.available) ? 'An attribute with this name already exists' : ''; });
}

function checkCode() {
    const code = document.getElementById('attr-code').value;
    const id = document.getElementById('attr-form-id').value;
    const errorSpan = document.getElementById('code-error');
    if (!code) { errorSpan.textContent = 'Code is required'; return; }
    fetch(window.ATTR_CONFIG.ajax_url + '?action=check_code&code=' + encodeURIComponent(code) + '&id=' + id + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { errorSpan.textContent = (data.success && !data.data.available) ? 'An attribute with this code already exists' : ''; });
}

function saveAttribute() {
    const form = document.getElementById('attr-form');
    const formData = new FormData(form);
    formData.append('values', JSON.stringify(valueRows.filter(v => v.value)));
    formData.append('action', 'save');
    formData.append('ajax', '1');
    fetch(window.ATTR_CONFIG.ajax_url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showToast('Attribute saved', 'success'); closeSlideOver(); loadAttributes(); loadStats(); }
            else { showToast(data.error || 'Failed to save', 'error'); }
        });
}

function saveAndAnother() {
    saveAttribute();
    setTimeout(() => { resetForm(); openSlideOver(); }, 500);
}

function editAttribute(id) { openSlideOver(id); }

function cloneAttribute(id) {
    fetch(window.ATTR_CONFIG.ajax_url + '?action=get&id=' + id + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const attr = data.data;
                resetForm();
                document.getElementById('attr-name').value = attr.name + ' (Copy)';
                document.getElementById('attr-code').value = attr.code + '_copy';
                document.getElementById('attr-unit').value = attr.unit || '';
                document.getElementById('attr-group').value = attr.group_id || '';
                document.getElementById('attr-bt').value = attr.business_type_id || '';
                document.getElementById('attr-form-sort').value = (attr.sort_order || 0) + 1;
                document.getElementById('attr-required').checked = attr.is_required == 1;
                document.getElementById('attr-filterable').checked = attr.is_filterable == 1;
                document.getElementById('attr-variant').checked = attr.is_variant_forming == 1;
                document.querySelectorAll('#attr-type-grid input').forEach(radio => { if (radio.value === attr.type) radio.checked = true; });
                valueRows = (attr.values || []).map(v => ({ value: v.value, label: v.label || '', color_hex: v.color_hex || '' }));
                renderValueManager();
                const showValues = ['dropdown', 'multiselect'].includes(attr.type);
                const showUnit = attr.type === 'number';
                document.getElementById('fg-values').classList.toggle('hidden', !showValues);
                document.getElementById('fg-unit').classList.toggle('hidden', !showUnit);
                openSlideOver();
            }
        });
}

function openGroupModal(groupId) {
    if (groupId) {
        document.getElementById('group-modal-title').textContent = 'Edit Group';
    } else {
        document.getElementById('group-modal-title').textContent = 'Create New Group';
        document.getElementById('group-form-id').value = '';
        document.getElementById('group-name').value = '';
        document.getElementById('group-bt').value = '';
        document.getElementById('group-sort').value = '0';
    }
    const modal = document.getElementById('attr-group-modal');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function closeGroupModal() {
    const modal = document.getElementById('attr-group-modal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function saveGroup() {
    const form = document.getElementById('group-form');
    const formData = new FormData(form);
    formData.append('action', 'save_group');
    formData.append('ajax', '1');
    fetch(window.ATTR_CONFIG.ajax_url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showToast('Group saved', 'success'); closeGroupModal(); setTimeout(() => window.location.reload(), 500); }
            else { showToast(data.error || 'Failed', 'error'); }
        });
}

function editGroup(id, name) {
    openGroupModal(id);
    document.getElementById('group-form-id').value = id;
    document.getElementById('group-name').value = name;
}

function deleteGroup(id) {
    if (!confirm('Delete this group? Attributes will be unassigned.')) return;
    fetch(window.ATTR_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete_group', ajax: '1', id: id, csrf_token: window.ATTR_CONFIG.csrf_token })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showToast('Group deleted', 'success'); setTimeout(() => window.location.reload(), 500); }
        else { showToast(data.error || 'Failed', 'error'); }
    });
}

function applyPreset(type) {
    let attributes = [];
    switch(type) {
        case 'pharmacy': attributes = [
            { name: 'Dosage', code: 'dosage', type: 'text', values: [] },
            { name: 'Manufacturer', code: 'manufacturer', type: 'text', values: [] },
            { name: 'Prescription Required', code: 'prescription_required', type: 'boolean', values: [] },
            { name: 'Expiry Date', code: 'expiry_date', type: 'date', values: [] },
            { name: 'Batch Number', code: 'batch_number', type: 'text', values: [] }
        ]; break;
        case 'fashion': attributes = [
            { name: 'Size', code: 'size', type: 'dropdown', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL'] },
            { name: 'Color', code: 'color', type: 'color', values: ['Red', 'Blue', 'Green', 'Black', 'White'] },
            { name: 'Material', code: 'material', type: 'text', values: [] },
            { name: 'Brand', code: 'brand', type: 'text', values: [] }
        ]; break;
        case 'restaurant': attributes = [
            { name: 'Spice Level', code: 'spice_level', type: 'dropdown', values: ['Mild', 'Medium', 'Hot', 'Extra Hot'] },
            { name: 'Dietary', code: 'dietary', type: 'multiselect', values: ['Vegetarian', 'Vegan', 'Gluten-Free', 'Halal'] },
            { name: 'Preparation Time', code: 'prep_time', type: 'number', values: [] }
        ]; break;
        case 'electronics': attributes = [
            { name: 'Brand', code: 'brand', type: 'text', values: [] },
            { name: 'Model', code: 'model', type: 'text', values: [] },
            { name: 'Warranty', code: 'warranty', type: 'dropdown', values: ['6 months', '1 year', '2 years', '3 years'] },
            { name: 'Color', code: 'color', type: 'color', values: [] }
        ]; break;
        case 'butchery': attributes = [
            { name: 'Cut Type', code: 'cut_type', type: 'dropdown', values: ['Whole', 'Half', 'Quarter', 'Steaks', 'Minced'] },
            { name: 'Weight Range', code: 'weight_range', type: 'text', values: [] },
            { name: 'Grass Fed', code: 'grass_fed', type: 'boolean', values: [] }
        ]; break;
    }
    if (attributes.length && confirm('Load ' + type + ' preset attributes?')) {
        savePresetAttributes(attributes);
    }
}

function savePresetAttributes(attributes) {
    let saved = 0, failed = 0;
    attributes.forEach(attr => {
        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('ajax', '1');
        formData.append('name', attr.name);
        formData.append('code', attr.code);
        formData.append('type', attr.type);
        formData.append('status', '1');
        formData.append('csrf_token', window.ATTR_CONFIG.csrf_token);
        if (attr.values && attr.values.length) {
            formData.append('values', JSON.stringify(attr.values.map(v => ({ value: v, label: v }))));
        }
        fetch(window.ATTR_CONFIG.ajax_url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) saved++; else failed++;
                if (saved + failed === attributes.length) {
                    showToast('Preset: ' + saved + ' created, ' + failed + ' failed', saved > 0 ? 'success' : 'error');
                    loadAttributes(); loadStats();
                }
            });
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/'/g, '&#39;');
}

function escapeJs(str) {
    if (!str) return "''";
    return "'" + str.replace(/'/g, "\\'") + "'";
}
