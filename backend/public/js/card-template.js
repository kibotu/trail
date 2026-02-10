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

// Convert @mentions to clickable links
function linkifyMentions(text) {
    // Match @username (alphanumeric + underscore + hyphen)
    const mentionRegex = /@(\w+)/g;
    return text.replace(mentionRegex, (match, username) => {
        // Preserve the original casing from the text
        return `<a href="/@${username}" class="mention-link" data-no-navigate>@${username}</a>`;
    });
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

// Format clap count to human-readable format (e.g., 3.9k, 1.2M)
function formatClapCount(count) {
    if (count >= 1000000) {
        return (count / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (count >= 1000) {
        return (count / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    }
    return count.toString();
}

// Format view count to human-readable format (e.g., 3.9k, 1.2M)
function formatViewCount(count) {
    if (count >= 1000000) {
        return (count / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (count >= 1000) {
        return (count / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    }
    return String(count || 0);
}

/**
 * Generate a lightweight browser fingerprint for view deduplication.
 * Combines stable browser properties to differentiate devices behind the same IP.
 * This is NOT for tracking across sites—only for per-session deduplication.
 * 
 * Properties chosen for stability (won't change during normal use):
 * - Screen resolution (monitor, not window)
 * - Color depth
 * - Language preference
 * - CPU cores
 * - Platform/OS
 * - Canvas rendering (font/GPU-based)
 * 
 * Excluded: timezone (changes with DST/travel), window size (user resizes)
 * 
 * @returns {string} A fingerprint string
 */
function generateBrowserFingerprint() {
    const components = [
        screen.width,
        screen.height,
        screen.colorDepth,
        navigator.language,
        navigator.hardwareConcurrency || 0,
        navigator.platform || '',
        // Canvas fingerprint (lightweight) - stable across sessions
        (() => {
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                ctx.textBaseline = 'top';
                ctx.font = '14px Arial';
                ctx.fillText('fp', 2, 2);
                return canvas.toDataURL().slice(-50);
            } catch {
                return '';
            }
        })()
    ];
    return components.join('|');
}

// Cache the fingerprint for the session
let cachedFingerprint = null;
function getBrowserFingerprint() {
    if (cachedFingerprint === null) {
        cachedFingerprint = generateBrowserFingerprint();
    }
    return cachedFingerprint;
}

/**
 * Record a view with fingerprint data
 * @param {string} url The API endpoint URL
 */
function recordViewWithFingerprint(url) {
    fetch(url, { 
        method: 'POST', 
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fingerprint: getBrowserFingerprint() })
    }).catch(() => {}); // Silent — views are best-effort
}

/**
 * Intersection Observer for view tracking
 * Records views when entries/comments scroll into the viewport
 */
const viewTrackingObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        
        const el = entry.target;
        observer.unobserve(el);
        
        const entryId = el.dataset.entryId;
        const hashId = el.dataset.hashId || el.querySelector('[data-hash-id]')?.dataset.hashId;
        
        if (hashId) {
            recordViewWithFingerprint(`/api/entries/${hashId}/views`);
        }
    });
}, { threshold: 0.5 }); // 50% visible = viewed

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
 * @param {boolean} options.isLoggedIn - Whether the current user is logged in
 * @param {number} options.currentUserId - Current user's ID (for checking if they can mute)
 * @returns {HTMLElement} DOM element for the entry card
 */
