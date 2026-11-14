<?php
/**
 * ArsipKu - Reusable Global Modals
 * PHP Native, No Framework
 */
?>

<!-- ========================================== -->
<!-- 1. MODAL: UPLOAD DOKUMEN                    -->
<!-- ========================================== -->
<?php if ($canWriteWorkspaceContent): ?>
<div id="modal-upload" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <?= renderSvgIcon('upload', 'w-4 h-4') ?>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Unggah Dokumen Baru</h3>
                    <p class="text-xs text-slate-500">Pilih atau letakkan dokumen ke dalam workspace ini</p>
                </div>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-upload">
                <?= renderSvgIcon('x', 'w-5 h-5') ?>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-4">
            <!-- Target Folder Selection -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                    Simpan Ke Folder
                </label>
                <select id="upload-destination-folder" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3 bg-slate-50 border">
                    <option value="root">📁 Arsip Utama (Tanpa Folder)</option>
                    <?php if (isset($flatFolders)): ?>
                        <?php foreach ($flatFolders as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= (isset($currentFolderId) && $currentFolderId === $f['id']) ? 'selected' : '' ?>>
                                📁 <?= htmlspecialchars($f['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Dropzone -->
            <div id="upload-dropzone" class="dropzone border-2 border-dashed border-slate-300 rounded-2xl p-8 text-center transition-all cursor-pointer hover:border-indigo-500 hover:bg-indigo-50/20 bg-slate-50/50 flex flex-col items-center justify-center">
                <input type="file" id="upload-file-input" class="hidden" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.webp,.svg,.txt,.csv,.zip">
                <div class="w-14 h-14 rounded-2xl bg-indigo-100/80 text-indigo-600 flex items-center justify-center mb-3 shadow-xs">
                    <?= renderSvgIcon('upload', 'w-7 h-7') ?>
                </div>
                <h4 class="text-sm font-semibold text-slate-800">Tarik & letakkan file dokumen Anda ke sini</h4>
                <p class="text-xs text-slate-500 mt-1 mb-4">Mendukung PDF, Word, Excel, Gambar, ZIP hingga 50 MB</p>
                <button type="button" id="btn-select-file" class="btn-interactive inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs shadow-xs">
                    <?= renderSvgIcon('plus', 'w-4 h-4') ?>
                    Pilih File dari Perangkat
                </button>
            </div>

            <!-- Queue Container -->
            <div id="upload-queue-container" class="hidden space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                    <span class="flex items-center gap-1.5">
                        Daftar Antrean (<span id="upload-queue-count">0</span>)
                    </span>
                    <button type="button" id="btn-clear-queue" class="text-slate-400 hover:text-rose-600 text-xs">
                        Bersihkan Semua
                    </button>
                </div>
                <div id="upload-queue-list" class="space-y-2 max-h-48 overflow-y-auto pr-1"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-200/50" data-close-modal="modal-upload">
                Batal
            </button>
            <button type="button" id="btn-start-upload" disabled class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                <?= renderSvgIcon('upload', 'w-3.5 h-3.5') ?>
                Mulai Unggah
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================== -->
<!-- 2. MODAL: UBAH NAMA DOKUMEN (RENAME)        -->
<!-- ========================================== -->
<div id="modal-rename" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-rename-document">
            <input type="hidden" id="rename-doc-id">
            
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <?= renderSvgIcon('pencil', 'w-4 h-4') ?>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Ubah Nama Dokumen</h3>
                        <p class="text-xs text-slate-500">Sesuaikan nama tampilan berkas arsip Anda</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-rename">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Baru Dokumen
                    </label>
                    <div class="flex items-center rounded-xl border border-slate-200 bg-white overflow-hidden focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                        <input type="text" id="rename-doc-name" required maxlength="120" class="flex-1 px-3.5 py-2.5 text-sm outline-none border-none focus:ring-0 text-slate-800" placeholder="Masukkan nama dokumen...">
                        <span id="rename-doc-ext-pill" class="bg-slate-100 text-slate-500 px-3 py-2.5 text-xs font-mono font-medium border-l border-slate-200 select-none">
                            .pdf
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1.5">Ekstensi file dipertahankan secara otomatis untuk keamanan format.</p>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-200/50" data-close-modal="modal-rename">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 3. MODAL: BUAT FOLDER BARU                 -->
<!-- ========================================== -->
<?php if ($canWriteWorkspaceContent): ?>
<div id="modal-create-folder" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-create-folder">
            <input type="hidden" id="new-folder-parent-id" value="<?= htmlspecialchars($currentFolderId ?? '') ?>">
            
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <?= renderSvgIcon('folder-plus', 'w-4 h-4') ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Buat Folder Baru</h3>
                        <p class="text-xs text-slate-500">Kelompokkan dokumen arsip secara terstruktur</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-create-folder">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Folder
                    </label>
                    <input type="text" id="new-folder-name" required maxlength="80" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3.5 border" placeholder="Contoh: Surat Masuk 2026, Laporan Pajak...">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
                        Label Warna Folder
                    </label>
                    <div class="flex items-center gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="folder_color" value="indigo" checked class="sr-only peer">
                            <span class="w-7 h-7 rounded-full bg-indigo-600 inline-block peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-indigo-600 transition-all"></span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="folder_color" value="emerald" class="sr-only peer">
                            <span class="w-7 h-7 rounded-full bg-emerald-600 inline-block peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-emerald-600 transition-all"></span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="folder_color" value="blue" class="sr-only peer">
                            <span class="w-7 h-7 rounded-full bg-blue-600 inline-block peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-blue-600 transition-all"></span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="folder_color" value="amber" class="sr-only peer">
                            <span class="w-7 h-7 rounded-full bg-amber-500 inline-block peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-amber-500 transition-all"></span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="folder_color" value="purple" class="sr-only peer">
                            <span class="w-7 h-7 rounded-full bg-purple-600 inline-block peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-purple-600 transition-all"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-200/50" data-close-modal="modal-create-folder">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">
                    Buat Folder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 4. MODAL: PINDAHKAN DOKUMEN                -->
<!-- ========================================== -->
<div id="modal-move" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-move-document">
            <input type="hidden" id="move-doc-id">
            
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <?= renderSvgIcon('move', 'w-4 h-4') ?>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Pindahkan Dokumen</h3>
                        <p class="text-xs text-slate-500">Pilih folder tujuan dokumen arsip</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-move">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Pilih Folder Tujuan
                    </label>
                    <select id="move-target-folder" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3 bg-slate-50 border">
                        <option value="root">📁 Arsip Utama (Tanpa Folder)</option>
                        <?php if (isset($flatFolders)): ?>
                            <?php foreach ($flatFolders as $f): ?>
                                <option value="<?= $f['id'] ?>">📁 <?= htmlspecialchars($f['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-200/50" data-close-modal="modal-move">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">
                    Pindahkan Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 5. MODAL: PRATINJAU DOKUMEN (PREVIEW)       -->
<!-- ========================================== -->
<div id="modal-preview" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-950/70 backdrop-blur-sm">
    <div class="modal-content w-full max-w-6xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col h-[90vh]">
        <!-- Toolbar -->
        <div class="px-6 py-3.5 border-b border-slate-200 bg-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <?= renderSvgIcon('eye', 'w-5 h-5') ?>
                </div>
                <div class="min-w-0">
                    <h3 id="preview-doc-title" class="text-sm font-semibold text-slate-800 truncate">Nama Dokumen</h3>
                    <p class="text-xs text-slate-500">Pratinjau Arsip Digital Aman</p>
                </div>
            </div>

            <!-- Toolbar Actions -->
            <div class="flex items-center gap-2 shrink-0">
                <a id="preview-download-btn" href="#" download class="btn-interactive inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">
                    <?= renderSvgIcon('download', 'w-3.5 h-3.5') ?>
                    <span>Unduh</span>
                </a>
                <button type="button" id="preview-share-btn" class="btn-interactive inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">
                    <?= renderSvgIcon('share', 'w-3.5 h-3.5') ?>
                    <span>Bagikan</span>
                </button>
                <button type="button" id="preview-rename-btn" class="btn-interactive inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">
                    <?= renderSvgIcon('pencil', 'w-3.5 h-3.5') ?>
                    <span>Ubah Nama</span>
                </button>
                <button type="button" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg ml-2" data-close-modal="modal-preview">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>
        </div>

        <!-- Preview Viewport (Split View: Canvas & Info Panel) -->
        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden bg-slate-100/60">
            <!-- Main Canvas -->
            <div id="preview-canvas-container" class="flex-1 p-4 lg:p-6 overflow-y-auto flex items-center justify-center">
                <!-- Dynamically filled by preview.js -->
            </div>

            <!-- Metadata Panel -->
            <div class="w-full lg:w-80 bg-white border-t lg:border-t-0 lg:border-l border-slate-200 p-5 overflow-y-auto shrink-0 space-y-5">
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Detail Berkas</h4>
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="text-slate-400 font-medium">Nama Dokumen</dt>
                            <dd id="preview-meta-name" class="text-slate-800 font-semibold mt-0.5 break-all">-</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 font-medium">Tipe Berkas</dt>
                            <dd id="preview-meta-type" class="text-slate-800 font-semibold mt-0.5">-</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 font-medium">Ukuran Dokumen</dt>
                            <dd id="preview-meta-size" class="text-slate-800 font-semibold mt-0.5">-</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 font-medium">Pemilik / Pengunggah</dt>
                            <dd id="preview-meta-owner" class="text-slate-800 font-semibold mt-0.5">-</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 font-medium">Folder Penempatan</dt>
                            <dd id="preview-meta-folder" class="text-slate-800 font-semibold mt-0.5">-</dd>
                        </div>
                    </dl>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Riwayat & Keamanan</h4>
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="text-slate-400 font-medium">Tanggal Dibuat</dt>
                            <dd id="preview-meta-created" class="text-slate-800 font-medium mt-0.5">-</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 font-medium">Terakhir Diubah</dt>
                            <dd id="preview-meta-updated" class="text-slate-800 font-medium mt-0.5">-</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 font-medium">Status Enkripsi</dt>
                            <dd class="text-emerald-700 font-semibold mt-0.5 flex items-center gap-1">
                                <?= renderSvgIcon('shield-check', 'w-3.5 h-3.5') ?>
                                AES-256 Cloud Encrypted
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 6. MODAL: BAGIKAN DOKUMEN                  -->
<!-- ========================================== -->
<div id="modal-share" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <?= renderSvgIcon('share', 'w-4 h-4') ?>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Bagikan Dokumen</h3>
                    <p id="share-doc-name" class="text-xs text-slate-500 truncate max-w-[220px]">Nama Dokumen</p>
                </div>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-share">
                <?= renderSvgIcon('x', 'w-5 h-5') ?>
            </button>
        </div>

        <form id="form-share-document">
            <input type="hidden" id="share-doc-id" name="id">
            <div class="p-6 space-y-5">
                <fieldset class="space-y-2.5">
                    <legend class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
                        Siapa yang dapat mengakses?
                    </legend>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50">
                        <input type="radio" name="share_scope" value="workspace" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Seluruh workspace</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Semua anggota aktif dapat melihat dokumen ini.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50">
                        <input type="radio" name="share_scope" value="selected" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Anggota tertentu</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Hanya pemilik, Owner/Admin, dan anggota terpilih yang dapat melihat.</span>
                        </span>
                    </label>
                </fieldset>

                <div id="share-recipients-section" class="space-y-2" hidden>
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Pilih anggota</label>
                        <span id="share-recipient-count" class="text-xs text-slate-500"></span>
                    </div>
                    <div id="share-member-list" class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                        <p class="p-4 text-sm text-slate-500">Memuat daftar anggota...</p>
                    </div>
                </div>

                <div id="share-permission-section" class="space-y-1.5" hidden>
                    <label for="share-permission" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Izin anggota terpilih</label>
                    <select id="share-permission" name="permission" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="view">Hanya melihat</option>
                        <option value="edit">Melihat, mengubah nama, dan memindahkan</option>
                    </select>
                </div>

                <div class="rounded-xl bg-indigo-50/50 p-3.5 border border-indigo-100 text-xs text-indigo-950 flex items-start gap-2.5">
                    <span class="text-indigo-600 shrink-0 mt-0.5"><?= renderSvgIcon('shield-check', 'w-4 h-4') ?></span>
                    <p>Anggota harus masuk ke akun ArsipKu dan menjadi anggota aktif workspace. Tautan berkas saja tidak memberikan akses.</p>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800" data-close-modal="modal-share">Batal</button>
                <button type="submit" id="share-save-btn" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">Simpan akses</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 7. MODAL: KONFIRMASI PINDAH KE SAMPAH      -->
<!-- ========================================== -->
<div id="modal-confirm-delete" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden text-center p-6">
        <input type="hidden" id="delete-doc-id">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 mx-auto flex items-center justify-center mb-3">
            <?= renderSvgIcon('trash', 'w-6 h-6') ?>
        </div>
        <h3 class="text-base font-semibold text-slate-800">Pindahkan ke Sampah?</h3>
        <p class="text-xs text-slate-500 mt-1 mb-5">
            Dokumen <strong id="delete-doc-name-target" class="text-slate-700"></strong> akan dipindahkan ke folder Sampah dan dapat dipulihkan kembali jika diperlukan.
        </p>

        <div class="flex items-center justify-center gap-2.5">
            <button type="button" class="flex-1 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100" data-close-modal="modal-confirm-delete">
                Batal
            </button>
            <button type="button" id="btn-confirm-delete-doc" class="btn-interactive flex-1 py-2 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-xs">
                Pindahkan
            </button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 8. MODAL: KONFIRMASI HAPUS PERMANEN        -->
<!-- ========================================== -->
<div id="modal-confirm-permanent-delete" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden text-center p-6">
        <input type="hidden" id="perm-delete-doc-id">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 mx-auto flex items-center justify-center mb-3">
            <?= renderSvgIcon('alert-triangle', 'w-6 h-6') ?>
        </div>
        <h3 class="text-base font-semibold text-slate-800">Hapus Permanen?</h3>
        <p class="text-xs text-rose-600 font-medium mt-1 mb-5">
            Peringatan: Tindakan ini tidak dapat dibatalkan! File dokumen <strong id="perm-delete-doc-name" class="text-slate-800"></strong> dan seluruh riwayatnya akan dihapus selamanya dari penyimpanan.
        </p>

        <div class="flex items-center justify-center gap-2.5">
            <button type="button" class="flex-1 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100" data-close-modal="modal-confirm-permanent-delete">
                Batal
            </button>
            <button type="button" id="btn-confirm-perm-delete-doc" class="btn-interactive flex-1 py-2 text-xs font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs">
                Hapus Selamanya
            </button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 9. MODAL: BUAT WORKSPACE BARU             -->
<!-- ========================================== -->
<div id="modal-create-workspace" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-create-workspace" onsubmit="event.preventDefault(); const name = document.getElementById('new-ws-name').value.trim(); const type = document.getElementById('new-ws-type').value; if(!name){showToast('Nama workspace wajib diisi', 'warning'); return;} fetch('api/workspaces.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'create', name, type})}).then(r=>r.json()).then(d=>{if(d.success){showToast(d.message); closeModal('modal-create-workspace'); window.location.href = 'index.php?ws=' + d.workspace.slug;}else{showToast(d.message, 'error');}});">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <?= renderSvgIcon('plus', 'w-4 h-4') ?>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Buat Workspace Baru</h3>
                        <p class="text-xs text-slate-500">Buat ruang kerja terisolasi untuk tim atau organisasi</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-create-workspace">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Workspace
                    </label>
                    <input type="text" id="new-ws-name" required maxlength="80" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3.5 border" placeholder="Contoh: PT Kreasi Nusantara, Sekolah Bina Bangsa...">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Kategori Ruang Kerja
                    </label>
                    <select id="new-ws-type" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3 bg-slate-50 border">
                        <option value="company">Perusahaan / Korporat (Kapasitas 50 GB)</option>
                        <option value="personal">Personal / Individu (Kapasitas 10 GB)</option>
                        <option value="school">Institusi Pendidikan (Kapasitas 25 GB)</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-200/50" data-close-modal="modal-create-workspace">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">
                    Buat Workspace
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 10. MODAL: UBAH NAMA FOLDER (RENAME)      -->
<!-- ========================================== -->
<?php if ($canWriteWorkspaceContent): ?>
<div id="modal-rename-folder" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-rename-folder" onsubmit="event.preventDefault(); const id = document.getElementById('rename-folder-id').value; const name = document.getElementById('rename-folder-name').value.trim(); if(!name){showToast('Nama folder tidak boleh kosong', 'warning'); return;} fetch('api/folders.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'rename', id, name})}).then(r=>r.json()).then(d=>{if(d.success){showToast(d.message); closeModal('modal-rename-folder'); location.reload();}else{showToast(d.message, 'error');}});">
            <input type="hidden" id="rename-folder-id">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <?= renderSvgIcon('pencil', 'w-4 h-4') ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Ubah Nama Folder</h3>
                        <p class="text-xs text-slate-500">Sesuaikan nama direktori pengelompokan berkas</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-rename-folder">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Baru Folder
                    </label>
                    <input type="text" id="rename-folder-name" required maxlength="80" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3.5 border" placeholder="Nama folder...">
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-200/50" data-close-modal="modal-rename-folder">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- 11. MODAL: KOSONGKAN TEMPAT SAMPAH         -->
<!-- ========================================== -->
<div id="modal-empty-trash" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-sm bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden text-center p-6">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 mx-auto flex items-center justify-center mb-3">
            <?= renderSvgIcon('trash', 'w-6 h-6') ?>
        </div>
        <h3 class="text-base font-semibold text-slate-800">Kosongkan Tempat Sampah?</h3>
        <p class="text-xs text-rose-600 font-medium mt-1 mb-5">
            Seluruh berkas yang saat ini berada di dalam Tempat Sampah akan dihapus selamanya secara permanen. Tindakan ini tidak dapat dibatalkan.
        </p>

        <div class="flex items-center justify-center gap-2.5">
            <button type="button" class="flex-1 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100" data-close-modal="modal-empty-trash">
                Batal
            </button>
            <button type="button" id="btn-confirm-empty-trash" class="btn-interactive flex-1 py-2 text-xs font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs" onclick="fetch('api/documents.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'empty_trash'})}).then(r=>r.json()).then(d=>{if(d.success){showToast(d.message); closeModal('modal-empty-trash'); location.reload();}else{showToast(d.message,'error');}});">
                Kosongkan Sekarang
            </button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 12. MODAL: KONFIRMASI HAPUS WORKSPACE      -->
<!-- ========================================== -->
<?php if (($currentWorkspaceRole ?? null) === 'Owner' && count($allWorkspaces) > 1): ?>
<div id="modal-delete-workspace" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs" hidden>
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form onsubmit="event.preventDefault(); const confirmName = document.getElementById('delete-ws-confirm-name').value.trim(); fetch('api/workspaces.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete', confirm_name: confirmName})}).then(r=>r.json()).then(d=>{if(d.success){showToast(d.message); closeModal('modal-delete-workspace'); window.location.href='index.php';}else{showToast(d.message, 'error');}});">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-rose-50/50">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center">
                        <?= renderSvgIcon('alert-triangle', 'w-4 h-4') ?>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Hapus Workspace</h3>
                        <p class="text-xs text-rose-600 font-medium">Tindakan destruktif & permanen</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-delete-workspace">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <p class="text-xs text-slate-600 leading-relaxed">
                    Tindakan ini akan menghapus seluruh data arsip, folder, dokumen, riwayat aktivitas, dan anggota di workspace ini selamanya.
                </p>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                    <span class="text-slate-500">Ketik nama workspace berikut untuk konfirmasi:</span>
                    <strong class="block text-slate-900 font-mono mt-1 select-all"><?= htmlspecialchars($currentWs['name'] ?? '') ?></strong>
                </div>
                <div>
                    <input type="text" id="delete-ws-confirm-name" required class="w-full text-sm rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500 py-2.5 px-3.5 border" placeholder="Ketik persis nama workspace di atas...">
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl" data-close-modal="modal-delete-workspace">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs">
                    Saya Mengerti, Hapus Workspace
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($currentWorkspaceRole ?? null, ['Owner', 'Admin'], true)): ?>
<div id="modal-create-invite-link" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-create-invite-link" class="ark-form">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Buat share link anggota</h3>
                    <p class="text-xs text-slate-500">Tentukan role dan cara anggota bergabung.</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-create-invite-link" aria-label="Tutup">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 ark-form">
                <div class="ark-field-grid">
                    <label class="ark-field">
                        <span class="ark-label">Role anggota</span>
                        <select class="ark-select" name="role" required>
                            <option value="Viewer">Viewer</option>
                            <option value="Editor">Editor</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </label>
                    <label class="ark-field">
                        <span class="ark-label">Masa berlaku (hari)</span>
                        <input class="ark-input" type="number" name="expires_in_days" value="7" min="1" max="30" required>
                    </label>
                </div>
                <label class="ark-field">
                    <span class="ark-label">Batas penggunaan <span class="ark-field__optional">opsional</span></span>
                    <input class="ark-input" type="number" name="max_uses" min="1" max="1000" placeholder="Kosongkan untuk tanpa batas">
                </label>
                <label class="ark-check">
                    <input class="ark-check__input" type="checkbox" name="approval_required" value="1" checked>
                    <span class="ark-check__box" aria-hidden="true"></span>
                    <span>Perlu persetujuan Owner/Admin sebelum anggota mendapat akses</span>
                </label>
                <p class="ark-helper">Jika tidak dicentang, pengguna dapat langsung bergabung setelah mengonfirmasi lewat link.</p>
                <div id="invite-link-result" class="ark-notice" role="status" aria-live="polite" hidden>
                    <div class="ark-field">
                        <span class="ark-label">Salin link ini sebelum menutup modal</span>
                        <div style="display:flex;gap:8px">
                            <input class="ark-input" id="invite-link-url" type="text" readonly>
                            <button type="button" class="ark-btn ark-btn--secondary" data-copy-invite-link>Salin</button>
                        </div>
                        <div class="ark-cluster" style="gap:8px;margin-top:8px">
                            <a class="ark-btn ark-btn--secondary" id="invite-link-open" href="#" target="_blank"
                                rel="noopener noreferrer">Buka undangan</a>
                        </div>
                        <p class="ark-helper">Token link hanya ditampilkan sekali dan tidak bisa dipulihkan.</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="ark-btn ark-btn--secondary" data-close-modal="modal-create-invite-link">Tutup</button>
                <button type="submit" class="ark-btn ark-btn--primary">Buat share link</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ========================================== -->
<!-- 13. MODAL: UNDANG ANGGOTA                  -->
<!-- ========================================== -->
<!-- Dipindah dari views/pages/members.php: .ark-content kini stacking context
     (z-index 0), jadi overlay apa pun yang dirender di dalam view akan tertindih
     header/sidebar. Semua modal berada di berkas ini, di luar kontainer konten. -->
<div id="modal-invite-member" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
    <div class="modal-content w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <form id="form-invite-member">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <?= renderSvgIcon('users', 'w-4 h-4') ?>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Undang Anggota Baru</h3>
                        <p class="text-xs text-slate-500">Undang akun terdaftar untuk menerima notifikasi</p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg" data-close-modal="modal-invite-member">
                    <?= renderSvgIcon('x', 'w-5 h-5') ?>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label for="invite-email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alamat Email Rekan Kerja
                    </label>
                    <input type="email" id="invite-email" required class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3.5 border" placeholder="nama@perusahaan.com">
                </div>

                <div>
                    <label for="invite-role" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Hak Akses (Role)
                    </label>
                    <select id="invite-role" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5 px-3 bg-slate-50 border">
                        <option value="Admin">Admin (Bisa mengelola anggota & folder)</option>
                        <option value="Editor">Editor (Bisa melihat, mengunggah & mengedit dokumen)</option>
                        <option value="Viewer">Viewer (Hanya dapat melihat dan mengunduh)</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl" data-close-modal="modal-invite-member">
                    Batal
                </button>
                <button type="submit" class="btn-interactive px-5 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs">
                    Kirim Undangan
                </button>
            </div>
        </form>
    </div>
</div>
