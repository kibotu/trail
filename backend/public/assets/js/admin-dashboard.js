/**
 * Admin Dashboard JavaScript
 * Handles entry loading, editing, deletion, and admin operations
 * 
 * Authentication: All requests use session cookies (credentials: 'same-origin')
 * No JWT tokens are sent in Authorization headers from the admin dashboard.
 */

// Load max text length from config
let MAX_TEXT_LENGTH = 140; // default
loadConfig().then(config => {
    MAX_TEXT_LENGTH = config.max_text_length || 140;
});

let currentPage = 0;
let loading = false;
let hasMore = true;
let currentSourceFilter = '';
// Note: currentTagFilter is declared globally (on window) so admin-tags.js can access it
window.currentTagFilter = '';
const pageSize = 20;

/**
 * Initialize admin dashboard
 */
function initAdminDashboard() {
    // Load initial entries
    loadEntries();

    // Source filter change handler
    const sourceFilter = document.getElementById('source-filter');
    if (sourceFilter) {
        sourceFilter.addEventListener('change', (e) => {
            currentSourceFilter = e.target.value;
            resetAndLoadEntries();
        });
    }

    // Duplicate match type filter change handler
    const dupeMatchFilter = document.getElementById('dupe-match-filter');
    if (dupeMatchFilter) {
        dupeMatchFilter.addEventListener('change', (e) => {
            currentMatchType = e.target.value;
            // Reset and reload duplicates
            document.getElementById('duplicates-container').innerHTML = '';
            duplicatePage = 0;
            duplicateHasMore = true;
            loadDuplicates();
        });
    }

    // Infinite scroll — supports both views
    window.addEventListener('scroll', () => {
        const scrollPosition = window.innerHeight + window.scrollY;
        const threshold = document.documentElement.scrollHeight - 500;
        
        if (scrollPosition >= threshold) {
            if (currentView === 'all' && !loading && hasMore) {
                loadEntries();
            } else if (currentView === 'duplicates' && !duplicateLoading && duplicateHasMore) {
                loadDuplicates();
            }
        }
    });

    // Close menus when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.entry-menu')) {
            const allMenus = document.querySelectorAll('.menu-dropdown');
            allMenus.forEach(m => m.classList.remove('active'));
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDashboard);
} else {
    // DOM is already ready
    initAdminDashboard();
}

/**
 * Reset and reload entries (used when filter changes)
 */
function resetAndLoadEntries() {
    currentPage = 0;
    hasMore = true;
    document.getElementById('entries-container').innerHTML = '';
    loadEntries();
}

/**
 * Load entries from API
 */
