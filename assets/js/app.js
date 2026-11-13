/**
 * ArsipKu - Core Application JavaScript
 * Zero external JS dependencies required
 */

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

function initApp() {
    setupSidebarToggle();
    setupMobileDrawer();
    setupDropdowns();
    setupModals();
    setupKeyboardShortcuts();
    setupWorkspaceSwitcher();
    setupGlobalSearch();
    setupMemberInvitationActions();
}

/**
 * Global Search Header
 */
function setupGlobalSearch() {
    const globalSearch = document.getElementById('global-search');
    if (!globalSearch) return;

    globalSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const query = globalSearch.value.trim();
            const currentUrl = new URL(window.location.href);
            const isDocPage = currentUrl.searchParams.get('page') === 'documents';

            if (isDocPage) {
                const docSearch = document.getElementById('doc-search');
                if (docSearch) {
                    docSearch.value = query;
                    docSearch.dispatchEvent(new Event('input'));
                }
            } else {
                currentUrl.searchParams.set('page', 'documents');
                if (query) {
                    currentUrl.searchParams.set('q', query);
                } else {
                    currentUrl.searchParams.delete('q');
                }
                window.location.href = currentUrl.toString();
            }
        }
    });
}

/**
 * Toast Notification System
 */
window.showToast = function(message, type = 'success', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-item flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium transition-all ${
        type === 'success' ? 'bg-white border-emerald-200 text-slate-800' :
        type === 'error' ? 'bg-white border-rose-200 text-slate-800' :
        type === 'warning' ? 'bg-white border-amber-200 text-slate-800' :
        'bg-white border-indigo-200 text-slate-800'
    }`;

    const iconSvg = 
        type === 'success' ? '<span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></span>' :
        type === 'error' ? '<span class="w-6 h-6 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></span>' :
        type === 'warning' ? '<span class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></span>' :
        '<span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>';

    toast.innerHTML = `
        ${iconSvg}
        <span class="flex-1">${message}</span>
        <button type="button" class="text-slate-400 hover:text-slate-600 ml-2" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 250);
    }, duration);
};

/**
 * Modal Management
 */
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.hidden = false;
    modal.classList.add('active');
    document.body.classList.add('overflow-hidden');

    // Auto-focus first input if any
    const firstInput = modal.querySelector('input:not([type=hidden]), textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('active');
    window.setTimeout(() => {
        if (!modal.classList.contains('active')) modal.hidden = true;
    }, 200);
    
    // Check if any other modal is active
    const activeModals = document.querySelectorAll('.modal-backdrop.active');
    if (activeModals.length === 0) {
        document.body.classList.remove('overflow-hidden');
    }
};

function setupModals() {
    // Backdrop click to close
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    // Close buttons inside modals
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.getAttribute('data-close-modal');
            closeModal(modalId);
        });
    });
}

/**
 * Sidebar Collapse & Expand
 */
function setupSidebarToggle() {
    const toggleBtn = document.getElementById('btn-toggle-sidebar');
    if (!toggleBtn) return;

    // Check stored state
    const isCollapsed = localStorage.getItem('arsipku_sidebar_collapsed') === 'true';
    if (isCollapsed && window.innerWidth >= 1024) {
        document.body.classList.add('sidebar-collapsed');
    }

    toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        const nowCollapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('arsipku_sidebar_collapsed', nowCollapsed);
    });
}

/**
 * Mobile Drawer Navigation
 */
function setupMobileDrawer() {
    const openBtn = document.getElementById('btn-open-mobile-drawer');
    const closeBtn = document.getElementById('btn-close-mobile-drawer');
    const drawer = document.getElementById('mobile-drawer');
    const overlay = document.getElementById('mobile-drawer-overlay');

    if (!drawer) return;

    const openDrawer = () => {
        drawer.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeDrawer = () => {
        drawer.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    if (openBtn) openBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);
}

/**
 * Generic Dropdown Menu Handler
 */
function setupDropdowns() {
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-dropdown-toggle]');
        const openDropdowns = document.querySelectorAll('.dropdown-menu:not(.hidden)');

        if (toggle) {
            e.stopPropagation();
            const targetId = toggle.getAttribute('data-dropdown-toggle');
            const targetMenu = document.getElementById(targetId);

            // Close others
            openDropdowns.forEach(menu => {
                if (menu !== targetMenu) menu.classList.add('hidden');
            });

            if (targetMenu) {
                targetMenu.classList.toggle('hidden');
            }
            return;
        }

        // Click outside any dropdown
        if (!e.target.closest('.dropdown-menu')) {
            openDropdowns.forEach(menu => menu.classList.add('hidden'));
        }
    });
}

/**
 * Workspace Switcher Dropdown & Action
 */
