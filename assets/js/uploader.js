/**
 * ArsipKu - Multi-File Uploader & Drag-and-Drop
 * Handles live progress, file queue, validation, and real backend storage
 */

let uploadQueue = [];
let isUploading = false;

document.addEventListener('DOMContentLoaded', () => {
    initUploader();
});

function initUploader() {
    const dropzone = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('upload-file-input');
    const btnSelectFile = document.getElementById('btn-select-file');
    const btnStartUpload = document.getElementById('btn-start-upload');
    const btnClearQueue = document.getElementById('btn-clear-queue');

    if (!dropzone || !fileInput) return;

    // Trigger file dialog
    if (btnSelectFile) {
        btnSelectFile.addEventListener('click', () => fileInput.click());
    }

    // Drag and drop events
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover', 'border-indigo-600', 'bg-indigo-50/50');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover', 'border-indigo-600', 'bg-indigo-50/50');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        handleFilesSelected(files);
    });

    fileInput.addEventListener('change', () => {
        handleFilesSelected(fileInput.files);
        fileInput.value = ''; // Reset so the same file can be selected again
    });

    if (btnStartUpload) {
        btnStartUpload.addEventListener('click', startUpload);
    }

    if (btnClearQueue) {
        btnClearQueue.addEventListener('click', clearQueue);
    }
}

function handleFilesSelected(fileList) {
    if (!fileList || fileList.length === 0) return;

    for (let i = 0; i < fileList.length; i++) {
        const file = fileList[i];
        
        // Check duplicate
        if (uploadQueue.some(item => item.file.name === file.name && item.file.size === file.size)) {
            continue;
        }

        uploadQueue.push({
            id: 'up_' + Math.random().toString(36).substring(2, 9),
            file: file,
            status: 'pending', // 'pending', 'uploading', 'success', 'error'
            progress: 0,
            errorMessage: null
        });
    }

    renderQueue();
}

function renderQueue() {
    const queueContainer = document.getElementById('upload-queue-container');
    const queueList = document.getElementById('upload-queue-list');
    const btnStart = document.getElementById('btn-start-upload');
    const btnClear = document.getElementById('btn-clear-queue');
    const queueCountBadge = document.getElementById('upload-queue-count');

    if (!queueContainer || !queueList) return;

    if (uploadQueue.length === 0) {
        queueContainer.classList.add('hidden');
        if (btnStart) btnStart.disabled = true;
        if (btnClear) btnClear.disabled = true;
        return;
    }

    queueContainer.classList.remove('hidden');
    if (btnStart) btnStart.disabled = isUploading;
    if (btnClear) btnClear.disabled = isUploading;
    if (queueCountBadge) queueCountBadge.textContent = uploadQueue.length;

    queueList.innerHTML = '';

    uploadQueue.forEach(item => {
        const row = document.createElement('div');
        row.className = 'p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex flex-col gap-2';

        const ext = item.file.name.split('.').pop().toLowerCase();
        const sizeFormatted = formatFileSize(item.file.size);

        let statusBadge = '';
        if (item.status === 'pending') {
            statusBadge = '<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-medium">Menunggu</span>';
        } else if (item.status === 'uploading') {
            statusBadge = `<span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-medium">Mengunggah ${item.progress}%</span>`;
        } else if (item.status === 'success') {
            statusBadge = '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Berhasil</span>';
        } else if (item.status === 'error') {
            statusBadge = `<span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-medium" title="${item.errorMessage || ''}">Gagal</span>`;
        }

        row.innerHTML = `
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 uppercase text-xs font-bold border border-indigo-100">
                        ${ext.substring(0, 4)}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">${item.file.name}</p>
                        <p class="text-xs text-slate-500">${sizeFormatted}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    ${statusBadge}
                    ${item.status === 'pending' && !isUploading ? `
                        <button type="button" class="text-slate-400 hover:text-rose-600 p-1" onclick="removeFromQueue('${item.id}')">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    ` : ''}
                </div>
            </div>
            ${item.status === 'uploading' ? `
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-200" style="width: ${item.progress}%"></div>
                </div>
            ` : ''}
        `;

        queueList.appendChild(row);
    });
}

window.removeFromQueue = function(id) {
    uploadQueue = uploadQueue.filter(item => item.id !== id);
    renderQueue();
};

function clearQueue() {
    if (isUploading) return;
    uploadQueue = [];
    renderQueue();
}

async function startUpload() {
    if (isUploading || uploadQueue.length === 0) return;

    const pendingItems = uploadQueue.filter(item => item.status === 'pending');
    if (pendingItems.length === 0) {
        showToast('Semua file dalam antrean sudah selesai diproses', 'info');
        return;
    }

    isUploading = true;
    renderQueue();

    const targetFolderSelect = document.getElementById('upload-destination-folder');
    const folderId = targetFolderSelect ? targetFolderSelect.value : null;

    let hasSuccess = false;

    for (let item of pendingItems) {
        item.status = 'uploading';
        item.progress = 10;
        renderQueue();

        try {
            await uploadSingleFile(item, folderId);
            item.status = 'success';
            item.progress = 100;
            hasSuccess = true;
        } catch (err) {
            item.status = 'error';
            item.errorMessage = err.message || 'Gagal mengunggah file';
        }

        renderQueue();
    }

    isUploading = false;
    renderQueue();

    if (hasSuccess) {
        showToast('Dokumen berhasil diunggah ke arsip', 'success');
        setTimeout(() => {
            closeModal('modal-upload');
            window.location.reload();
        }, 800);
    }
}

function uploadSingleFile(item, folderId) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const formData = new FormData();

        formData.append('file', item.file);
        if (folderId && folderId !== 'root') {
            formData.append('folder_id', folderId);
        }

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 90);
                item.progress = Math.max(15, percent);
                renderQueue();
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        resolve(res);
                    } else {
                        reject(new Error(res.message || 'Gagal menyimpan file'));
                    }
                } catch (e) {
                    reject(new Error('Respons server tidak valid'));
                }
            } else {
                reject(new Error(`Server error (${xhr.status})`));
            }
        });

        xhr.addEventListener('error', () => {
            reject(new Error('Koneksi terputus saat mengunggah'));
        });

        xhr.open('POST', 'api/upload.php', true);
        xhr.send(formData);
    });
}

function formatFileSize(bytes) {
    if (bytes <= 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
