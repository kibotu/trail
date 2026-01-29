/**
 * Shared card template for both landing page and admin page
 * Provides consistent card rendering with optional admin features
 */

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Convert URLs in text to clickable links
function linkifyText(text) {
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
}

// Extract domain from URL
function extractDomain(url) {
    try {
        return url
            .replace(/^https?:\/\//, '')
            .replace(/^www\./, '')
            .split('/')[0];
    } catch (e) {
        return url;
    }
}

// Format timestamp
function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
}

/**
 * Create entry images HTML
 * @param {Object} entry - Entry object with images array
 * @returns {string} HTML string for the images
 */
function createEntryImagesHtml(entry) {
    if (!entry.images || !Array.isArray(entry.images) || entry.images.length === 0) {
        return '';
    }
    
    let imagesHtml = '<div class="entry-images">';
    
    entry.images.forEach(image => {
        imagesHtml += `
            <div class="entry-image-wrapper">
                <a href="${escapeHtml(image.url)}" target="_blank" rel="noopener noreferrer">
                    <img src="${escapeHtml(image.url)}" 
                         alt="Post image" 
                         class="entry-image"
                         loading="lazy"
                         onerror="this.parentElement.parentElement.style.display='none'">
                </a>
            </div>
        `;
    });
    
    imagesHtml += '</div>';
    
    return imagesHtml;
}

/**
 * Create link preview card HTML
 * @param {Object} entry - Entry object with preview data
 * @param {Object} options - Options for rendering
 * @param {boolean} options.showSourceBadge - Whether to show source badge (admin only)
 * @returns {string} HTML string for the preview card
 */
function createLinkPreviewCard(entry, options = {}) {
    if (!entry.preview_url) return '';
    
    const { showSourceBadge = false } = options;
    
    // Check if we have meaningful preview data (not just "Just a moment..." or similar)
    const hasValidTitle = entry.preview_title && 
                         entry.preview_title.length > 3 && 
                         !entry.preview_title.toLowerCase().includes('just a moment') &&
                         !entry.preview_title.toLowerCase().includes('please wait');
    const hasValidDescription = entry.preview_description && 
                               entry.preview_description.length > 10;
    
    // Show card if we have at least title, description, OR image
    if (!hasValidTitle && !hasValidDescription && !entry.preview_image) {
        return '';
    }
    
    // Create source badge for admin view
    let sourceBadge = '';
    if (showSourceBadge && entry.preview_source) {
        const badgeConfig = {
            'iframely': { icon: 'fa-link', label: 'Iframely', color: '#3b82f6' },
            'embed': { icon: 'fa-box', label: 'Fallback', color: '#f59e0b' },
            'medium': { icon: 'fa-file-lines', label: 'Medium', color: '#10b981' }
        };
        const config = badgeConfig[entry.preview_source] || { icon: 'fa-question', label: 'Unknown', color: '#6b7280' };
        sourceBadge = `
            <div class="preview-source-badge" style="background-color: ${config.color};">
                <i class="fa-solid ${config.icon}"></i>
                <span>${config.label}</span>
            </div>
        `;
    }
    
    // Build the preview card HTML
    let previewHtml = showSourceBadge ? '<div class="link-preview-wrapper">' : '';
    
    if (sourceBadge) {
        previewHtml += sourceBadge;
    }
    
    previewHtml += `<a href="${escapeHtml(entry.preview_url)}" class="link-preview-card" target="_blank" rel="noopener noreferrer">`;
    
    // Preview image
    if (entry.preview_image) {
        previewHtml += `
            <img src="${escapeHtml(entry.preview_image)}" 
                 alt="Preview" 
                 class="link-preview-image" 
                 loading="lazy"
                 onerror="this.style.display='none'">
        `;
    }
    
    // Preview content
    previewHtml += '<div class="link-preview-content">';
    
    // Title
    if (hasValidTitle) {
        previewHtml += `<div class="link-preview-title">${escapeHtml(entry.preview_title)}</div>`;
    }
    
    // Description
    if (hasValidDescription) {
        previewHtml += `<div class="link-preview-description">${escapeHtml(entry.preview_description)}</div>`;
    }
    
    // Site name / URL
    const siteName = entry.preview_site_name || extractDomain(entry.preview_url);
    previewHtml += `
        <div class="link-preview-url">
            <i class="fa-solid fa-link"></i>
            <span>${escapeHtml(siteName)}</span>
        </div>
    `;
    
    previewHtml += '</div></a>';
    
    if (showSourceBadge) {
        previewHtml += '</div>';
    }
    
    return previewHtml;
}

/**
 * Create entry card HTML
 * @param {Object} entry - Entry object
 * @param {Object} options - Options for rendering
 * @param {boolean} options.showSourceBadge - Whether to show source badge on preview cards (admin only)
 * @param {boolean} options.canModify - Whether the current user can modify this entry
 * @param {boolean} options.isAdmin - Whether this is being rendered in admin context
 * @param {boolean} options.enablePermalink - Whether to make the card clickable (default: true)
 * @returns {HTMLElement} DOM element for the entry card
 */
