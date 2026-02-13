/**
 * Admin Broken Links JavaScript
 * Handles broken link checking, viewing, dismissing, deleting, and filtering
 * 
 * Authentication: All requests use session cookies (credentials: 'same-origin')
 */

let brokenLinksPage = 0;
let brokenLinksLoading = false;
let brokenLinksHasMore = true;
let currentErrorTypeFilter = '';
let hideDismissed = true;
const brokenLinksPageSize = 20;

// Bulk selection tracking
let selectedBrokenLinkIds = new Set();
let allBrokenLinkIds = [];

/**
 * Initialize broken links functionality
 */
function initBrokenLinks() {
    // Error type filter change handler
    const errorTypeFilter = document.getElementById('error-type-filter');
    if (errorTypeFilter) {
        errorTypeFilter.addEventListener('change', (e) => {
            currentErrorTypeFilter = e.target.value;
            resetAndLoadBrokenLinks();
        });
    }

    // Hide dismissed checkbox handler
    const hideDismissedCheckbox = document.getElementById('hide-dismissed');
    if (hideDismissedCheckbox) {
        hideDismissedCheckbox.addEventListener('change', (e) => {
            hideDismissed = e.target.checked;
            resetAndLoadBrokenLinks();
        });
    }

    // Infinite scroll for broken links view
    window.addEventListener('scroll', () => {
        const scrollPosition = window.innerHeight + window.scrollY;
        const threshold = document.documentElement.scrollHeight - 500;
        
        if (scrollPosition >= threshold) {
            if (currentView === 'broken-links' && !brokenLinksLoading && brokenLinksHasMore) {
                loadBrokenLinks();
            }
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBrokenLinks);
} else {
    initBrokenLinks();
}

/**
 * Reset and reload broken links (used when filter changes)
 */
function resetAndLoadBrokenLinks() {
    brokenLinksPage = 0;
    brokenLinksHasMore = true;
    selectedBrokenLinkIds.clear();
    allBrokenLinkIds = [];
    document.getElementById('broken-links-container').innerHTML = '';
    updateBrokenLinksBulkActionsUI();
    loadBrokenLinks();
}

/**
 * Load broken links from API
 */
async function loadBrokenLinks() {
    if (brokenLinksLoading || !brokenLinksHasMore) return;
    
    brokenLinksLoading = true;
    document.getElementById('loading').style.display = 'block';
    
    try {
        const params = new URLSearchParams({
            page: brokenLinksPage.toString(),
            limit: brokenLinksPageSize.toString(),
            include_dismissed: (!hideDismissed).toString()
        });
        
        if (currentErrorTypeFilter) {
            params.append('error_type', currentErrorTypeFilter);
        }
        
        const response = await fetch(`/api/admin/broken-links?${params}`, {
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to load broken links');
        }
        
        const data = await response.json();
        const container = document.getElementById('broken-links-container');
        const emptyState = document.getElementById('empty-broken-links-state');
        
        if (data.broken_links.length === 0 && brokenLinksPage === 0) {
            container.style.display = 'none';
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
            container.style.display = 'grid';
            
            data.broken_links.forEach(link => {
                const card = createBrokenLinkCard(link);
                container.appendChild(card);
            });
            
            brokenLinksPage++;
            brokenLinksHasMore = data.broken_links.length === brokenLinksPageSize;
        }
    } catch (error) {
        console.error('Error loading broken links:', error);
        showSnackbar('Failed to load broken links', 'error');
    } finally {
        brokenLinksLoading = false;
        document.getElementById('loading').style.display = 'none';
    }
}

/**
 * Create broken link card element
 */
function createBrokenLinkCard(link) {
    const card = document.createElement('div');
    card.className = 'entry-card broken-link-card';
    card.dataset.linkId = link.id;
    
    // Determine error badge color
    let badgeClass = 'error-badge';
    if (link.error_type === 'timeout') badgeClass += ' warning';
    else if (link.error_type === 'dns_error') badgeClass += ' danger';
    else if (link.error_type === 'ssl_error') badgeClass += ' danger';
    
    // Format last healthy date
    const lastHealthy = link.last_healthy_at 
        ? formatRelativeTime(new Date(link.last_healthy_at))
        : 'Never';
    
    // Format last checked date
    const lastChecked = link.last_checked_at
        ? formatRelativeTime(new Date(link.last_checked_at))
        : 'Not checked';

    // Check if this link is selected
    const isSelected = selectedBrokenLinkIds.has(link.id);
    
    card.innerHTML = `
        <div class="entry-header">
            <div class="entry-meta" style="display: flex; align-items: center; gap: 0.75rem;">
                <label class="bulk-checkbox" title="Select for bulk action">
                    <input type="checkbox" 
                           class="broken-link-checkbox" 
                           data-link-id="${link.id}"
                           ${isSelected ? 'checked' : ''}
                           onchange="toggleBrokenLinkSelection(${link.id}, this.checked)">
                </label>
                <span class="${badgeClass}">
                    ${link.http_status_code > 0 ? link.http_status_code : link.error_type.toUpperCase()}
                </span>
                <span class="entry-date">
                    Last checked: ${lastChecked}
                </span>
            </div>
            <div class="entry-actions">
                ${link.is_dismissed 
                    ? `<button onclick="undismissBrokenLink(${link.id})" class="button small secondary">
                        <i class="fa-solid fa-eye"></i> Undismiss
                       </button>`
                    : `<button onclick="dismissBrokenLink(${link.id})" class="button small secondary">
                        <i class="fa-solid fa-eye-slash"></i> Dismiss
                       </button>`
                }
                <button onclick="deleteBrokenLinkEntries(${link.id}, ${link.affected_entries_count})" class="button small danger" title="Delete all ${link.affected_entries_count} entries using this broken URL">
                    <i class="fa-solid fa-trash"></i> Delete ${link.affected_entries_count} ${link.affected_entries_count === 1 ? 'Entry' : 'Entries'}
                </button>
            </div>
        </div>
        
        <div class="broken-link-content">
            <div class="broken-link-url">
                <a href="${escapeHtml(link.url)}" target="_blank" rel="noopener noreferrer">
                    ${escapeHtml(link.url)}
                    <i class="fa-solid fa-external-link-alt" style="font-size: 0.75rem; margin-left: 0.25rem;"></i>
                </a>
            </div>
            
            ${link.title ? `<div class="broken-link-title">${escapeHtml(link.title)}</div>` : ''}
            
            <div class="broken-link-meta">
                <span><i class="fa-solid fa-heart-crack"></i> Last healthy: ${lastHealthy}</span>
                <span><i class="fa-solid fa-exclamation-triangle"></i> ${link.consecutive_failures} consecutive failures</span>
                <span><i class="fa-solid fa-file-lines"></i> ${link.affected_entries_count} ${link.affected_entries_count === 1 ? 'entry' : 'entries'} affected</span>
            </div>
            
            ${link.error_message ? `<div class="broken-link-error">${escapeHtml(link.error_message)}</div>` : ''}
        </div>
    `;
    
    return card;
}

/**
 * Check broken links (run link health checker)
 */
async function checkBrokenLinks() {
    const button = document.querySelector('.btn-check-links');
    if (!button) return;
    
    const originalText = button.innerHTML;
    button.disabled = true;
    
    // Get config from backend (batch size and rate limit)
    const config = window.LINK_HEALTH_CONFIG || { batch_size: 50, rate_limit_ms: 500 };
    const batchSize = config.batch_size;
    
    // Calculate total estimated time (minimum 100ms per URL for smooth animation)
    const msPerUrl = Math.max(config.rate_limit_ms + 100, 200); // Add overhead for request time
    const totalEstimatedMs = batchSize * msPerUrl;
    
    let currentProgress = 0;
    let progressInterval;
    
    // Update progress display
    const updateProgress = (current) => {
        button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Checking ${current}/${batchSize}...`;
    };
    
    // Start progress simulation
    let estimatedCurrent = 1;
    progressInterval = setInterval(() => {
        currentProgress += 100; // Update every 100ms
        const progressRatio = currentProgress / totalEstimatedMs;
        const newCurrent = Math.max(1, Math.min(batchSize, Math.ceil(progressRatio * batchSize)));
        
        // Only update if changed
        if (newCurrent !== estimatedCurrent) {
            estimatedCurrent = newCurrent;
            updateProgress(estimatedCurrent);
        }
    }, 100);
    
    updateProgress(1);
    
    try {
        const response = await fetch('/api/admin/broken-links/check', {
            method: 'POST',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to check links');
        }
        
        const data = await response.json();
        
        // Clear progress interval
        clearInterval(progressInterval);
        
        // Show final actual count
        button.innerHTML = `<i class="fa-solid fa-check"></i> Checked ${data.checked}/${batchSize}`;
        
        showSnackbar(data.message || `Checked ${data.checked} links`, 'success');
        
        // Reload the page to update stats
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        
    } catch (error) {
        console.error('Error checking links:', error);
        clearInterval(progressInterval);
        showSnackbar('Failed to check links', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

/**
 * Recheck only broken/failing links
 */
async function recheckBrokenLinks() {
    const button = document.querySelector('.btn-recheck-broken');
    if (!button) return;
    
    const originalText = button.innerHTML;
    button.disabled = true;
    
    // Get config from backend (batch size and rate limit)
    const config = window.LINK_HEALTH_CONFIG || { batch_size: 50, rate_limit_ms: 500 };
    const batchSize = config.batch_size;
    
    // Calculate total estimated time (minimum 100ms per URL for smooth animation)
    const msPerUrl = Math.max(config.rate_limit_ms + 100, 200); // Add overhead for request time
    const totalEstimatedMs = batchSize * msPerUrl;
    
    let currentProgress = 0;
    let progressInterval;
    
    // Update progress display
    const updateProgress = (current) => {
        button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Rechecking ${current}/${batchSize}...`;
    };
    
    // Start progress simulation
    let estimatedCurrent = 1;
    progressInterval = setInterval(() => {
        currentProgress += 100; // Update every 100ms
        const progressRatio = currentProgress / totalEstimatedMs;
        const newCurrent = Math.max(1, Math.min(batchSize, Math.ceil(progressRatio * batchSize)));
        
        // Only update if changed
        if (newCurrent !== estimatedCurrent) {
            estimatedCurrent = newCurrent;
            updateProgress(estimatedCurrent);
        }
    }, 100);
    
    updateProgress(1);
    
    try {
        const response = await fetch('/api/admin/broken-links/recheck', {
            method: 'POST',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to recheck links');
        }
        
        const data = await response.json();
        
        // Clear progress interval
        clearInterval(progressInterval);
        
        // Show final actual count
        button.innerHTML = `<i class="fa-solid fa-check"></i> Rechecked ${data.checked}`;
        
        showSnackbar(data.message || `Rechecked ${data.checked} links`, 'success');
        
        // Reload the page to update stats
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        
    } catch (error) {
        console.error('Error rechecking links:', error);
        clearInterval(progressInterval);
        showSnackbar('Failed to recheck links', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

/**
 * Dismiss a broken link
 */
async function dismissBrokenLink(id) {
    try {
        const response = await fetch(`/api/admin/broken-links/${id}/dismiss`, {
            method: 'POST',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to dismiss link');
        }
        
        showSnackbar('Link dismissed', 'success');
        
        // Remove or update the card
        if (hideDismissed) {
            const card = document.querySelector(`[data-link-id="${id}"]`);
            if (card) {
                card.remove();
            }
        } else {
            // Reload to show updated state
            resetAndLoadBrokenLinks();
        }
        
    } catch (error) {
        console.error('Error dismissing link:', error);
        showSnackbar('Failed to dismiss link', 'error');
    }
}

/**
 * Undismiss a broken link
 */
async function undismissBrokenLink(id) {
    try {
        const response = await fetch(`/api/admin/broken-links/${id}/undismiss`, {
            method: 'POST',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to undismiss link');
        }
        
        showSnackbar('Link undismissed', 'success');
        
        // Reload to show updated state
        resetAndLoadBrokenLinks();
        
    } catch (error) {
        console.error('Error undismissing link:', error);
        showSnackbar('Failed to undismiss link', 'error');
    }
}

/**
 * Format relative time (e.g., "2 days ago")
 */
function formatRelativeTime(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSecs < 60) return 'just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    if (diffDays < 30) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
    
    const diffMonths = Math.floor(diffDays / 30);
    if (diffMonths < 12) return `${diffMonths} month${diffMonths !== 1 ? 's' : ''} ago`;
    
    const diffYears = Math.floor(diffMonths / 12);
    return `${diffYears} year${diffYears !== 1 ? 's' : ''} ago`;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Delete entries associated with a broken link
 */
async function deleteBrokenLinkEntries(id, entryCount) {
    const countText = entryCount === 1 ? '1 entry' : `${entryCount} entries`;
    if (!confirm(`Delete ${countText} that use this broken URL?\n\nThis will permanently delete the entries and all associated comments, claps, and views. This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/broken-links/${id}/entries`, {
            method: 'DELETE',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to delete entries');
        }
        
        const data = await response.json();
        showSnackbar(`Deleted ${data.deleted_entries} ${data.deleted_entries === 1 ? 'entry' : 'entries'}`, 'success');
        
        // Remove the card from DOM
        const card = document.querySelector(`[data-link-id="${id}"]`);
        if (card) {
            card.remove();
        }
        
        // Remove from selection if selected
        selectedBrokenLinkIds.delete(id);
        updateBrokenLinksBulkActionsUI();
        
    } catch (error) {
        console.error('Error deleting entries:', error);
        showSnackbar('Failed to delete entries', 'error');
    }
}

/**
 * Toggle selection of a broken link for bulk operations
 */
function toggleBrokenLinkSelection(id, isSelected) {
    if (isSelected) {
        selectedBrokenLinkIds.add(id);
    } else {
        selectedBrokenLinkIds.delete(id);
    }
    updateBrokenLinksBulkActionsUI();
}

/**
 * Select all visible broken links
 */
function selectAllBrokenLinks() {
    const checkboxes = document.querySelectorAll('.broken-link-checkbox');
    checkboxes.forEach(cb => {
        const id = parseInt(cb.dataset.linkId);
        cb.checked = true;
        selectedBrokenLinkIds.add(id);
    });
    updateBrokenLinksBulkActionsUI();
}

/**
 * Deselect all broken links
 */
function deselectAllBrokenLinks() {
    const checkboxes = document.querySelectorAll('.broken-link-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    selectedBrokenLinkIds.clear();
    updateBrokenLinksBulkActionsUI();
}

/**
 * Select all broken links matching current filters (loads all IDs from server)
 */
async function selectAllFilteredBrokenLinks() {
    try {
        const params = new URLSearchParams({
            include_dismissed: (!hideDismissed).toString()
        });
        
        if (currentErrorTypeFilter) {
            params.append('error_type', currentErrorTypeFilter);
        }
        
        const response = await fetch(`/api/admin/broken-links/ids?${params}`, {
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch broken link IDs');
        }
        
        const data = await response.json();
        allBrokenLinkIds = data.ids;
        
        // Add all IDs to selection
        data.ids.forEach(id => selectedBrokenLinkIds.add(id));
        
        // Check visible checkboxes
        const checkboxes = document.querySelectorAll('.broken-link-checkbox');
        checkboxes.forEach(cb => {
            const id = parseInt(cb.dataset.linkId);
            if (selectedBrokenLinkIds.has(id)) {
                cb.checked = true;
            }
        });
        
        updateBrokenLinksBulkActionsUI();
        showSnackbar(`Selected ${data.count} broken links`, 'success');
        
    } catch (error) {
        console.error('Error fetching broken link IDs:', error);
        showSnackbar('Failed to select all broken links', 'error');
    }
}

/**
 * Delete entries for all selected broken links
 */
async function deleteSelectedBrokenLinkEntries() {
    const count = selectedBrokenLinkIds.size;
    
    if (count === 0) {
        showSnackbar('No broken links selected', 'warning');
        return;
    }
    
    if (!confirm(`Delete all entries associated with ${count} broken link(s)?\n\nThis will permanently delete the entries and all associated comments, claps, and views. This action cannot be undone.`)) {
        return;
    }
    
    const button = document.querySelector('.btn-delete-selected-entries');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
    }
    
    try {
        const response = await fetch('/api/admin/broken-links/delete-entries', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ids: Array.from(selectedBrokenLinkIds)
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to delete entries');
        }
        
        const data = await response.json();
        
        showSnackbar(`Deleted ${data.deleted_entries} entries from ${data.deleted_links} broken links`, 'success');
        
        // Clear selection and reload
        selectedBrokenLinkIds.clear();
        resetAndLoadBrokenLinks();
        
    } catch (error) {
        console.error('Error deleting entries:', error);
        showSnackbar('Failed to delete entries', 'error');
        
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected Entries';
        }
    }
}

/**
 * Update the bulk actions UI for broken links
 */
function updateBrokenLinksBulkActionsUI() {
    const bulkActions = document.getElementById('broken-links-bulk-actions');
    const countSpan = document.getElementById('broken-links-selected-count');
    
    if (!bulkActions) return;
    
    const count = selectedBrokenLinkIds.size;
    
    if (count > 0) {
        bulkActions.style.display = 'flex';
        if (countSpan) {
            countSpan.textContent = `${count} broken link${count !== 1 ? 's' : ''} selected`;
        }
    } else {
        bulkActions.style.display = 'none';
    }
}