function createEntryCard(entry, options = {}) {
    const {
        showSourceBadge = false,
        canModify = false,
        isAdmin = false,
        enablePermalink = true,
        isLoggedIn = false,
        currentUserId = null
    } = options;
    
    // Ensure entry has a valid numeric ID
    if (!entry.id) {
        console.error('Entry missing ID:', entry);
        console.error('Entry keys:', Object.keys(entry));
        return document.createElement('div'); // Return empty div if no ID
    }
    
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
    const mentionedText = linkifyMentions(escapedText);
    const linkedText = linkifyText(mentionedText);
    const previewCard = createLinkPreviewCard(entry, { showSourceBadge });
    
    // Get hash ID once for all event handlers
    const hashId = entry.hash_id || entry.id; // Fallback to numeric ID if hash_id not available
    
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
            ${userProfileLink ? 
                `<a href="${userProfileLink}" data-no-navigate>
                    <img src="${escapeHtml(entry.avatar_url)}" alt="${escapeHtml(displayName)}" class="avatar" loading="lazy">
                </a>` :
                `<img src="${escapeHtml(entry.avatar_url)}" alt="${escapeHtml(displayName)}" class="avatar" loading="lazy">`
            }
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
                    ${canModify || isLoggedIn ? `
                        <div class="entry-menu">
                            <button class="menu-button" data-entry-id="${entry.id}" data-action="toggle-menu" data-no-navigate aria-label="More options">
                                ⋯
                            </button>
                            <div class="menu-dropdown" id="menu-${entry.id}">
                                ${canModify ? `
                                    <button class="menu-item" data-entry-id="${entry.id}" data-action="edit" data-no-navigate>
                                        <i class="fa-solid fa-pen"></i>
                                        <span>Edit</span>
                                    </button>
                                    <button class="menu-item delete" data-entry-id="${entry.id}" data-action="delete" data-no-navigate>
                                        <i class="fa-solid fa-trash"></i>
                                        <span>Delete</span>
                                    </button>
                                ` : ''}
                                ${isLoggedIn && currentUserId && currentUserId !== entry.user_id ? `
                                    <button class="menu-item" data-entry-id="${entry.id}" data-user-id="${entry.user_id}" data-action="report" data-no-navigate>
                                        <i class="fa-solid fa-flag"></i>
                                        <span>Report Post</span>
                                    </button>
                                    <button class="menu-item" data-entry-id="${entry.id}" data-user-id="${entry.user_id}" data-action="mute" data-no-navigate>
                                        <i class="fa-solid fa-volume-xmark"></i>
                                        <span>Mute User</span>
                                    </button>
                                ` : ''}
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
                <div class="entry-footer-actions">
                    <div class="entry-footer-left">
                        <span class="view-counter" 
                              data-entry-id="${entry.id}"
                              data-hash-id="${hashId}"
                              aria-label="Views">
                            <i class="fa-regular fa-eye"></i>
                            <span class="view-count">${formatViewCount(entry.view_count || 0)}</span>
                        </span>
                        <button class="comment-button" 
                                ${options.enablePermalink !== false ? '' : 'data-no-navigate'}
                                data-entry-id="${entry.id}"
                                data-hash-id="${hashId}"
                                data-comment-count="${entry.comment_count || 0}"
                                aria-label="Comments">
                            <i class="fa-regular fa-comment"></i>
                            <span class="comment-count">${entry.comment_count || 0}</span>
                        </button>
                        <button class="clap-button ${(entry.user_clap_count || 0) > 0 ? 'clapped' : ''} ${currentUserId && currentUserId === entry.user_id ? 'own-entry' : ''}" 
                                data-no-navigate 
                                data-entry-id="${entry.id}"
                                data-hash-id="${hashId}"
                                data-user-claps="${entry.user_clap_count || 0}"
                                data-total-claps="${entry.clap_count || 0}"
                                data-is-own="${currentUserId && currentUserId === entry.user_id ? 'true' : 'false'}"
                                aria-label="${currentUserId && currentUserId === entry.user_id ? 'Your entry claps' : 'Clap for this entry'}">
                            <i class="fa-${(entry.user_clap_count || 0) > 0 ? 'solid' : 'regular'} fa-heart"></i>
                            <span class="clap-count">${formatClapCount(entry.clap_count || 0)}</span>
                        </button>
                        <button class="share-button" data-no-navigate aria-label="Share entry">
                            <i class="fa-solid fa-share-nodes"></i>
                        </button>
                    </div>
                </div>
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
            
            window.location.href = `/status/${hashId}`;
        });
        
        // Add keyboard navigation
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.target.closest('[data-no-navigate]')) {
                window.location.href = `/status/${hashId}`;
            }
        });
    }
    
    // Add share button click handler
    const shareButton = card.querySelector('.share-button');
    if (shareButton) {
        shareButton.addEventListener('click', (e) => {
            e.stopPropagation();
            openShareModal(hashId, shareButton);
        });
        
        // Store hash_id in dataset for share functionality
        shareButton.dataset.hashId = hashId;
    }
    
    // Add clap button click handler
    const clapButton = card.querySelector('.clap-button');
    if (clapButton) {
        clapButton.addEventListener('click', async (e) => {
            e.stopPropagation();
            
            // Check if this is the user's own entry
            const isOwnEntry = clapButton.dataset.isOwn === 'true';
            if (isOwnEntry) {
                // Just show a subtle feedback for own entries
                clapButton.classList.add('clap-own-entry-shake');
                setTimeout(() => clapButton.classList.remove('clap-own-entry-shake'), 500);
                return;
            }
            
            // Check if user is logged in
            if (!isLoggedIn) {
                // Redirect to login or show login prompt
                if (typeof showLoginPrompt === 'function') {
                    showLoginPrompt();
                } else {
                    alert('Please log in to clap for entries');
                }
                return;
            }
            
            // Get current clap count
            let userClaps = parseInt(clapButton.dataset.userClaps, 10) || 0;
            let totalClaps = parseInt(clapButton.dataset.totalClaps, 10) || 0;
            
            // Check if user has reached the limit
            if (userClaps >= 50) {
                // Show feedback that limit is reached
                clapButton.classList.add('clap-limit-reached');
                setTimeout(() => clapButton.classList.remove('clap-limit-reached'), 500);
                return;
            }
            
            // Increment user claps
            userClaps++;
            totalClaps++;
            
            // Optimistic UI update
            clapButton.dataset.userClaps = userClaps;
            clapButton.dataset.totalClaps = totalClaps;
            clapButton.classList.add('clapped', 'clap-animation');
            
            // Update icon to filled heart
            const icon = clapButton.querySelector('i');
            icon.className = 'fa-solid fa-heart';
            
            // Update count display
            const countSpan = clapButton.querySelector('.clap-count');
            countSpan.textContent = formatClapCount(totalClaps);
            
            // Create particle explosion effect at click location
            createClapParticles(clapButton, e.clientX, e.clientY);
            
            // Remove animation class after animation completes
            setTimeout(() => clapButton.classList.remove('clap-animation'), 300);
            
            // Send request to server
            try {
                const response = await fetch(`/api/entries/${hashId}/claps`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('jwt_token') || ''}`
                    },
                    credentials: 'include',
                    body: JSON.stringify({ count: userClaps })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to add clap');
                }
                
                const data = await response.json();
                
                // Update with server values
                clapButton.dataset.userClaps = data.user_claps;
                clapButton.dataset.totalClaps = data.total_claps;
                countSpan.textContent = formatClapCount(data.total_claps);
                
                // Update button state
                if (data.user_claps >= 50) {
                    clapButton.classList.add('clap-limit-reached');
                    clapButton.setAttribute('aria-label', 'Maximum claps reached (50/50)');
                } else {
                    clapButton.setAttribute('aria-label', `Clap for this entry (${data.user_claps}/50)`);
                }
                
            } catch (error) {
                console.error('Error adding clap:', error);
                
                // Revert optimistic update on error
                userClaps--;
                totalClaps--;
                clapButton.dataset.userClaps = userClaps;
                clapButton.dataset.totalClaps = totalClaps;
                countSpan.textContent = formatClapCount(totalClaps);
                
                if (userClaps === 0) {
                    clapButton.classList.remove('clapped');
                    icon.className = 'fa-regular fa-heart';
                }
                
                // Show error feedback
                alert('Failed to add clap. Please try again.');
            }
        });
        
        // Update aria label based on current state
        const userClaps = parseInt(clapButton.dataset.userClaps, 10) || 0;
        if (userClaps >= 50) {
            clapButton.setAttribute('aria-label', 'Maximum claps reached (50/50)');
        } else if (userClaps > 0) {
            clapButton.setAttribute('aria-label', `Clap for this entry (${userClaps}/50)`);
        }
    }
    
    // Add event listeners for menu buttons
    if (canModify || isLoggedIn) {
        const menuButton = card.querySelector('[data-action="toggle-menu"]');
        const editButton = card.querySelector('[data-action="edit"]');
        const deleteButton = card.querySelector('[data-action="delete"]');
        const reportButton = card.querySelector('[data-action="report"]');
        const muteButton = card.querySelector('[data-action="mute"]');
        
        if (menuButton) {
            menuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const entryId = parseInt(menuButton.dataset.entryId, 10);
                if (typeof toggleMenu === 'function') {
                    toggleMenu(e, entryId);
                } else {
                    console.error('toggleMenu function not found');
                }
            });
        }
        
        if (editButton) {
            editButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const entryId = parseInt(editButton.dataset.entryId, 10);
                if (typeof editEntry === 'function') {
                    editEntry(entryId);
                } else {
                    console.error('editEntry function not found');
                }
            });
        }
        
        if (deleteButton) {
            deleteButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const entryId = parseInt(deleteButton.dataset.entryId, 10);
                if (typeof deleteEntry === 'function') {
                    deleteEntry(entryId);
                } else {
                    console.error('deleteEntry function not found');
                }
            });
        }
        
        if (reportButton) {
            reportButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const entryId = parseInt(reportButton.dataset.entryId, 10);
                if (typeof reportEntry === 'function') {
                    reportEntry(entryId, card);
                } else {
                    console.error('reportEntry function not found');
                }
            });
        }
        
        if (muteButton) {
            muteButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const userId = parseInt(muteButton.dataset.userId, 10);
                if (typeof muteUser === 'function') {
                    muteUser(userId);
                } else {
                    console.error('muteUser function not found');
                }
            });
        }
    }
    
    // Add card to view tracking observer for automatic view recording
    card.dataset.hashId = hashId;
    viewTrackingObserver.observe(card);
    
    return card;
}