function createEntryCard(entry, options = {}) {
    const {
        showSourceBadge = false,
        canModify = false,
        isAdmin = false,
        enablePermalink = true
    } = options;
    
    const card = document.createElement('div');
    card.className = 'entry-card';
    card.dataset.entryId = entry.id;
    
    // Make card clickable if permalink is enabled
    if (enablePermalink) {
        card.style.cursor = 'pointer';
        card.setAttribute('role', 'article');
        card.setAttribute('tabindex', '0');
    }
    
    // Admin pages may need an ID for the card
    if (isAdmin) {
        card.id = `entry-${entry.id}`;
    }
    
    const escapedText = escapeHtml(entry.text);
    const linkedText = linkifyText(escapedText);
    const previewCard = createLinkPreviewCard(entry, { showSourceBadge });
    
    // Determine display name (nickname or fallback)
    let displayName = entry.user_name;
    let userProfileLink = null;
    
    if (entry.user_nickname) {
        displayName = entry.user_nickname;
        userProfileLink = `/@${entry.user_nickname}`;
    } else if (entry.google_id) {
        // Generate a temporary display name hash (will be generated on backend)
        displayName = 'user_' + entry.google_id.substring(0, 8);
    }
    
    // Use the same structure for both pages
    card.innerHTML = `
        <div class="entry-header">
            <img src="${escapeHtml(entry.avatar_url)}" alt="${escapeHtml(displayName)}" class="avatar" loading="lazy">
            <div class="entry-header-content">
                <div class="entry-header-top">
                    <div class="user-info">
                        ${userProfileLink ? 
                            `<a href="${userProfileLink}" class="user-name-link" data-no-navigate>${escapeHtml(displayName)}</a>` :
                            `<span class="user-name">${escapeHtml(displayName)}</span>`
                        }
                        <span style="color: var(--text-muted);">·</span>
                        <span class="timestamp">${formatTimestamp(entry.created_at)}</span>
                    </div>
                    ${canModify ? `
                        <div class="entry-menu">
                            <button class="menu-button" onclick="toggleMenu(event, ${entry.id})" data-no-navigate aria-label="More options">
                                ⋯
                            </button>
                            <div class="menu-dropdown" id="menu-${entry.id}">
                                <button class="menu-item" onclick="editEntry(${entry.id})" data-no-navigate>
                                    <i class="fa-solid fa-pen"></i>
                                    <span>Edit</span>
                                </button>
                                <button class="menu-item delete" onclick="deleteEntry(${entry.id})" data-no-navigate>
                                    <i class="fa-solid fa-trash"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
        <div class="entry-body">
            <div class="entry-content" ${isAdmin ? `id="content-${entry.id}"` : ''}>
                <div class="entry-text">${linkedText}</div>
                ${createEntryImagesHtml(entry)}
                ${previewCard}
            </div>
            <div class="entry-footer">
                <div class="entry-footer-left">
                    ${entry.updated_at && entry.updated_at !== entry.created_at ? 
                        `<div class="timestamp">
                            <i class="fa-solid fa-pen"></i>
                            <span>edited ${formatTimestamp(entry.updated_at)}</span>
                        </div>` : '<div></div>'}
                </div>
                <button class="share-button" onclick="openShareModal(${entry.id})" data-no-navigate aria-label="Share entry">
                    <i class="fa-solid fa-share-nodes"></i>
                </button>
            </div>
        </div>
    `;
    
    // Add click handler for permalink navigation
    if (enablePermalink) {
        card.addEventListener('click', (e) => {
            // Don't navigate if clicking on interactive elements
            if (e.target.closest('[data-no-navigate]') || 
                e.target.closest('a') || 
                e.target.closest('button') ||
                e.target.closest('.link-preview-card') ||
                e.target.closest('.entry-image-wrapper')) {
                return;
            }
            
            window.location.href = `/status/${entry.id}`;
        });
        
        // Add keyboard navigation
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.target.closest('[data-no-navigate]')) {
                window.location.href = `/status/${entry.id}`;
            }
        });
    }
    
    return card;
}

/**
 * Open share modal for an entry
 * @param {number} entryId - Entry ID to share
 */
function openShareModal(entryId) {
    // Remove any existing modal
    closeShareModal();
    
    const entryUrl = `${window.location.origin}/status/${entryId}`;
    const supportsNativeShare = navigator.share !== undefined;
    
    // Create modal backdrop
    const backdrop = document.createElement('div');
    backdrop.id = 'share-modal-backdrop';
    backdrop.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        animation: fadeIn 0.2s ease-out;
    `;
    
    // Create modal container
    const modal = document.createElement('div');
    modal.id = 'share-modal';
    modal.style.cssText = `
        background: var(--bg-secondary, #1e293b);
        border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 1.5rem;
        max-width: 400px;
        width: 100%;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        animation: slideUp 0.3s ease-out;
    `;
    
    modal.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--text-primary, #f8fafc); margin: 0;">Share Entry</h3>
            <button id="share-modal-close" style="background: transparent; border: none; color: var(--text-muted, #94a3b8); font-size: 1.5rem; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.2s;" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button id="copy-link-button" style="
                background: var(--bg-tertiary, #334155);
                border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
                color: var(--text-primary, #f8fafc);
                padding: 0.875rem 1rem;
                border-radius: 8px;
                font-size: 0.9375rem;
                font-weight: 500;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
                transition: all 0.2s;
                min-height: 44px;
            ">
                <i class="fa-solid fa-link"></i>
                <span>Copy Link</span>
            </button>
            
            ${supportsNativeShare ? `
                <button id="native-share-button" style="
                    background: var(--accent, #3b82f6);
                    border: none;
                    color: white;
                    padding: 0.875rem 1rem;
                    border-radius: 8px;
                    font-size: 0.9375rem;
                    font-weight: 500;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.75rem;
                    transition: all 0.2s;
                    min-height: 44px;
                ">
                    <i class="fa-solid fa-share-from-square"></i>
                    <span>Share</span>
                </button>
            ` : ''}
        </div>
    `;
    
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #copy-link-button:hover {
            background: var(--bg-primary, #0f172a);
            border-color: var(--accent, #3b82f6);
        }
        
        #native-share-button:hover {
            background: var(--accent-hover, #2563eb);
        }
        
        #share-modal-close:hover {
            background: var(--bg-tertiary, #334155);
            color: var(--text-primary, #f8fafc);
        }
        
        @media (max-width: 768px) {
            #share-modal-backdrop {
                align-items: flex-end;
            }
            
            #share-modal {
                border-radius: 12px 12px 0 0;
                animation: slideUpMobile 0.3s ease-out;
            }
            
            @keyframes slideUpMobile {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }
        }
    `;
    document.head.appendChild(style);
    
    // Event listeners
    document.getElementById('share-modal-close').addEventListener('click', closeShareModal);
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            closeShareModal();
        }
    });
    
    document.getElementById('copy-link-button').addEventListener('click', () => copyEntryLink(entryId, entryUrl));
    
    if (supportsNativeShare) {
        document.getElementById('native-share-button').addEventListener('click', () => shareEntryNative(entryId, entryUrl));
    }
    
    // Close on ESC key
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            closeShareModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

