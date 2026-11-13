/**
 * ArsipKu - Document Preview Controller
 * Handles PDF rendering, images, code/text, fallback cards, and metadata panel
 */

document.addEventListener('DOMContentLoaded', () => {
    initPreviewHandler();
});

function initPreviewHandler() {
    // Listen for preview button clicks
    document.querySelectorAll('.btn-action-preview').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const docId = btn.getAttribute('data-doc-id');
            if (docId) {
                openDocumentPreview(docId);
            }
        });
    });

    // Also double-click row to preview
    document.querySelectorAll('.document-row').forEach(row => {
        row.addEventListener('dblclick', () => {
            const docId = row.getAttribute('data-doc-id');
            if (docId) {
                openDocumentPreview(docId);
            }
        });
    });
}

window.openDocumentPreview = async function(docId) {
    const modal = document.getElementById('modal-preview');
    if (!modal) return;

    const titleEl = document.getElementById('preview-doc-title');
    const container = document.getElementById('preview-canvas-container');
    const downloadBtn = document.getElementById('preview-download-btn');
    const shareBtn = document.getElementById('preview-share-btn');
    const renameBtn = document.getElementById('preview-rename-btn');

    // Metadata panel elements
    const metaName = document.getElementById('preview-meta-name');
    const metaType = document.getElementById('preview-meta-type');
    const metaSize = document.getElementById('preview-meta-size');
    const metaOwner = document.getElementById('preview-meta-owner');
    const metaCreated = document.getElementById('preview-meta-created');
    const metaUpdated = document.getElementById('preview-meta-updated');
    const metaFolder = document.getElementById('preview-meta-folder');

    // Show loading indicator
    if (container) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center p-12 text-slate-400 gap-3">
                <svg class="w-8 h-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <p class="text-sm font-medium">Memuat pratinjau dokumen...</p>
            </div>
        `;
    }

    openModal('modal-preview');

    try {
        const res = await fetch(`api/documents.php?action=detail&id=${docId}`);
        const result = await res.json();

        if (!result.success || !result.data) {
            throw new Error(result.message || 'Gagal memuat dokumen');
        }

        const doc = result.data;

        // Set toolbar details
        if (titleEl) titleEl.textContent = doc.name;
        if (downloadBtn) {
            downloadBtn.href = doc.download_url;
            downloadBtn.setAttribute('download', doc.name);
        }

        if (shareBtn) {
            shareBtn.hidden = !doc.can_share;
            shareBtn.onclick = () => {
                closeModal('modal-preview');
                window.openShareDialog(doc.id, doc.name);
            };
        }

        if (renameBtn) {
            renameBtn.hidden = !doc.can_edit;
            renameBtn.onclick = () => {
                closeModal('modal-preview');
                const idInput = document.getElementById('rename-doc-id');
                const nameInput = document.getElementById('rename-doc-name');
                const extPill = document.getElementById('rename-doc-ext-pill');

                if (idInput && nameInput) {
                    idInput.value = doc.id;
                    const baseName = doc.name.endsWith('.' + doc.extension) 
                        ? doc.name.slice(0, -(doc.extension.length + 1)) 
                        : doc.name;
                    nameInput.value = baseName;
                    if (extPill) extPill.textContent = '.' + doc.extension;
                    openModal('modal-rename');
                }
            };
        }

        // Fill metadata panel
        if (metaName) metaName.textContent = doc.name;
        if (metaType) metaType.textContent = `${doc.meta.type} (.${doc.extension})`;
        if (metaSize) metaSize.textContent = doc.formatted_size;
        if (metaOwner) metaOwner.textContent = doc.owner_name;
        if (metaCreated) metaCreated.textContent = doc.formatted_created || '-';
        if (metaUpdated) metaUpdated.textContent = doc.formatted_date || '-';
        if (metaFolder) metaFolder.textContent = doc.folder_name || 'Arsip Utama (Tanpa Folder)';

        // Render file body according to extension
        renderFilePreview(container, doc);

    } catch (err) {
        if (container) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h4 class="text-base font-semibold text-slate-800">Pratinjau Tidak Tersedia</h4>
                    <p class="text-sm text-slate-500 max-w-sm mt-1">${err.message}</p>
                </div>
            `;
        }
    }
};

async function renderFilePreview(container, doc) {
    if (!container) return;

    const ext = doc.extension.toLowerCase();
    const fileUrl = doc.preview_url;
    const safeName = escapeHtml(doc.name);

    // 1. PDF
    if (ext === 'pdf') {
        container.innerHTML = `
            <div class="w-full h-[70vh] bg-slate-100 rounded-xl overflow-hidden shadow-inner flex flex-col">
                <iframe src="${fileUrl}#view=FitH" class="w-full h-full border-0" title="${safeName}"></iframe>
            </div>
        `;
        return;
    }

    // 2. Raster images (SVG is intentionally download-only)
    if (['png', 'jpg', 'jpeg', 'webp'].includes(ext)) {
        container.innerHTML = `
            <div class="w-full min-h-[50vh] max-h-[70vh] flex items-center justify-center bg-slate-950/5 rounded-xl p-4 overflow-auto">
                <img src="${fileUrl}" alt="${safeName}" class="max-h-[65vh] max-w-full object-contain rounded-lg shadow-md border border-slate-200/80">
            </div>
        `;
        return;
    }

    // 3. Text & Code files
    if (['txt', 'csv', 'log', 'json', 'xml'].includes(ext)) {
        try {
            const resp = await fetch(fileUrl);
            const textContent = await resp.text();
            container.innerHTML = `
                <div class="w-full max-h-[65vh] overflow-auto rounded-xl border border-slate-200 bg-slate-900 p-4 shadow-inner">
                    <pre class="text-xs font-mono text-slate-200 whitespace-pre-wrap leading-relaxed">${escapeHtml(textContent)}</pre>
                </div>
            `;
            return;
        } catch (e) {
            // fallback
        }
    }

    // 4. Fallback Presentation Card for Word, Excel, PowerPoint, ZIP, etc.
    container.innerHTML = `
        <div class="w-full py-16 px-6 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 rounded-2xl ${doc.meta.bg} flex items-center justify-center mb-4 shadow-xs">
                <span class="text-xl font-bold uppercase">${ext}</span>
            </div>
            <h4 class="text-base font-semibold text-slate-800">${safeName}</h4>
            <p class="text-sm text-slate-500 mt-1 max-w-md">
                Pratinjau inline untuk tipe dokumen <strong>${doc.meta.type}</strong> belum didukung oleh peramban web secara langsung. Anda dapat mengunduh dokumen untuk membukanya secara utuh.
            </p>
            <div class="mt-6 flex items-center gap-3">
                <a href="${doc.download_url}" class="btn-interactive inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Dokumen (${doc.formatted_size})
                </a>
            </div>
        </div>
    `;
}

function escapeHtml(string) {
    const entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;'
    };
    return String(string).replace(/[&<>"'\/]/g, s => entityMap[s]);
}