/**
 * Open share tooltip for an entry
 * @param {string} hashId - Entry hash ID to share
 * @param {HTMLElement} buttonElement - The share button element that was clicked
 */
function openShareModal(hashId, buttonElement) {
    // Remove any existing tooltip
    closeShareModal();
    
    const entryUrl = `${window.location.origin}/status/${hashId}`;
    const supportsNativeShare = navigator.share !== undefined;
    
    // Create tooltip container
    const tooltip = document.createElement('div');
    tooltip.id = 'share-tooltip';
    tooltip.style.cssText = `
        position: fixed;
        background: var(--bg-secondary, #1e293b);
        border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
        border-radius: 8px;
        padding: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
        z-index: 10001;
        animation: tooltipFadeIn 0.2s ease-out;
        min-width: 140px;
    `;
    
    tooltip.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <button id="copy-link-button" style="
                background: transparent;
                border: none;
                color: var(--text-primary, #f8fafc);
                padding: 0.625rem 0.75rem;
                border-radius: 4px;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.625rem;
                transition: all 0.2s;
                text-align: left;
                white-space: nowrap;
            ">
                <i class="fa-solid fa-link" style="width: 16px;"></i>
                <span>Copy Link</span>
            </button>
            
            ${supportsNativeShare ? `
                <button id="native-share-button" style="
                    background: transparent;
                    border: none;
                    color: var(--text-primary, #f8fafc);
                    padding: 0.625rem 0.75rem;
                    border-radius: 4px;
                    font-size: 0.875rem;
                    font-weight: 500;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 0.625rem;
                    transition: all 0.2s;
                    text-align: left;
                    white-space: nowrap;
                ">
                    <i class="fa-solid fa-share-from-square" style="width: 16px;"></i>
                    <span>Share</span>
                </button>
            ` : ''}
        </div>
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip above the button
    const buttonRect = buttonElement.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    // Calculate position (centered above button with arrow)
    let left = buttonRect.left + (buttonRect.width / 2) - (tooltipRect.width / 2);
    let top = buttonRect.top - tooltipRect.height - 8; // 8px gap
    
    // Adjust if tooltip goes off-screen horizontally
    if (left < 8) {
        left = 8;
    } else if (left + tooltipRect.width > window.innerWidth - 8) {
        left = window.innerWidth - tooltipRect.width - 8;
    }
    
    // If tooltip goes off-screen at top, show below button instead
    if (top < 8) {
        top = buttonRect.bottom + 8;
        tooltip.style.animation = 'tooltipFadeInDown 0.2s ease-out';
    }
    
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
    
    // Add CSS animations if not already added
    if (!document.getElementById('share-tooltip-styles')) {
        const style = document.createElement('style');
        style.id = 'share-tooltip-styles';
        style.textContent = `
            @keyframes tooltipFadeIn {
                from { 
                    opacity: 0;
                    transform: translateY(4px);
                }
                to { 
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes tooltipFadeInDown {
                from { 
                    opacity: 0;
                    transform: translateY(-4px);
                }
                to { 
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            #share-tooltip button:hover {
                background: var(--bg-tertiary, #334155);
            }
            
            #share-tooltip button:active {
                transform: scale(0.98);
            }
        `;
        document.head.appendChild(style);
    }
    
    // Event listeners
    document.getElementById('copy-link-button').addEventListener('click', () => copyEntryLink(hashId, entryUrl, buttonElement));
    
    if (supportsNativeShare) {
        document.getElementById('native-share-button').addEventListener('click', () => shareEntryNative(hashId, entryUrl));
    }
    
    // Close on click outside
    setTimeout(() => {
        const closeHandler = (e) => {
            if (!tooltip.contains(e.target) && e.target !== buttonElement) {
                closeShareModal();
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);
    
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
 * Close share tooltip
 */
function closeShareModal() {
    const tooltip = document.getElementById('share-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

/**
 * Copy entry link to clipboard
 * @param {string} hashId - Entry hash ID
 * @param {string} entryUrl - Full URL to the entry
 * @param {HTMLElement} shareButton - The share button element for positioning feedback
 */
async function copyEntryLink(hashId, entryUrl, shareButton) {
    const button = document.getElementById('copy-link-button');
    const originalContent = button.innerHTML;
    
    try {
        await navigator.clipboard.writeText(entryUrl);
        
        // Show success feedback
        button.innerHTML = `
            <i class="fa-solid fa-check" style="width: 16px;"></i>
            <span>Copied!</span>
        `;
        button.style.color = 'var(--accent, #3b82f6)';
        
        // Close tooltip after brief delay
        setTimeout(() => {
            closeShareModal();
        }, 1000);
        
    } catch (error) {
        console.error('Failed to copy link:', error);
        
        // Show error feedback
        button.innerHTML = `
            <i class="fa-solid fa-xmark" style="width: 16px;"></i>
            <span>Failed</span>
        `;
        button.style.color = '#ef4444';
        
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.style.color = 'var(--text-primary, #f8fafc)';
        }, 2000);
    }
}

/**
 * Share entry using native share API
 * @param {string} hashId - Entry hash ID
 * @param {string} entryUrl - Full URL to the entry
 */
async function shareEntryNative(hashId, entryUrl) {
    try {
        await navigator.share({
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

/**
 * Report an entry
 * @param {number} entryId - Entry ID to report
 * @param {HTMLElement} cardElement - The card element to hide
 */
async function reportEntry(entryId, cardElement) {
    try {
        const response = await fetch(`/api/entries/${entryId}/report`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });

        const data = await response.json();

        if (response.ok) {
            // Hide the card immediately
            if (cardElement) {
                cardElement.style.transition = 'opacity 0.3s, transform 0.3s';
                cardElement.style.opacity = '0';
                cardElement.style.transform = 'scale(0.95)';
                
                setTimeout(() => {
                    cardElement.style.display = 'none';
                }, 300);
            }

            // Show success message
            if (typeof showSnackbar === 'function') {
                showSnackbar('Post reported. Thank you for keeping our community safe.', 'success');
            }
        } else if (data.already_reported) {
            if (typeof showSnackbar === 'function') {
                showSnackbar('You have already reported this post', 'info');
            }
        } else {
            throw new Error(data.error || 'Failed to report entry');
        }
    } catch (error) {
        console.error('Error reporting entry:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to report post. Please try again.', 'error');
        }
    }
}

/**
 * Mute a user
 * @param {number} userId - User ID to mute
 */
async function muteUser(userId) {
    try {
        const response = await fetch(`/api/users/${userId}/mute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });

        const data = await response.json();

        if (response.ok) {
            // Reload the page to hide all posts from this user
            if (typeof showSnackbar === 'function') {
                showSnackbar('User muted. Refreshing feed...', 'success', 1500);
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.error || 'Failed to mute user');
        }
    } catch (error) {
        console.error('Error muting user:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to mute user. Please try again.', 'error');
        }
    }
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        escapeHtml,
        linkifyText,
        linkifyMentions,
        extractDomain,
        formatTimestamp,
        formatClapCount,
        formatViewCount,
        createEntryImagesHtml,
        createLinkPreviewCard,
        createEntryCard,
        openShareModal,
        closeShareModal,
        copyEntryLink,
        shareEntryNative,
        reportEntry,
        muteUser,
        viewTrackingObserver
    };
}
