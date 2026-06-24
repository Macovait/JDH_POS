/**
 * Attribute Terms Management - JavaScript
 * Jakababa POS - SaaS Version
 */

let deleteTargetId = null;
let isEditing = false;
let dragSrcEl = null;

function init() {
    loadTerms();
    setupDragAndDrop();
    setupAutoSlug();
    if (window.TERM_CONFIG.is_color) {
        setupColorSync();
    }
}

function setupAutoSlug() {
    const nameInput = document.getElementById('term-name');
    const slugInput = document.getElementById('term-slug');
    if (!nameInput || !slugInput) return;

    nameInput.addEventListener('blur', function() {
        if (!slugInput.value && nameInput.value) {
            slugInput.value = nameInput.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });
}

function setupColorSync() {
    const colorInput = document.getElementById('term-color');
    const hexInput = document.getElementById('term-color-hex');
    if (!colorInput || !hexInput) return;

    colorInput.addEventListener('input', function() {
        hexInput.value = colorInput.value;
    });
}

function loadTerms() {
    fetch(window.TERM_CONFIG.ajax_url + '&action=list&ajax=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data) {
            renderTermsTable(data.data.terms);
        }
    })
    .catch(err => console.error('Load terms error:', err));
}

function renderTermsTable(terms) {
    const tbody = document.getElementById('terms-tbody');
    const isColor = window.TERM_CONFIG.is_color;
    const colspan = isColor ? 5 : 4;

    if (!terms || terms.length === 0) {
        tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="empty-state">' +
            '<div class="empty-state-icon"><i class="fas fa-list text-gray-500 text-xl"></i></div>' +
            '<p class="text-gray-400 font-medium">No terms found</p>' +
            '<p class="text-gray-500 text-sm mt-1">Add your first term using the form on the right.</p>' +
            '</td></tr>';
        return;
    }

    tbody.innerHTML = terms.map(term => {
        let cells = '';
        cells += '<td><i class="fas fa-grip-vertical drag-handle"></i></td>';
        cells += '<td>' +
            '<div class="flex items-center gap-2">' +
            (isColor && term.color_hex ? '<span class="color-swatch" style="background-color: ' + escapeHtml(term.color_hex) + '"></span>' : '') +
            '<span class="font-medium text-white">' + escapeHtml(term.value) + '</span>' +
            '</div></td>';
        cells += '<td><code class="term-slug">' + escapeHtml(term.label || term.value) + '</code></td>';
        if (isColor) {
            cells += '<td><code class="term-slug">' + escapeHtml(term.color_hex || '—') + '</code></td>';
        }
        cells += '<td>' +
            '<div class="term-actions">' +
            '<button onclick="editTerm(' + term.id + ')" title="Edit"><i class="fas fa-pen"></i></button>' +
            '<button onclick="deleteTerm(' + term.id + ', ' + escapeJs(term.value) + ')" class="delete" title="Delete"><i class="fas fa-trash"></i></button>' +
            '</div></td>';
        return '<tr data-id="' + term.id + '" draggable="true">' + cells + '</tr>';
    }).join('');

    setupDragAndDrop();
}

function saveTerm() {
    const name = document.getElementById('term-name').value.trim();
    if (!name) {
        showToast('Name is required', 'error');
        return;
    }

    const formData = new URLSearchParams({
        action: 'save',
        ajax: '1',
        csrf_token: window.TERM_CONFIG.csrf_token,
        id: document.getElementById('term-id').value,
        name: name,
        slug: document.getElementById('term-slug').value.trim(),
        description: document.getElementById('term-description').value.trim()
    });

    if (window.TERM_CONFIG.is_color) {
        formData.append('color_hex', document.getElementById('term-color').value);
    }

    fetch(window.TERM_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.data.message || 'Saved', 'success');
            resetForm();
            loadTerms();
        } else {
            showToast(data.error || 'Save failed', 'error');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        showToast('Save failed', 'error');
    });
}

