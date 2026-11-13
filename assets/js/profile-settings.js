document.addEventListener('DOMContentLoaded', () => {
    bindImagePreview('account');
    bindImagePreview('workspace');
    bindProfileForm('form-account-profile', 'api/account.php', true);
    bindProfileForm('form-workspace-profile', 'api/workspaces.php', true);
    bindProfileForm('form-change-password', 'api/account.php', false);
});

function bindImagePreview(kind) {
    const input = document.querySelector(`[data-image-input="${kind}"]`);
    const preview = document.querySelector(`[data-image-preview="${kind}"]`);
    const fallback = document.querySelector(`[data-image-fallback="${kind}"]`);
    const trigger = document.querySelector(`[data-trigger-image="${kind}"]`);
    const removeInput = document.querySelector(`[name="remove_${kind === 'account' ? 'avatar' : 'logo'}"]`);
    const removeButton = document.querySelector(`[data-remove-image="${kind}"]`);
    if (!input || !preview || !fallback || !removeInput) return;

    if (trigger) trigger.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
        fallback.hidden = true;
        removeInput.value = '0';
        if (removeButton) removeButton.hidden = false;
    });

    if (removeButton) {
        removeButton.addEventListener('click', () => {
            input.value = '';
            preview.removeAttribute('src');
            preview.hidden = true;
            fallback.hidden = false;
            removeInput.value = '1';
            removeButton.hidden = true;
        });
    }
}

function bindProfileForm(formId, endpoint, reloadOnSuccess) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: new FormData(form),
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                window.showToast(data.message || 'Perubahan tidak dapat disimpan.', 'error');
                return;
            }

            window.showToast(data.message, 'success');
            if (reloadOnSuccess) {
                window.setTimeout(() => window.location.reload(), 350);
            } else {
                form.reset();
            }
        } catch (error) {
            window.showToast('Terjadi kesalahan jaringan. Coba lagi.', 'error');
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
}
