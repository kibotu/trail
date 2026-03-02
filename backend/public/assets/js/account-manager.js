/**
 * Account Manager
 * 
 * Handles account deletion and data export functionality.
 */

class AccountManager {
    constructor({ apiBase = '/api' } = {}) {
        this.apiBase = apiBase;
        this.nickname = null;
        this.init();
    }

    init() {
        const exportBtn = document.getElementById('exportDataBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportData());
        }

        const deleteBtn = document.getElementById('deleteAccountBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => this.showDeleteModal());
        }
    }

    setNickname(nickname) {
        this.nickname = nickname;
    }

    async exportData() {
        const btn = document.getElementById('exportDataBtn');
        const icon = btn?.querySelector('i');
        const label = btn?.querySelector('span');
        const originalIconClass = icon?.className;

        if (btn) {
            btn.disabled = true;
            btn.classList.add('btn-loading');
            if (icon) icon.className = 'fa-solid fa-spinner fa-spin';
            if (label) label.textContent = 'Preparing export…';
        }

        try {
            const response = await fetch(`${this.apiBase}/profile/export`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to export data');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);

            const disposition = response.headers.get('Content-Disposition');
            let filename = 'trail-data-export.html';
            if (disposition) {
                const match = disposition.match(/filename="(.+?)"/);
                if (match) filename = match[1];
            }

            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            if (typeof showSnackbar === 'function') {
                showSnackbar('Data export downloaded successfully', 'success');
            }
        } catch (err) {
            console.error('Export error:', err);
            if (typeof showSnackbar === 'function') {
                showSnackbar('Failed to export data. Please try again.', 'error');
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('btn-loading');
                if (icon) icon.className = originalIconClass;
                if (label) label.textContent = 'Download My Data';
            }
        }
    }

    showDeleteModal() {
        const existing = document.getElementById('delete-account-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'delete-account-modal';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content delete-modal-content">
                <div class="modal-header delete-modal-header">
                    <h3><i class="fa-solid fa-heart-crack"></i> We're sad to see you go</h3>
                    <button class="modal-close" id="deleteModalClose">&times;</button>
                </div>
                <div class="modal-body">
                    <p>We're sorry to hear you'd like to leave Trail. Before you go, here's what will happen:</p>
                    <ul class="delete-consequences">
                        <li><i class="fa-solid fa-eye-slash"></i><span>Your profile, entries, and comments will be <strong>hidden immediately</strong> from public view.</span></li>
                        <li><i class="fa-solid fa-clock"></i><span>Your account and all data will be <strong>permanently deleted after 14 days</strong>.</span></li>
                        <li><i class="fa-solid fa-rotate-left"></i><span>You can <strong>reverse this decision</strong> within the 14-day window by emailing <a href="mailto:contact@kibotu.net">contact@kibotu.net</a>.</span></li>
                    </ul>
                    <div class="delete-confirm-input">
                        <label for="deleteConfirmNickname">Type your nickname <strong>${this.nickname || 'your_nickname'}</strong> to confirm:</label>
                        <input type="text" id="deleteConfirmNickname" placeholder="Enter your nickname" autocomplete="off" spellcheck="false">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="deleteModalCancel">Cancel</button>
                    <button class="btn btn-danger" id="deleteModalConfirm" disabled>
                        <i class="fa-solid fa-trash"></i> Delete My Account
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        const confirmInput = document.getElementById('deleteConfirmNickname');
        const confirmBtn = document.getElementById('deleteModalConfirm');
        const cancelBtn = document.getElementById('deleteModalCancel');
        const closeBtn = document.getElementById('deleteModalClose');

        confirmInput.addEventListener('input', () => {
            const matches = confirmInput.value.trim() === this.nickname;
            confirmBtn.disabled = !matches;
        });

        confirmBtn.addEventListener('click', () => this.confirmDeletion());
        cancelBtn.addEventListener('click', () => this.closeDeleteModal());
        closeBtn.addEventListener('click', () => this.closeDeleteModal());

        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closeDeleteModal();
        });

        document.addEventListener('keydown', this._escHandler = (e) => {
            if (e.key === 'Escape') this.closeDeleteModal();
        });

        confirmInput.focus();
    }

    closeDeleteModal() {
        const modal = document.getElementById('delete-account-modal');
        if (modal) modal.remove();
        if (this._escHandler) {
            document.removeEventListener('keydown', this._escHandler);
            this._escHandler = null;
        }
    }

    async confirmDeletion() {
        const confirmBtn = document.getElementById('deleteModalConfirm');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        }

        try {
            const response = await fetch(`${this.apiBase}/profile/delete`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to process deletion request');
            }

            this.closeDeleteModal();

            if (typeof showSnackbar === 'function') {
                showSnackbar('Your account deletion request has been received. Redirecting...', 'success', 4000);
            }

            setTimeout(() => {
                window.location.href = '/';
            }, 2000);
        } catch (err) {
            console.error('Deletion error:', err);
            if (typeof showSnackbar === 'function') {
                showSnackbar(err.message || 'Failed to process request. Please try again.', 'error');
            }
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete My Account';
            }
        }
    }
}