async function loadEntries() {
    if (loading || !hasMore) return;
    
    loading = true;
    document.getElementById('loading').style.display = 'block';
    
    try {
        let url = `/api/admin/entries?page=${currentPage}&limit=${pageSize}`;
        if (currentSourceFilter) {
            url += `&source=${encodeURIComponent(currentSourceFilter)}`;
        }
        // Use window.currentTagFilter to ensure we get the value set by admin-tags.js
        if (window.currentTagFilter) {
            url += `&tag=${encodeURIComponent(window.currentTagFilter)}`;
        }
        const response = await fetch(url, {
            credentials: 'same-origin' // Include httpOnly cookie with JWT
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        
        if (data.entries && data.entries.length > 0) {
            renderEntries(data.entries);
            currentPage++;
            hasMore = data.entries.length === pageSize;
        } else {
            hasMore = false;
            if (currentPage === 0) {
                document.getElementById('empty-state').style.display = 'block';
            }
        }
    } catch (error) {
        console.error('Error loading entries:', error);
        alert('Failed to load entries. Please refresh the page.');
    } finally {
        loading = false;
        document.getElementById('loading').style.display = 'none';
    }
}

/**
 * Render entries to the page
 */
function renderEntries(entries) {
    const container = document.getElementById('entries-container');
    
    entries.forEach(entry => {
        // Use shared card template with admin options
        const card = createEntryCard(entry, {
            showSourceBadge: true,  // Show source badges in admin
            canModify: true,        // Admin can modify all entries
            isAdmin: true,          // Admin context
            isLoggedIn: true,       // Admin is always logged in
            currentUserId: null     // Not needed for admin
        });
        container.appendChild(card);
    });
}

/**
 * Toggle menu dropdown
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
    menu.classList.toggle('active');
}

/**
 * Edit an entry
 */
function editEntry(entryId) {
    const card = document.querySelector(`[data-entry-id="${entryId}"]`);
    const contentDiv = card.querySelector('.entry-content');
    const entryText = contentDiv.querySelector('.entry-text').textContent;
    
    const editForm = document.createElement('div');
    editForm.className = 'edit-form';
    editForm.innerHTML = `
        <textarea id="edit-text-${entryId}" class="edit-textarea" maxlength="${MAX_TEXT_LENGTH}">${escapeHtml(entryText)}</textarea>
        <div class="char-counter" id="char-count-${entryId}">${entryText.length}/${MAX_TEXT_LENGTH} characters</div>
        <div class="edit-actions">
            <button class="cancel-button" onclick="cancelEdit(${entryId})">Cancel</button>
            <button class="save-button" onclick="saveEdit(${entryId})">Save</button>
        </div>
    `;
    
    contentDiv.appendChild(editForm);
    
    const textarea = document.getElementById(`edit-text-${entryId}`);
    textarea.addEventListener('input', () => updateCharCount(entryId));
    textarea.focus();
}

/**
 * Cancel editing an entry
 */
function cancelEdit(entryId) {
    const card = document.querySelector(`[data-entry-id="${entryId}"]`);
    const editForm = card.querySelector('.edit-form');
    if (editForm) {
        editForm.remove();
    }
}

/**
 * Update character count for edit textarea
 */
function updateCharCount(entryId) {
    const textarea = document.getElementById(`edit-text-${entryId}`);
    const charCount = document.getElementById(`char-count-${entryId}`);
    const length = textarea.value.length;
    charCount.textContent = `${length}/${MAX_TEXT_LENGTH} characters`;
    
    charCount.className = 'char-counter';
    if (length > MAX_TEXT_LENGTH - 20) {
        charCount.classList.add('error');
    } else if (length > MAX_TEXT_LENGTH - 40) {
        charCount.classList.add('warning');
    }
}

/**
 * Save edited entry
 */
async function saveEdit(entryId) {
    const textarea = document.getElementById(`edit-text-${entryId}`);
    const newText = textarea.value.trim();

    if (!newText) {
        alert('Entry text cannot be empty');
        return;
    }

    try {
        const response = await fetch(`/api/admin/entries/${entryId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // Use session cookie for authentication
            body: JSON.stringify({ text: newText })
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `HTTP ${response.status}`);
        }

        const data = await response.json();

        // Update the entry content
        const card = document.querySelector(`[data-entry-id="${entryId}"]`);
        const contentDiv = card.querySelector('.entry-content');
        const escapedText = escapeHtml(newText);
        const linkedText = linkifyText(escapedText);
        
        contentDiv.innerHTML = `<div class="entry-text">${linkedText}</div>`;

        // Update the timestamp
        const footer = card.querySelector('.entry-footer');
        const timestampDiv = footer.querySelector('.timestamp');
        const editedTimestamp = document.createElement('div');
        editedTimestamp.className = 'timestamp';
        editedTimestamp.innerHTML = `
            <i class="fa-solid fa-pen"></i>
            <span>edited ${formatTimestamp(data.updated_at || new Date().toISOString())}</span>
        `;
        
        // Remove old edited timestamp if exists
        const oldEditedTimestamp = footer.querySelectorAll('.timestamp')[1];
        if (oldEditedTimestamp && oldEditedTimestamp.textContent.includes('edited')) {
            oldEditedTimestamp.remove();
        }
        
        // Insert new edited timestamp after the created timestamp
        timestampDiv.after(editedTimestamp);

    } catch (error) {
        console.error('Error updating entry:', error);
        alert(`Failed to update entry: ${error.message}`);
    }
}

/**
 * Delete an entry
 */
async function deleteEntry(id) {
    if (!confirm('Are you sure you want to delete this entry?')) {
        return;
    }

    try {
        const response = await fetch(`/api/admin/entries/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin' // Use session cookie for authentication
        });

        if (response.ok) {
            const entryCard = document.getElementById(`entry-${id}`);
            entryCard.style.opacity = '0';
            entryCard.style.transform = 'scale(0.95)';
            setTimeout(() => {
                entryCard.remove();
                
                // Check if there are no more entries
                const container = document.getElementById('entries-container');
                if (container.children.length === 0 && currentPage === 0) {
                    document.getElementById('empty-state').style.display = 'block';
                }
            }, 300);
        } else {
            const data = await response.json().catch(() => ({ error: 'Unknown error' }));
            alert('Failed to delete entry: ' + (data.error || `HTTP ${response.status}`));
        }
    } catch (error) {
        console.error('Error deleting entry:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Clear temporary cache files
 */
async function clearCache() {
    if (!confirm('Clear all temporary upload cache files older than 1 hour?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/cache/clear', {
            method: 'POST',
            credentials: 'same-origin' // Use session cookie for authentication
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Failed to clear cache: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error clearing cache:', error);
        alert('Failed to clear cache: ' + error.message);
    }
}

/**
 * Prune orphaned images
 */
async function pruneImages() {
    if (!confirm('Prune orphaned images?\n\nThis will:\n• Delete orphaned image files not referenced by entries, comments, or users\n• Remove database records for images where files no longer exist\n\nThis action cannot be undone.')) {
        return;
    }
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pruning...';
    
    try {
        const response = await fetch('/api/admin/images/prune', {
            method: 'POST',
            credentials: 'same-origin' // Use session cookie for authentication
        });
        
        const data = await response.json();
        
        if (data.success) {
            let message = data.message;
            if (data.details && data.details.length > 0) {
                console.log('Prune details:', data.details);
                message += '\n\nSee console for details.';
            }
            alert(message);
            location.reload();
        } else {
            alert('Failed to prune images: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error pruning images:', error);
        alert('Failed to prune images: ' + error.message);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHTML;
    }
}

/**
 * Prune orphaned views and view counts
 */
async function pruneViews() {
    if (!confirm('Prune orphaned views?\n\nThis will:\n• Delete view counts for entries, comments, and profiles that no longer exist\n• Delete view records for entries, comments, and profiles that no longer exist\n\nThis action cannot be undone.')) {
        return;
    }
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pruning...';
    
    try {
        const response = await fetch('/api/admin/views/prune', {
            method: 'POST',
            credentials: 'same-origin' // Use session cookie for authentication
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Failed to prune views: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error pruning views:', error);
        alert('Failed to prune views: ' + error.message);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHTML;
    }
}

// ============================================================
// Duplicates View
// ============================================================

let currentView = 'all'; // 'all', 'duplicates', 'broken-links', 'tags'
let currentMatchType = 'all'; // 'all', 'text', 'url'
let duplicatePage = 0;
let duplicateLoading = false;
let duplicateHasMore = true;
let tagsLoaded = false;

/**
 * Switch between All Entries and Duplicates view
 */
function switchView(view) {
    currentView = view;

    const entriesContainer = document.getElementById('entries-container');
    const duplicatesContainer = document.getElementById('duplicates-container');
    const brokenLinksContainer = document.getElementById('broken-links-container');
    const tagsContainer = document.getElementById('tags-container');
    const shortLinksContainer = document.getElementById('short-links-container');
    const entriesFilters = document.getElementById('entries-filters');
    const duplicatesFilters = document.getElementById('duplicates-filters');
    const brokenLinksFilters = document.getElementById('broken-links-filters');
    const tagsFilters = document.getElementById('tags-filters');
    const shortLinksFilters = document.getElementById('short-links-filters');
    const bulkActions = document.getElementById('bulk-actions');
    const brokenLinksBulkActions = document.getElementById('broken-links-bulk-actions');
    const emptyState = document.getElementById('empty-state');
    const emptyDuplicatesState = document.getElementById('empty-duplicates-state');
    const emptyBrokenLinksState = document.getElementById('empty-broken-links-state');
    const emptyTagsState = document.getElementById('empty-tags-state');
    const emptyShortLinksState = document.getElementById('empty-short-links-state');
    const sectionTitle = document.getElementById('section-title');

    // Toggle active button
    document.getElementById('view-all').classList.toggle('active', view === 'all');
    document.getElementById('view-duplicates').classList.toggle('active', view === 'duplicates');
    document.getElementById('view-broken-links').classList.toggle('active', view === 'broken-links');
    document.getElementById('view-tags').classList.toggle('active', view === 'tags');
    const viewShortLinksBtn = document.getElementById('view-short-links');
    if (viewShortLinksBtn) {
        viewShortLinksBtn.classList.toggle('active', view === 'short-links');
    }

    // Hide all containers and filters first
    entriesContainer.style.display = 'none';
    duplicatesContainer.style.display = 'none';
    brokenLinksContainer.style.display = 'none';
    tagsContainer.style.display = 'none';
    if (shortLinksContainer) shortLinksContainer.style.display = 'none';
    entriesFilters.style.display = 'none';
    duplicatesFilters.style.display = 'none';
    brokenLinksFilters.style.display = 'none';
    tagsFilters.style.display = 'none';
    if (shortLinksFilters) shortLinksFilters.style.display = 'none';
    bulkActions.style.display = 'none';
    if (brokenLinksBulkActions) brokenLinksBulkActions.style.display = 'none';
    emptyState.style.display = 'none';
    emptyDuplicatesState.style.display = 'none';
    emptyBrokenLinksState.style.display = 'none';
    emptyTagsState.style.display = 'none';
    if (emptyShortLinksState) emptyShortLinksState.style.display = 'none';
    
    // Clear broken links selection when switching away
    if (view !== 'broken-links' && typeof selectedBrokenLinkIds !== 'undefined') {
        selectedBrokenLinkIds.clear();
    }

    if (view === 'all') {
        entriesContainer.style.display = '';
        entriesFilters.style.display = '';
        sectionTitle.textContent = 'All Entries';
    } else if (view === 'duplicates') {
        duplicatesContainer.style.display = '';
        duplicatesFilters.style.display = '';
        sectionTitle.textContent = 'Duplicate Entries';

        // Load duplicates if not already loaded
        if (duplicatesContainer.children.length === 0) {
            duplicatePage = 0;
            duplicateHasMore = true;
            loadDuplicates();
        }
    } else if (view === 'broken-links') {
        brokenLinksContainer.style.display = '';
        brokenLinksFilters.style.display = '';
        sectionTitle.textContent = 'Broken Links';

        // Load broken links if not already loaded
        if (brokenLinksContainer.children.length === 0) {
            brokenLinksPage = 0;
            brokenLinksHasMore = true;
            loadBrokenLinks();
        }
    } else if (view === 'tags') {
        tagsContainer.style.display = '';
        tagsFilters.style.display = '';
        sectionTitle.textContent = 'Tag Management';

        // Load tags if not already loaded
        if (typeof loadTags === 'function' && !tagsLoaded) {
            loadTags();
        }
    } else if (view === 'short-links') {
        if (shortLinksContainer) shortLinksContainer.style.display = '';
        if (shortLinksFilters) shortLinksFilters.style.display = '';
        sectionTitle.textContent = 'Short Links';

        // Load short links if not already loaded
        if (shortLinksContainer && shortLinksContainer.children.length === 0) {
            if (typeof loadShortLinks === 'function') {
                shortLinksPage = 0;
                shortLinksHasMore = true;
                loadShortLinks();
            }
        }
    }
}

/**
 * Load duplicate groups from API
 */
async function loadDuplicates() {
    if (duplicateLoading || !duplicateHasMore) return;

    duplicateLoading = true;
    document.getElementById('loading').style.display = 'block';

    try {
        const url = `/api/admin/duplicates?page=${duplicatePage}&limit=20&match_type=${encodeURIComponent(currentMatchType)}`;
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();

        if (data.groups && data.groups.length > 0) {
            renderDuplicateGroups(data.groups);
            duplicatePage++;
            duplicateHasMore = data.groups.length === 20;

            // Show bulk actions
            const bulkActions = document.getElementById('bulk-actions');
            bulkActions.style.display = '';
            document.getElementById('dupe-summary').textContent =
                `${data.total_groups} duplicate group${data.total_groups !== 1 ? 's' : ''} found`;
        } else {
            duplicateHasMore = false;
            if (duplicatePage === 0) {
                document.getElementById('empty-duplicates-state').style.display = 'block';
                document.getElementById('bulk-actions').style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error loading duplicates:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to load duplicates.', 'error');
        }
    } finally {
        duplicateLoading = false;
        document.getElementById('loading').style.display = 'none';
    }
}

/**
 * Render duplicate groups to the page
 */
function renderDuplicateGroups(groups) {
    const container = document.getElementById('duplicates-container');

    groups.forEach(group => {
        const card = createDuplicateGroupCard(group);
        container.appendChild(card);
    });
}

/**
 * Create a duplicate group card element
 */
function createDuplicateGroupCard(group) {
    const card = document.createElement('div');
    card.className = 'duplicate-group-card';
    card.dataset.matchType = group.match_type;

    const matchConfig = {
        'text': { icon: 'fa-font', label: 'Same Text' },
        'url': { icon: 'fa-link', label: 'Same URL Preview' },
        'text_url': { icon: 'fa-file-lines', label: 'Same URL in Text' },
    };
    const match = matchConfig[group.match_type] || matchConfig['text'];
    const matchIcon = match.icon;
    const matchLabel = match.label;
    const displayName = group.user_nickname || group.user_name || 'Unknown';
    const avatarUrl = group.avatar_url || '';

    // Build entries list
    let entriesHtml = '';
    const entryIds = group.entries.map(e => e.id);

    group.entries.forEach((entry, index) => {
        const isFirst = index === 0;
        const claps = entry.clap_count || 0;
        const comments = entry.comment_count || 0;
        const views = entry.view_count || 0;
        const hashId = entry.hash_id || entry.id;
        const timestamp = formatTimestamp(entry.created_at);
        const hasEngagement = claps > 0 || comments > 0 || views > 0;

        entriesHtml += `
            <div class="duplicate-entry-item ${isFirst ? 'keep' : 'removable'}">
                <div class="duplicate-entry-header">
                    <div class="duplicate-entry-info">
                        ${isFirst ? '<span class="keep-badge"><i class="fa-solid fa-star"></i> Keep (oldest)</span>' : ''}
                        <span class="duplicate-entry-id">#${entry.id}</span>
                        <span class="duplicate-entry-time">${timestamp}</span>
                    </div>
                    <div class="duplicate-entry-actions">
                        <a href="/status/${hashId}" target="_blank" class="button secondary tiny" title="View entry">
                            <i class="fa-solid fa-external-link-alt"></i>
                        </a>
                        ${!isFirst ? `
                            <button onclick="deleteSingleDuplicate(${entry.id}, this)" class="button danger tiny" title="Delete this entry">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
                <div class="duplicate-entry-stats">
                    <span><i class="fa-solid fa-heart"></i> ${claps}</span>
                    <span><i class="fa-regular fa-comment"></i> ${comments}</span>
                    <span><i class="fa-solid fa-chart-simple"></i> ${views}</span>
                    ${hasEngagement && !isFirst ? '<span class="engagement-warning"><i class="fa-solid fa-triangle-exclamation"></i> Has engagement</span>' : ''}
                </div>
                ${entry.text ? `<div class="duplicate-entry-text">${escapeHtml(entry.text).substring(0, 150)}${entry.text.length > 150 ? '...' : ''}</div>` : ''}
            </div>
        `;
    });

    card.innerHTML = `
        <div class="duplicate-group-header">
            <div class="duplicate-group-user">
                ${avatarUrl ? `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(displayName)}" class="avatar-small">` : ''}
                <div>
                    <span class="duplicate-group-username">${escapeHtml(displayName)}</span>
                    <span class="duplicate-group-count">${group.dupe_count} duplicates</span>
                </div>
            </div>
            <div class="duplicate-match-badge ${group.match_type}">
                <i class="fa-solid ${matchIcon}"></i>
                <span>${matchLabel}</span>
            </div>
        </div>
        <div class="duplicate-matched-value">
            <i class="fa-solid fa-quote-left" style="opacity: 0.4;"></i>
            <span>${escapeHtml(group.matched_value)}</span>
        </div>
        <div class="duplicate-entries-list">
            ${entriesHtml}
        </div>
        <div class="duplicate-group-actions">
            <button onclick="resolveDuplicateGroup([${entryIds.join(',')}], 'oldest', this)" class="button primary small">
                <i class="fa-solid fa-check"></i> Keep Oldest &amp; Remove Duplicates
            </button>
            <button onclick="resolveDuplicateGroup([${entryIds.join(',')}], 'newest', this)" class="button secondary small">
                <i class="fa-solid fa-arrow-up"></i> Keep Newest
            </button>
        </div>
    `;

    return card;
}

/**
 * Resolve a single duplicate group
 */
async function resolveDuplicateGroup(entryIds, keep, buttonElement) {
    const groupCard = buttonElement.closest('.duplicate-group-card');
    const count = entryIds.length - 1;

    if (!confirm(`Delete ${count} duplicate entr${count === 1 ? 'y' : 'ies'} and keep the ${keep}?`)) {
        return;
    }

    // Disable buttons
    const buttons = groupCard.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);
    buttonElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Resolving...';

    try {
        const response = await fetch('/api/admin/duplicates/resolve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ entry_ids: entryIds, keep })
        });

        const data = await response.json();

        if (data.success) {
            // Animate removal
            groupCard.style.transition = 'opacity 0.3s, transform 0.3s';
            groupCard.style.opacity = '0';
            groupCard.style.transform = 'scale(0.95)';
            setTimeout(() => {
                groupCard.remove();
                // Update badge count
                updateDuplicateBadge(-1);
                // Check if empty
                const container = document.getElementById('duplicates-container');
                if (container.children.length === 0) {
                    document.getElementById('empty-duplicates-state').style.display = 'block';
                    document.getElementById('bulk-actions').style.display = 'none';
                }
            }, 300);

            if (typeof showSnackbar === 'function') {
                showSnackbar(data.message, 'success');
            }
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error resolving duplicates:', error);
        buttons.forEach(b => b.disabled = false);
        buttonElement.innerHTML = '<i class="fa-solid fa-check"></i> Keep Oldest &amp; Remove Duplicates';
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to resolve duplicates: ' + error.message, 'error');
        }
    }
}

/**
 * Delete a single duplicate entry
 */
async function deleteSingleDuplicate(entryId, buttonElement) {
    if (!confirm('Delete this duplicate entry?')) return;

    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const response = await fetch(`/api/admin/entries/${entryId}`, {
            method: 'DELETE',
            credentials: 'same-origin'
        });

        if (response.ok) {
            const entryItem = buttonElement.closest('.duplicate-entry-item');
            const groupCard = entryItem.closest('.duplicate-group-card');

            entryItem.style.transition = 'opacity 0.3s, height 0.3s, margin 0.3s, padding 0.3s';
            entryItem.style.opacity = '0';
            setTimeout(() => {
                entryItem.remove();

                // Check remaining entries in this group
                if (groupCard) {
                    const remainingEntries = groupCard.querySelectorAll('.duplicate-entry-item');
                    if (remainingEntries.length <= 1) {
                        // No more duplicates in this group
                        groupCard.style.transition = 'opacity 0.3s, transform 0.3s';
                        groupCard.style.opacity = '0';
                        groupCard.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            groupCard.remove();
                            updateDuplicateBadge(-1);
                            const container = document.getElementById('duplicates-container');
                            if (container.children.length === 0) {
                                document.getElementById('empty-duplicates-state').style.display = 'block';
                                document.getElementById('bulk-actions').style.display = 'none';
                            }
                        }, 300);
                    } else {
                        // Update count in header
                        const countEl = groupCard.querySelector('.duplicate-group-count');
                        if (countEl) {
                            countEl.textContent = `${remainingEntries.length} duplicates`;
                        }
                    }
                }
            }, 300);

            if (typeof showSnackbar === 'function') {
                showSnackbar('Entry deleted', 'success');
            }
        } else {
            throw new Error('Failed to delete entry');
        }
    } catch (error) {
        console.error('Error deleting entry:', error);
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i class="fa-solid fa-trash"></i>';
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to delete entry', 'error');
        }
    }
}

/**
 * Resolve all duplicate groups at once
 */
async function resolveAllDuplicates(keep) {
    const matchType = currentMatchType;
    const container = document.getElementById('duplicates-container');
    const groupCount = container.children.length;

    if (!confirm(`Resolve ALL ${groupCount} duplicate groups? This will keep the ${keep} entry in each group and delete all others.\n\nThis action cannot be undone.`)) {
        return;
    }

    const bulkButtons = document.querySelectorAll('.bulk-actions-buttons button');
    bulkButtons.forEach(b => b.disabled = true);

    try {
        const response = await fetch('/api/admin/duplicates/resolve-all', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ match_type: matchType, keep })
        });

        const data = await response.json();

        if (data.success) {
            if (typeof showSnackbar === 'function') {
                showSnackbar(data.message, 'success');
            }

            // Reload page to update stat cards
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error resolving all duplicates:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to resolve duplicates: ' + error.message, 'error');
        }
    } finally {
        bulkButtons.forEach(b => b.disabled = false);
    }
}

/**
 * Update the duplicate badge count in the view toggle
 */
function updateDuplicateBadge(delta) {
    const badge = document.querySelector('#view-duplicates .dupe-badge');
    if (badge) {
        const current = parseInt(badge.textContent, 10) || 0;
        const newCount = Math.max(0, current + delta);
        badge.textContent = newCount;
        if (newCount === 0) {
            badge.style.display = 'none';
        }
    }
}
