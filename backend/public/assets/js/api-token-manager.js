/**
 * API Token Manager - Manage user API tokens
 * 
 * Handles viewing, hiding, copying, and regenerating API tokens
 * for authenticated API access.
 */

class ApiTokenManager {
    constructor() {
        this.tokenValue = null;
        this.createdAt = null;
        this.isVisible = false;
        this.apiBase = '/api';
    }

    /**
     * Initialize the API token manager
     */
    async init() {
        try {
            await this.loadToken();
            this.attachEventListeners();
        } catch (error) {
            console.error('Failed to initialize API token manager:', error);
            this.showError('Failed to load API token');
        }
    }

    /**
     * Load the current API token from the server
     */
    async loadToken() {
        try {
            const response = await fetch(`${this.apiBase}/token`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load API token');
            }

            const data = await response.json();
            this.tokenValue = data.api_token;
            this.createdAt = data.created_at;
            this.updateUI(data);
        } catch (error) {
            console.error('Error loading API token:', error);
            throw error;
        }
    }

    /**
     * Update the UI with token data
     */
    updateUI(data) {
        const valueEl = document.getElementById('api-token-value');
        const createdEl = document.getElementById('api-token-created');

        if (valueEl) {
            valueEl.textContent = this.isVisible 
                ? this.tokenValue 
                : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••';
        }

        if (createdEl && data.created_at) {
            const createdDate = new Date(data.created_at);
            createdEl.textContent = createdDate.toLocaleString();
        }
    }

    /**
     * Toggle token visibility
     */
    toggleVisibility() {
        this.isVisible = !this.isVisible;
        const icon = document.querySelector('#toggle-token-btn i');
        
        if (icon) {
            icon.className = this.isVisible 
                ? 'fa-solid fa-eye-slash' 
                : 'fa-solid fa-eye';
        }

        // Only update the token value display; use the stored original date
        const valueEl = document.getElementById('api-token-value');
        if (valueEl) {
            valueEl.textContent = this.isVisible 
                ? this.tokenValue 
                : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••';
        }
    }

    /**
     * Copy token to clipboard
     */
    async copyToClipboard() {
        try {
            if (!this.tokenValue) {
                throw new Error('No token available to copy');
            }

            await navigator.clipboard.writeText(this.tokenValue);
            
            // Show checkmark animation on copy button
            const copyBtn = document.getElementById('copy-token-btn');
            if (copyBtn) {
                const icon = copyBtn.querySelector('i');
                const originalClass = icon.className;
                
                // Change to checkmark
                icon.className = 'fa-solid fa-check';
                copyBtn.classList.add('success');
                
                // Revert after 2 seconds
                setTimeout(() => {
                    icon.className = originalClass;
                    copyBtn.classList.remove('success');
                }, 2000);
            }
            
            // Show success message using the global snackbar function
            if (typeof showSnackbar === 'function') {
                showSnackbar('API token copied to clipboard', 'success');
            } else {
                console.log('API token copied to clipboard');
            }
        } catch (error) {
            console.error('Error copying to clipboard:', error);
            this.showError('Failed to copy token to clipboard');
        }
    }

    /**
     * Regenerate the API token
     */
    async regenerateToken() {
        // Confirm with user
        const confirmed = confirm(
            'Regenerate API token?\n\n' +
            'This will invalidate your current token and any applications using it will need to be updated.\n\n' +
            'Are you sure you want to continue?'
        );

        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}/token/regenerate`, {
                method: 'POST',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to regenerate token');
            }

            const data = await response.json();
            this.tokenValue = data.api_token;
            this.createdAt = data.created_at;
            
            // Automatically show the new token
            this.isVisible = true;
            const icon = document.querySelector('#toggle-token-btn i');
            if (icon) {
                icon.className = 'fa-solid fa-eye-slash';
            }
            
            this.updateUI(data);

            // Show success message
            if (typeof showSnackbar === 'function') {
                showSnackbar('API token regenerated successfully', 'success');
            }
        } catch (error) {
            console.error('Error regenerating token:', error);
            this.showError('Failed to regenerate API token');
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        if (typeof showSnackbar === 'function') {
            showSnackbar(message, 'error');
        } else {
            alert(message);
        }
    }

    /**
     * Attach event listeners to buttons
     */
    attachEventListeners() {
        const toggleBtn = document.getElementById('toggle-token-btn');
        const copyBtn = document.getElementById('copy-token-btn');
        const regenerateBtn = document.getElementById('regenerate-token-btn');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleVisibility());
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', () => this.copyToClipboard());
        }

        if (regenerateBtn) {
            regenerateBtn.addEventListener('click', () => this.regenerateToken());
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.apiTokenManager = new ApiTokenManager();
        window.apiTokenManager.init();
    });
} else {
    window.apiTokenManager = new ApiTokenManager();
    window.apiTokenManager.init();
}
