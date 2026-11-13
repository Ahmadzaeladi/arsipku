/**
 * ArsipKu - Documents Interactions & State
 * Handles live search, filters, multi-select, rename, delete, preview
 */

let selectedDocIds = new Set();

document.addEventListener('DOMContentLoaded', () => {
    initDocumentsPage();
});

function initDocumentsPage() {
    setupViewToggle();
    setupSearchAndFilters();
    setupMultiSelect();
    setupDocumentActions();
    setupRenameForm();
    setupCreateFolderForm();
    setupMoveForm();
    setupDeleteConfirm();
}

/**
 * View Toggle (List vs Grid)
 */
function setupViewToggle() {
    const btnList = document.getElementById('btn-view-list');
    const btnGrid = document.getElementById('btn-view-grid');
    const viewList = document.getElementById('view-list-container');
    const viewGrid = document.getElementById('view-grid-container');

    if (!btnList || !btnGrid || !viewList || !viewGrid) return;

    const savedView = localStorage.getItem('arsipku_doc_view') || 'list';
    applyView(savedView);

    btnList.addEventListener('click', () => {
        applyView('list');
        localStorage.setItem('arsipku_doc_view', 'list');
    });

    btnGrid.addEventListener('click', () => {
        applyView('grid');
        localStorage.setItem('arsipku_doc_view', 'grid');
    });

    function applyView(view) {
        const grid = view === 'grid';
        // Atribut `hidden`, bukan kelas framework: tidak ada CSS tambahan yang
        // dibutuhkan supaya pengalih ini tetap benar setelah Tailwind dicabut.
        viewList.hidden = grid;
        viewGrid.hidden = !grid;
        btnList.setAttribute('aria-pressed', String(!grid));
        btnGrid.setAttribute('aria-pressed', String(grid));
    }
}

/**
 * Search & Filters with URL State preservation
 */
function setupSearchAndFilters() {
    const searchInput = document.getElementById('doc-search');
    const typeFilter = document.getElementById('filter-type');
    const dateFilter = document.getElementById('filter-date');
    const sortFilter = document.getElementById('filter-sort');

    let debounceTimer;

    const applyFilters = () => {
        const url = new URL(window.location.href);
        
        if (searchInput && searchInput.value.trim()) {
            url.searchParams.set('q', searchInput.value.trim());
        } else {
            url.searchParams.delete('q');
        }

        if (typeFilter && typeFilter.value) {
            url.searchParams.set('type', typeFilter.value);
        } else {
            url.searchParams.delete('type');
        }

        if (dateFilter && dateFilter.value) {
            url.searchParams.set('date_range', dateFilter.value);
        } else {
            url.searchParams.delete('date_range');
        }

        if (sortFilter && sortFilter.value) {
            url.searchParams.set('sort', sortFilter.value);
        } else {
            url.searchParams.delete('sort');
        }

        window.location.href = url.toString();
    };

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 500);
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                clearTimeout(debounceTimer);
                applyFilters();
            }
        });
    }

    if (typeFilter) typeFilter.addEventListener('change', applyFilters);
    if (dateFilter) dateFilter.addEventListener('change', applyFilters);
    if (sortFilter) sortFilter.addEventListener('change', applyFilters);
}

/**
 * Multi-Select Logic
 */