/**
 * Close share modal
 */
function closeShareModal() {
    const backdrop = document.getElementById('share-modal-backdrop');
    if (backdrop) {
        backdrop.remove();
    }
}

/**
 * Copy entry link to clipboard
 * @param {number} entryId - Entry ID
 * @param {string} entryUrl - Full URL to the entry
 */
async function copyEntryLink(entryId, entryUrl) {
    const button = document.getElementById('copy-link-button');
    const originalContent = button.innerHTML;
    
    try {
        await navigator.clipboard.writeText(entryUrl);
        
        // Show success feedback
        button.innerHTML = `
            <i class="fa-solid fa-check"></i>
            <span>Copied!</span>
        `;
        button.style.background = 'var(--accent, #3b82f6)';
        button.style.color = 'white';
        
        // Reset after 2 seconds
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.style.background = 'var(--bg-tertiary, #334155)';
            button.style.color = 'var(--text-primary, #f8fafc)';
        }, 2000);
        
    } catch (error) {
        console.error('Failed to copy link:', error);
        
        // Fallback: show error
        button.innerHTML = `
            <i class="fa-solid fa-xmark"></i>
            <span>Failed to copy</span>
        `;
        button.style.background = '#ef4444';
        button.style.color = 'white';
        
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.style.background = 'var(--bg-tertiary, #334155)';
            button.style.color = 'var(--text-primary, #f8fafc)';
        }, 2000);
    }
}

/**
 * Share entry using native share API
 * @param {number} entryId - Entry ID
 * @param {string} entryUrl - Full URL to the entry
 */
async function shareEntryNative(entryId, entryUrl) {
    try {
        await navigator.share({
            title: 'Trail Entry',
            text: 'Check out this entry on Trail',
            url: entryUrl
        });
        
        // Close modal after successful share
        closeShareModal();
        
    } catch (error) {
        // User cancelled or share failed
        if (error.name !== 'AbortError') {
            console.error('Failed to share:', error);
        }
    }
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        escapeHtml,
        linkifyText,
        extractDomain,
        formatTimestamp,
        createEntryImagesHtml,
        createLinkPreviewCard,
        createEntryCard,
        openShareModal,
        closeShareModal,
        copyEntryLink,
        shareEntryNative
    };
}
