/**
 * UI Interactions - Common UI behaviors and utilities
 * 
 * Provides shared UI interaction patterns like menu toggles, permission checking,
 * character counters, and message displays.
 */

/**
 * Toggle dropdown menu for entry actions
 * @param {Event} event - Click event
 * @param {number} entryId - Entry ID
 */
function toggleMenu(event, entryId) {
    event.stopPropagation();
    const menu = document.getElementById(`menu-${entryId}`);
    const allMenus = document.querySelectorAll('.menu-dropdown');
    
    // Close all other menus
    allMenus.forEach(m => {
        if (m !== menu) {
            m.classList.remove('active');
        }
    });
    
    // Toggle current menu
    if (menu) {
        menu.classList.toggle('active');
    }
}

/**
 * Setup click-outside-to-close handler for menus
 */
function setupMenuCloseHandler() {
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.entry-menu')) {
            const allMenus = document.querySelectorAll('.menu-dropdown');
            allMenus.forEach(m => m.classList.remove('active'));
        }
    });
}

/**
 * Check if current user can modify an entry
 * @param {Object} entry - Entry object
 * @param {Object} sessionState - Session state with user info
 * @returns {boolean} True if user can modify
 */
function canModifyEntry(entry, sessionState) {
    if (!sessionState.isLoggedIn) return false;
    if (sessionState.isAdmin) return true;
    return entry.user_email === sessionState.userEmail;
}

/**
 * Update character counter for textarea
 * @param {HTMLTextAreaElement} textarea - Textarea element
 * @param {HTMLElement} counter - Counter display element
 * @param {HTMLButtonElement} submitButton - Submit button to enable/disable
 * @param {number} maxLength - Maximum character length
 * @param {Object} options - Additional options
 * @param {boolean} options.allowEmpty - Allow empty text if images present
 * @param {Function} options.hasImages - Function to check if images are present
 */
function updateCharacterCounter(textarea, counter, submitButton, maxLength = 280, options = {}) {
    const { allowEmpty = false, hasImages = () => false } = options;
    
    const length = textarea.value.length;
    const hasImagesPresent = hasImages();
    
    counter.textContent = `${length} / ${maxLength}`;
    
    // Update counter color
    counter.classList.remove('warning', 'error');
    if (length > maxLength - 20) {
        counter.classList.add('error');
    } else if (length > maxLength - 40) {
        counter.classList.add('warning');
    }
    
    // Enable submit if has text OR (images and allowEmpty), but text can't be too long
    if (allowEmpty) {
        submitButton.disabled = (length === 0 && !hasImagesPresent) || length > maxLength;
    } else {
        submitButton.disabled = length === 0 || length > maxLength;
    }
}

/**
 * Show inline message in a container
 * @param {HTMLElement} container - Message container element
 * @param {string} message - Message text (can include HTML)
 * @param {string} type - Message type: 'success' or 'error'
 * @param {number} duration - Duration to show message in ms (0 = permanent)
 */
function showMessage(container, message, type = 'info', duration = 5000) {
    container.innerHTML = message;
    container.className = type === 'success' ? 'post-success' : 'post-error';
    container.style.display = 'block';
    
    if (duration > 0) {
        setTimeout(() => {
            container.style.display = 'none';
        }, duration);
    }
}

/**
 * Setup character counter for a textarea
 * @param {Object} elements - Object containing textarea, counter, and submitButton
 * @param {number} maxLength - Maximum character length
 * @param {Object} options - Additional options
 * @returns {Function} Cleanup function to remove event listener
 */
function setupCharacterCounter(elements, maxLength = 280, options = {}) {
    const { textarea, counter, submitButton } = elements;
    
    const updateHandler = () => {
        updateCharacterCounter(textarea, counter, submitButton, maxLength, options);
    };
    
    textarea.addEventListener('input', updateHandler);
    
    // Initial update
    updateHandler();
    
    // Return cleanup function
    return () => {
        textarea.removeEventListener('input', updateHandler);
    };
}

/**
 * Show error message in a container
 * @param {HTMLElement} container - Error container element
 * @param {string} message - Error message
 */
function showError(container, message) {
    container.innerHTML = `
        <div class="error-message">
            ${escapeHtml(message)}
        </div>
    `;
}

/**
 * Show empty state message
 * @param {HTMLElement} container - Container element
 * @param {Object} options - Empty state options
 * @param {string} options.icon - FontAwesome icon class
 * @param {string} options.title - Title text
 * @param {string} options.message - Message text
 */
function showEmptyState(container, options = {}) {
    const {
        icon = 'fa-file-lines',
        title = 'No entries yet',
        message = 'Be the first to share something!'
    } = options;
    
    container.innerHTML = `
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid ${icon}"></i></div>
            <h2>${escapeHtml(title)}</h2>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

/**
 * Set button loading state
 * @param {HTMLButtonElement} button - Button element
 * @param {boolean} isLoading - Whether button is loading
 * @param {string} loadingText - Text to show when loading
 * @param {string} originalHTML - Original button HTML to restore
 */
function setButtonLoading(button, isLoading, loadingText = 'Loading...', originalHTML = null) {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalHtml = originalHTML || button.innerHTML;
        button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i><span>${loadingText}</span>`;
    } else {
        button.disabled = false;
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }
}

/**
 * Disable form during submission
 * @param {HTMLFormElement} form - Form element
 * @param {boolean} disabled - Whether to disable
 */
function setFormDisabled(form, disabled) {
    const inputs = form.querySelectorAll('input, textarea, button, select');
    inputs.forEach(input => {
        input.disabled = disabled;
    });
}

/**
 * Validate entry text
 * @param {string} text - Entry text
 * @param {Object} options - Validation options
 * @param {number} options.maxLength - Maximum length
 * @param {boolean} options.allowEmpty - Allow empty text
 * @param {boolean} options.hasImages - Whether images are present
 * @returns {Object} Validation result with valid flag and error message
 */
function validateEntryText(text, options = {}) {
    const {
        maxLength = 280,
        allowEmpty = false,
        hasImages = false
    } = options;
    
    const trimmedText = text.trim();
    
    // Check if text is required
    if (!allowEmpty && !trimmedText && !hasImages) {
        return {
            valid: false,
            error: 'Please add text or upload an image'
        };
    }
    
    // Check text length if provided
    if (trimmedText && trimmedText.length > maxLength) {
        return {
            valid: false,
            error: `Text must be ${maxLength} characters or less`
        };
    }
    
    return { valid: true };
}

/**
 * Setup login prompt for unauthenticated users
 * @param {string} message - Message to show
 */
function showLoginPrompt(message = 'Please log in to continue') {
    if (typeof showSnackbar === 'function') {
        showSnackbar(message, 'info');
        setTimeout(() => {
            window.location.href = '/admin/login.php';
        }, 1500);
    } else {
        alert(message);
        window.location.href = '/admin/login.php';
    }
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        toggleMenu,
        setupMenuCloseHandler,
        canModifyEntry,
        updateCharacterCounter,
        setupCharacterCounter,
        showMessage,
        showError,
        showEmptyState,
        setButtonLoading,
        setFormDisabled,
        validateEntryText,
        showLoginPrompt
    };
}