function setupMultiSelect() {
    const selectAllCheckbox = document.getElementById('select-all-docs');
    const rowCheckboxes = document.querySelectorAll('.doc-checkbox');
    const multiBar = document.getElementById('multi-select-bar');
    const countBadge = document.getElementById('multi-selected-count');
    const btnCancel = document.getElementById('btn-multi-cancel');

    if (!multiBar) return;

    function updateMultiSelectState() {
        const count = selectedDocIds.size;
        if (countBadge) countBadge.textContent = count;

        if (count > 0) {
            multiBar.classList.add('show');
        } else {
            multiBar.classList.remove('show');
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = rowCheckboxes.length > 0 && count === rowCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < rowCheckboxes.length;
            const selectAllVisual = selectAllCheckbox.nextElementSibling;
            if (selectAllVisual) {
                selectAllVisual.setAttribute(
                    'aria-checked',
                    selectAllCheckbox.indeterminate ? 'mixed' : String(selectAllCheckbox.checked)
                );
            }
        }

        // Highlight selected rows
        document.querySelectorAll('.document-row').forEach(row => {
            row.classList.toggle('is-selected', selectedDocIds.has(row.getAttribute('data-doc-id')));
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', (e) => {
            const docId = cb.getAttribute('data-doc-id');
            if (cb.checked) {
                selectedDocIds.add(docId);
            } else {
                selectedDocIds.delete(docId);
            }
            updateMultiSelectState();
        });
    });

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            if (selectAllCheckbox.checked) {
                rowCheckboxes.forEach(cb => {
                    cb.checked = true;
                    selectedDocIds.add(cb.getAttribute('data-doc-id'));
                });
            } else {
                rowCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
                selectedDocIds.clear();
            }
            updateMultiSelectState();
        });
    }

    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            selectedDocIds.clear();
            rowCheckboxes.forEach(cb => cb.checked = false);
            updateMultiSelectState();
        });
    }

    // Multi-Delete Button (Soft delete to trash)
    const btnMultiDelete = document.getElementById('btn-multi-delete');
    if (btnMultiDelete) {
        btnMultiDelete.addEventListener('click', async () => {
            if (selectedDocIds.size === 0) return;
            if (!confirm(`Pindahkan ${selectedDocIds.size} dokumen terpilih ke Sampah?`)) return;

            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'multi_action',
                        sub_action: 'delete',
                        ids: Array.from(selectedDocIds)
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal menghapus dokumen', 'error');
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan', 'error');
            }
        });
    }

    // Multi-Restore Button (From trash)
    const btnMultiRestore = document.getElementById('btn-multi-restore');
    if (btnMultiRestore) {
        btnMultiRestore.addEventListener('click', async () => {
            if (selectedDocIds.size === 0) return;
            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'multi_action',
                        sub_action: 'restore',
                        ids: Array.from(selectedDocIds)
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal memulihkan dokumen', 'error');
                }
            } catch (err) {
                showToast('Gagal memulihkan dokumen', 'error');
            }
        });
    }

    // Multi-Permanent-Delete Button
    const btnMultiPermDelete = document.getElementById('btn-multi-perm-delete');
    if (btnMultiPermDelete) {
        btnMultiPermDelete.addEventListener('click', async () => {
            if (selectedDocIds.size === 0) return;
            if (!confirm(`Peringatan: Hapus PERMANEN ${selectedDocIds.size} dokumen terpilih? Tindakan ini tidak dapat dibatalkan.`)) return;

            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'multi_action',
                        sub_action: 'permanent_delete',
                        ids: Array.from(selectedDocIds)
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal menghapus permanen', 'error');
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan', 'error');
            }
        });
    }
}

/**
 * Document Row & Card Actions
 */
function setupDocumentActions() {
    // Favorite Toggle
    document.querySelectorAll('.btn-toggle-favorite').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const docId = btn.getAttribute('data-doc-id');
            if (!docId) return;

            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'toggle_favorite', id: docId})
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    // Negara favorit dibaca dari aria-pressed; bentuk bintang ikut
                    // ditukar lewat registry ikon yang sama.
                    const on = !!data.is_favorite;
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    btn.title = on ? 'Hapus dari favorit' : 'Tandai favorit';
                    const host = btn.querySelector('[data-icon]');
                    if (host && window.ArkIcons) host.innerHTML = window.ArkIcons.markup(on ? 'star-fill' : 'star');
                }
            } catch (err) {
                showToast('Gagal mengubah status favorit', 'error');
            }
        });
    });

    // Rename Trigger
    document.querySelectorAll('.btn-action-rename').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const docId = btn.getAttribute('data-doc-id');
            const docName = btn.getAttribute('data-doc-name');
            const docExt = btn.getAttribute('data-doc-ext');

            const idInput = document.getElementById('rename-doc-id');
            const nameInput = document.getElementById('rename-doc-name');
            const extPill = document.getElementById('rename-doc-ext-pill');

            if (idInput && nameInput) {
                idInput.value = docId;
                // Pre-fill name without extension
                const baseName = docName.endsWith('.' + docExt) 
                    ? docName.slice(0, -(docExt.length + 1)) 
                    : docName;
                nameInput.value = baseName;
                if (extPill) extPill.textContent = '.' + docExt;
                openModal('modal-rename');
            }
        });
    });

    // Move Trigger
    document.querySelectorAll('.btn-action-move').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const docId = btn.getAttribute('data-doc-id');
            const currentFolderId = btn.getAttribute('data-doc-folder-id');
            
            const idInput = document.getElementById('move-doc-id');
            const folderSelect = document.getElementById('move-target-folder');
            if (idInput) idInput.value = docId;
            if (folderSelect && currentFolderId) folderSelect.value = currentFolderId;

            openModal('modal-move');
        });
    });

    // Delete Trigger (Soft delete to trash)
    document.querySelectorAll('.btn-action-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const docId = btn.getAttribute('data-doc-id');
            const docName = btn.getAttribute('data-doc-name');

            const idInput = document.getElementById('delete-doc-id');
            const nameTarget = document.getElementById('delete-doc-name-target');
            if (idInput) idInput.value = docId;
            if (nameTarget) nameTarget.textContent = `"${docName}"`;

            openModal('modal-confirm-delete');
        });
    });

    // Restore from Trash Trigger
    document.querySelectorAll('.btn-action-restore').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const docId = btn.getAttribute('data-doc-id');
            if (!docId) return;

            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'restore', id: docId})
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal memulihkan dokumen', 'error');
                }
            } catch (err) {
                showToast('Gagal memulihkan dokumen', 'error');
            }
        });
    });

    // Permanent Delete Trigger
    document.querySelectorAll('.btn-action-permanent-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const docId = btn.getAttribute('data-doc-id');
            const docName = btn.getAttribute('data-doc-name');

            const idInput = document.getElementById('perm-delete-doc-id');
            const nameTarget = document.getElementById('perm-delete-doc-name');
            if (idInput) idInput.value = docId;
            if (nameTarget) nameTarget.textContent = `"${docName}"`;

            openModal('modal-confirm-permanent-delete');
        });
    });

    // Share Trigger
    document.querySelectorAll('.btn-action-share').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const docName = btn.getAttribute('data-doc-name');
            const shareUrl = window.location.origin + window.location.pathname + '?page=preview&id=' + btn.getAttribute('data-doc-id');

            const titleTarget = document.getElementById('share-doc-name');
            const urlInput = document.getElementById('share-link-input');
            if (titleTarget) titleTarget.textContent = docName;
            if (urlInput) urlInput.value = shareUrl;

            openModal('modal-share');
        });
    });
}