function setupWorkspaceSwitcher() {
    const items = document.querySelectorAll('.ws-switch-item');
    items.forEach(item => {
        item.addEventListener('click', async (e) => {
            e.preventDefault();
            const slug = item.getAttribute('data-ws-slug');
            if (!slug) return;

            try {
                const res = await fetch('api/workspaces.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'switch', slug})
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    // Redirect to workspace
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('ws', slug);
                    window.location.href = currentUrl.toString();
                } else {
                    showToast(data.message || 'Gagal berpindah workspace', 'error');
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan', 'error');
            }
        });
    });
}

function setupMemberInvitationActions() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';
    const post = async (payload) => {
        const headers = {'Content-Type': 'application/json'};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        const response = await fetch('api/members.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Permintaan gagal diproses.');
        }
        return data;
    };

    const showError = (error) => showToast(error.message || 'Permintaan gagal diproses.', 'error');

    const inviteForm = document.getElementById('form-invite-member');
    if (inviteForm) {
        inviteForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = inviteForm.querySelector('[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                const data = await post({
                    action: 'invite',
                    email: document.getElementById('invite-email').value,
                    role: document.getElementById('invite-role').value
                });
                showToast(data.message);
                closeModal('modal-invite-member');
                window.location.reload();
            } catch (error) {
                showError(error);
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    }

    const linkForm = document.getElementById('form-create-invite-link');
    if (linkForm) {
        linkForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const fields = new FormData(linkForm);
            const submit = linkForm.querySelector('[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                const data = await post({
                    action: 'create_link',
                    role: fields.get('role'),
                    approval_required: fields.get('approval_required') === '1',
                    expires_in_days: fields.get('expires_in_days'),
                    max_uses: fields.get('max_uses')
                });
                const result = document.getElementById('invite-link-result');
                const input = document.getElementById('invite-link-url');
                input.value = new URL(data.data.invite_url, window.location.href).href;
                result.hidden = false;
                showToast(data.message);
            } catch (error) {
                showError(error);
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    }

    document.addEventListener('click', async (event) => {
        const target = event.target.closest('[data-invitation-action], [data-resolve-invitation], [data-cancel-invitation], [data-revoke-invite-link], [data-remove-member], [data-copy-invite-link], [data-join-link]');
        if (!target) return;

        if (target.hasAttribute('data-copy-invite-link')) {
            const input = document.getElementById('invite-link-url');
            try {
                await navigator.clipboard.writeText(input.value);
                showToast('Link undangan disalin.');
            } catch (error) {
                input.select();
                document.execCommand('copy');
                showToast('Link undangan disalin.');
            }
            return;
        }

        if (target.hasAttribute('data-remove-member') && !window.confirm('Keluarkan anggota ini dari workspace?')) return;
        if (target.hasAttribute('data-revoke-invite-link') && !window.confirm('Nonaktifkan link undangan ini?')) return;

        let payload;
        if (target.hasAttribute('data-invitation-action')) {
            payload = {action: 'respond_invitation', id: target.dataset.id, decision: target.dataset.invitationAction};
        } else if (target.hasAttribute('data-resolve-invitation')) {
            payload = {action: 'resolve_invitation', id: target.dataset.id, decision: target.dataset.resolveInvitation};
        } else if (target.hasAttribute('data-cancel-invitation')) {
            payload = {action: 'cancel_invitation', id: target.dataset.id};
        } else if (target.hasAttribute('data-revoke-invite-link')) {
            payload = {action: 'revoke_link', id: target.dataset.id};
        } else if (target.hasAttribute('data-remove-member')) {
            payload = {action: 'remove', id: target.dataset.id};
        } else if (target.hasAttribute('data-join-link')) {
            payload = {action: 'join_link', token: target.dataset.token};
        }
        if (!payload) return;

        target.disabled = true;
        try {
            const data = await post(payload);
            showToast(data.message);
            if ((payload.action === 'join_link' || payload.action === 'respond_invitation')
                && data.data.status === 'accepted' && data.data.workspace_slug) {
                window.location.href = `index.php?ws=${encodeURIComponent(data.data.workspace_slug)}`;
            } else {
                window.location.reload();
            }
        } catch (error) {
            target.disabled = false;
            showError(error);
        }
    });

    document.querySelectorAll('[data-update-member-role]').forEach((select) => {
        select.addEventListener('change', async () => {
            const previousRole = select.dataset.previousRole || select.value;
            select.disabled = true;
            try {
                const data = await post({action: 'update_role', id: select.dataset.id, role: select.value});
                select.dataset.previousRole = select.value;
                showToast(data.message);
            } catch (error) {
                select.value = previousRole;
                showError(error);
            } finally {
                select.disabled = false;
            }
        });
    });
}

/**
 * Global Keyboard Shortcuts
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Press Escape to close active modal
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-backdrop.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
            const openDropdowns = document.querySelectorAll('.dropdown-menu:not(.hidden)');
            openDropdowns.forEach(menu => menu.classList.add('hidden'));
        }

        // Press Ctrl+K or / to focus search
        if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName))) {
            e.preventDefault();
            const searchInput = document.getElementById('global-search') || document.getElementById('doc-search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });
}
