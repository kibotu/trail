/**
 * Simple snackbar notification system
 */

// Create snackbar container if it doesn't exist
function ensureSnackbarContainer() {
    let container = document.getElementById('snackbar-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'snackbar-container';
        container.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }
    return container;
}

/**
 * Show a snackbar notification
 * @param {string} message - The message to display
 * @param {string} type - Type of notification: 'success', 'error', 'info', 'warning'
 * @param {number} duration - Duration in milliseconds (default: 3000)
 */
function showSnackbar(message, type = 'info', duration = 3000) {
    const container = ensureSnackbarContainer();
    
    // Create snackbar element
    const snackbar = document.createElement('div');
    snackbar.className = `snackbar snackbar-${type}`;
    
    // Color schemes for different types
    const colors = {
        success: { bg: '#10b981', icon: 'fa-check-circle' },
        error: { bg: '#ef4444', icon: 'fa-exclamation-circle' },
        warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
        info: { bg: '#3b82f6', icon: 'fa-info-circle' }
    };
    
    const color = colors[type] || colors.info;
    
    snackbar.style.cssText = `
        background-color: ${color.bg};
        color: white;
        padding: 14px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 280px;
        max-width: 500px;
        pointer-events: auto;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    `;
    
    snackbar.innerHTML = `
        <i class="fa-solid ${color.icon}" style="font-size: 18px;"></i>
        <span style="flex: 1;">${escapeHtml(message)}</span>
    `;
    
    container.appendChild(snackbar);
    
    // Trigger animation
    requestAnimationFrame(() => {
        snackbar.style.opacity = '1';
        snackbar.style.transform = 'translateY(0)';
    });
    
    // Auto-hide after duration
    setTimeout(() => {
        snackbar.style.opacity = '0';
        snackbar.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            if (snackbar.parentNode) {
                snackbar.parentNode.removeChild(snackbar);
            }
        }, 300);
    }, duration);
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { showSnackbar };
}