/**
 * Rename Form Submission
 */
function setupRenameForm() {
    const form = document.getElementById('form-rename-document');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('rename-doc-id').value;
        const nameInput = document.getElementById('rename-doc-name');
        const extPill = document.getElementById('rename-doc-ext-pill');
        const ext = extPill ? extPill.textContent.trim() : '';

        const baseName = nameInput.value.trim();
        if (!baseName) {
            showToast('Nama dokumen tidak boleh kosong', 'warning');
            nameInput.focus();
            return;
        }

        const fullName = baseName + ext;
        const submitBtn = form.querySelector('button[type=submit]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const res = await fetch('api/documents.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'rename',
                    id: id,
                    name: fullName
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('modal-rename');
                // Refresh list or update UI directly
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Gagal mengubah nama', 'error');
            }
        } catch (err) {
            showToast('Terjadi kesalahan jaringan', 'error');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}

/**
 * Create Folder Form Submission
 */
function setupCreateFolderForm() {
    const form = document.getElementById('form-create-folder');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameInput = document.getElementById('new-folder-name');
        const parentInput = document.getElementById('new-folder-parent-id');
        const colorInput = document.querySelector('input[name="folder_color"]:checked');

        const name = nameInput.value.trim();
        if (!name) {
            showToast('Nama folder wajib diisi', 'warning');
            nameInput.focus();
            return;
        }

        const submitBtn = form.querySelector('button[type=submit]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const res = await fetch('api/folders.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'create',
                    name: name,
                    parent_id: parentInput ? parentInput.value : null,
                    color: colorInput ? colorInput.value : 'indigo'
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('modal-create-folder');
                nameInput.value = '';
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Gagal membuat folder', 'error');
            }
        } catch (err) {
            showToast('Terjadi kesalahan jaringan', 'error');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}

/**
 * Move Document Form Submission
 */
function setupMoveForm() {
    const form = document.getElementById('form-move-document');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const docId = document.getElementById('move-doc-id').value;
        const targetFolder = document.getElementById('move-target-folder').value;

        try {
            let res;
            if (selectedDocIds.size > 0 && !docId) {
                // Multi-move
                res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'multi_action',
                        sub_action: 'move',
                        ids: Array.from(selectedDocIds),
                        target_folder_id: targetFolder
                    })
                });
            } else {
                // Single move
                res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'move',
                        id: docId,
                        folder_id: targetFolder
                    })
                });
            }

            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('modal-move');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message || 'Gagal memindahkan dokumen', 'error');
            }
        } catch (err) {
            showToast('Gagal memindahkan dokumen', 'error');
        }
    });
}

/**
 * Delete Confirmation
 */
function setupDeleteConfirm() {
    // Soft Delete Confirm
    const btnConfirmDelete = document.getElementById('btn-confirm-delete-doc');
    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            const docId = document.getElementById('delete-doc-id').value;
            if (!docId) return;

            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete', id: docId})
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('modal-confirm-delete');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal menghapus dokumen', 'error');
                }
            } catch (err) {
                showToast('Gagal menghapus dokumen', 'error');
            }
        });
    }

    // Permanent Delete Confirm
    const btnConfirmPerm = document.getElementById('btn-confirm-perm-delete-doc');
    if (btnConfirmPerm) {
        btnConfirmPerm.addEventListener('click', async () => {
            const docId = document.getElementById('perm-delete-doc-id').value;
            if (!docId) return;

            try {
                const res = await fetch('api/documents.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'permanent_delete', id: docId})
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('modal-confirm-permanent-delete');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(data.message || 'Gagal menghapus dokumen', 'error');
                }
            } catch (err) {
                showToast('Gagal menghapus dokumen', 'error');
            }
        });
    }
}
