/**
 * Admin AI Scripts - GitHub Actions workflow trigger and token management
 */

(function () {
    let settingsOpen = false;
    let hasToken = false;

    function timeAgo(unixTimestamp) {
        const seconds = Math.floor(Date.now() / 1000) - unixTimestamp;
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' min ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        const days = Math.floor(hours / 24);
        return days + 'd ago';
    }

    function init() {
        loadGithubSettings();
    }

    async function loadGithubSettings() {
        try {
            const response = await fetch('/api/admin/github/token', { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            hasToken = data.has_token;

            const statusDot = document.getElementById('ai-scripts-status-dot');
            const statusText = document.getElementById('ai-scripts-status-text');
            const triggerBtn = document.getElementById('btn-trigger-ai');
            const repoInput = document.getElementById('github-repo');
            const tokenInput = document.getElementById('github-token');
            const lastRunEl = document.getElementById('ai-scripts-last-run');

            if (data.repo) {
                repoInput.value = data.repo;
            }

            if (lastRunEl) {
                if (data.last_triggered) {
                    lastRunEl.textContent = 'Last run: ' + timeAgo(data.last_triggered);
                    lastRunEl.title = new Date(data.last_triggered * 1000).toLocaleString();
                } else {
                    lastRunEl.textContent = 'Never triggered';
                    lastRunEl.title = '';
                }
            }

            if (hasToken) {
                statusDot.className = 'ai-scripts-status-dot configured';
                statusText.textContent = 'Ready';
                triggerBtn.disabled = false;
                tokenInput.placeholder = data.masked_token || '••••••••';
            } else {
                statusDot.className = 'ai-scripts-status-dot not-configured';
                statusText.textContent = 'Token not set';
                triggerBtn.disabled = true;
            }
        } catch (error) {
            console.error('Error loading GitHub settings:', error);
            const statusDot = document.getElementById('ai-scripts-status-dot');
            const statusText = document.getElementById('ai-scripts-status-text');
            statusDot.className = 'ai-scripts-status-dot error';
            statusText.textContent = 'Error';
        }
    }

    window.toggleGithubSettings = function () {
        settingsOpen = !settingsOpen;
        const panel = document.getElementById('ai-scripts-settings');
        panel.style.display = settingsOpen ? '' : 'none';
    };

    window.toggleTokenVisibility = function () {
        const input = document.getElementById('github-token');
        const icon = document.getElementById('token-eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    };

    window.saveGithubSettings = async function () {
        const repo = document.getElementById('github-repo').value.trim();
        const token = document.getElementById('github-token').value.trim();
        const saveBtn = document.getElementById('btn-save-github');

        const payload = {};
        if (repo) payload.repo = repo;
        if (token) payload.token = token;

        if (Object.keys(payload).length === 0) {
            showSnackbar('Nothing to save', 'warning');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        try {
            const response = await fetch('/api/admin/github/token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (data.success) {
                showSnackbar('GitHub settings saved', 'success');
                document.getElementById('github-token').value = '';
                await loadGithubSettings();
            } else {
                showSnackbar(data.error || 'Failed to save', 'error');
            }
        } catch (error) {
            console.error('Error saving GitHub settings:', error);
            showSnackbar('Failed to save settings', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
        }
    };

    window.clearGithubToken = async function () {
        if (!confirm('Remove the stored GitHub token?')) return;

        try {
            const response = await fetch('/api/admin/github/token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ token: '' }),
            });

            const data = await response.json();
            if (data.success) {
                showSnackbar('GitHub token removed', 'success');
                await loadGithubSettings();
            } else {
                showSnackbar(data.error || 'Failed to remove token', 'error');
            }
        } catch (error) {
            console.error('Error clearing token:', error);
            showSnackbar('Failed to remove token', 'error');
        }
    };

    window.triggerAiScripts = async function () {
        const btn = document.getElementById('btn-trigger-ai');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Triggering...';

        try {
            const response = await fetch('/api/admin/github/trigger', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    workflow: 'ai-scripts.yml',
                    ref: 'main',
                }),
            });

            const data = await response.json();

            if (data.success) {
                showSnackbar(data.message, 'success', 5000);
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Triggered!';
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 3000);
            } else {
                showSnackbar(data.error || 'Failed to trigger workflow', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Error triggering workflow:', error);
            showSnackbar('Failed to trigger workflow: ' + error.message, 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