function editTerm(id) {
    fetch(window.TERM_CONFIG.ajax_url + '&action=get&id=' + id + '&ajax=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data) {
            const term = data.data;
            document.getElementById('term-id').value = term.id;
            document.getElementById('term-name').value = term.value;
            document.getElementById('term-slug').value = term.label || '';
            document.getElementById('term-description').value = term.description || '';
            if (window.TERM_CONFIG.is_color) {
                const color = term.color_hex || '#000000';
                document.getElementById('term-color').value = color;
                document.getElementById('term-color-hex').value = color;
            }
            document.getElementById('form-title').textContent = 'Edit ' + term.value;
            document.getElementById('btn-save').innerHTML = '<i class="fas fa-save"></i> Update Term';
            document.getElementById('btn-cancel').style.display = '';
            isEditing = true;
            document.getElementById('term-name').focus();
        }
    })
    .catch(err => console.error('Edit load error:', err));
}

function resetForm() {
    document.getElementById('term-form').reset();
    document.getElementById('term-id').value = '';
    document.getElementById('form-title').textContent = 'Add new ' + (window.TERM_CONFIG.attr_name || 'Term');
    document.getElementById('btn-save').innerHTML = '<i class="fas fa-plus"></i> Add Term';
    document.getElementById('btn-cancel').style.display = 'none';
    if (window.TERM_CONFIG.is_color) {
        document.getElementById('term-color').value = '#000000';
        document.getElementById('term-color-hex').value = '#000000';
    }
    isEditing = false;
}

function deleteTerm(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteTermName').textContent = name;
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

    fetch(window.TERM_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
            action: 'delete',
            ajax: '1',
            id: id,
            csrf_token: window.TERM_CONFIG.csrf_token
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Term deleted', 'success');
            loadTerms();
        } else {
            showToast(data.error || 'Delete failed', 'error');
        }
    })
    .catch(err => console.error('Delete error:', err));
}

// Drag and Drop Reordering
function setupDragAndDrop() {
    const rows = document.querySelectorAll('#terms-tbody tr[draggable="true"]');
    rows.forEach(row => {
        row.addEventListener('dragstart', handleDragStart);
        row.addEventListener('dragover', handleDragOver);
        row.addEventListener('dragenter', handleDragEnter);
        row.addEventListener('dragleave', handleDragLeave);
        row.addEventListener('drop', handleDrop);
        row.addEventListener('dragend', handleDragEnd);
    });
}

function handleDragStart(e) {
    dragSrcEl = this;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
    this.classList.add('dragging');
}

function handleDragOver(e) {
    if (e.preventDefault) e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragEnter(e) {
    this.classList.add('bg-gray-700');
}

function handleDragLeave(e) {
    this.classList.remove('bg-gray-700');
}

function handleDrop(e) {
    if (e.stopPropagation) e.stopPropagation();
    if (dragSrcEl !== this) {
        const tbody = document.getElementById('terms-tbody');
        const allRows = Array.from(tbody.querySelectorAll('tr[draggable="true"]'));
        const srcIndex = allRows.indexOf(dragSrcEl);
        const targetIndex = allRows.indexOf(this);

        if (srcIndex < targetIndex) {
            this.parentNode.insertBefore(dragSrcEl, this.nextSibling);
        } else {
            this.parentNode.insertBefore(dragSrcEl, this);
        }

        saveOrder();
    }
    return false;
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    document.querySelectorAll('#terms-tbody tr').forEach(row => row.classList.remove('bg-gray-700'));
}

function saveOrder() {
    const rows = document.querySelectorAll('#terms-tbody tr[draggable="true"]');
    const orders = [];
    rows.forEach((row, index) => {
        orders.push({ id: parseInt(row.dataset.id), sort_order: index + 1 });
    });

    fetch(window.TERM_CONFIG.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
            action: 'reorder',
            ajax: '1',
            csrf_token: window.TERM_CONFIG.csrf_token,
            orders: JSON.stringify(orders)
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            showToast(data.error || 'Reorder failed', 'error');
        }
    })
    .catch(err => console.error('Reorder error:', err));
}

function showToast(message, type) {
    const toast = document.getElementById('term-toast');
    const msg = document.getElementById('term-toast-msg');
    if (!toast || !msg) return;
    msg.textContent = message;
    toast.className = type === 'error' ? 'toast-error fixed bottom-4 right-4 z-50' : 'toast-success fixed bottom-4 right-4 z-50';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeJs(str) {
    return JSON.stringify(str);
}

document.addEventListener('DOMContentLoaded', init);
