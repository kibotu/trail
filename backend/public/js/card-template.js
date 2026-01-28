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
            'iframely': { emoji: '🔗', label: 'Iframely', color: '#3b82f6' },
            'embed': { emoji: '📦', label: 'Fallback', color: '#f59e0b' },
            'medium': { emoji: '📝', label: 'Medium', color: '#10b981' }
        };
        const config = badgeConfig[entry.preview_source] || { emoji: '❓', label: 'Unknown', color: '#6b7280' };
        sourceBadge = `
            <div class="preview-source-badge" style="background-color: ${config.color};">
                <span>${config.emoji}</span>
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
            <span>🔗</span>
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
 * @returns {HTMLElement} DOM element for the entry card
 */
function createEntryCard(entry, options = {}) {
    const {
        showSourceBadge = false,
        canModify = false,
        isAdmin = false
    } = options;
    
    const card = document.createElement('div');
    card.className = 'entry-card';
    card.dataset.entryId = entry.id;
    
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
                            `<a href="${userProfileLink}" class="user-name-link">${escapeHtml(displayName)}</a>` :
                            `<span class="user-name">${escapeHtml(displayName)}</span>`
                        }
                        <span style="color: var(--text-muted);">·</span>
                        <span class="timestamp">${formatTimestamp(entry.created_at)}</span>
                    </div>
                    ${canModify ? `
                        <div class="entry-menu">
                            <button class="menu-button" onclick="toggleMenu(event, ${entry.id})" aria-label="More options">
                                ⋯
                            </button>
                            <div class="menu-dropdown" id="menu-${entry.id}">
                                <button class="menu-item" onclick="editEntry(${entry.id})">
                                    <span>✏️</span>
                                    <span>Edit</span>
                                </button>
                                <button class="menu-item delete" onclick="deleteEntry(${entry.id})">
                                    <span>🗑️</span>
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
                ${previewCard}
            </div>
            <div class="entry-footer">
                ${entry.updated_at && entry.updated_at !== entry.created_at ? 
                    `<div class="timestamp">
                        <span>✏️</span>
                        <span>edited ${formatTimestamp(entry.updated_at)}</span>
                    </div>` : ''}
            </div>
        </div>
    `;
    
    return card;
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        escapeHtml,
        linkifyText,
        extractDomain,
        formatTimestamp,
        createLinkPreviewCard,
        createEntryCard
    };
}
