/**
 * Admin Tags Management JavaScript
 * Handles tag listing, editing, deletion, and merging
 */

// tagsLoaded is declared in admin-dashboard.js
let allTags = [];

/**
 * Initialize tags functionality
 */
function initTagsManagement() {
    // Load tags for the filter dropdown
    loadTagsForFilter();

    // Tag filter change handler - update global currentTagFilter used by admin-dashboard.js
    const tagFilter = document.getElementById('tag-filter');
    if (tagFilter) {
        tagFilter.addEventListener('change', (e) => {
            // Update the global variable (declared in admin-dashboard.js on window)
            window.currentTagFilter = e.target.value;
            resetAndLoadEntries();
        });
    }

    // Tags search handler
    const tagsSearch = document.getElementById('tags-search');
    if (tagsSearch) {
        let searchTimeout;
        tagsSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadTags(e.target.value);
            }, 300);
        });
    }
}

// Current tag filter for entries is now on window.currentTagFilter (declared in admin-dashboard.js)

/**
 * Load tags for the filter dropdown
 */
async function loadTagsForFilter() {
    try {
        const response = await fetch('/api/admin/tags', {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        const tagFilter = document.getElementById('tag-filter');
        
        if (tagFilter && data.tags) {
            // Keep the "All Tags" option
            tagFilter.innerHTML = '<option value="">All Tags</option>';
            
            data.tags.forEach(tag => {
                const option = document.createElement('option');
                option.value = tag.id;
                option.textContent = `${tag.name} (${tag.entry_count})`;
                tagFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading tags for filter:', error);
    }
}

/**
 * Load tags for the tags management view
 */
async function loadTags(searchQuery = '') {
    const container = document.getElementById('tags-container');
    const emptyState = document.getElementById('empty-tags-state');
    const loading = document.getElementById('loading');

    loading.style.display = 'block';
    container.innerHTML = '';

    try {
        let url = '/api/admin/tags';
        if (searchQuery) {
            url += `?search=${encodeURIComponent(searchQuery)}`;
        }

        const response = await fetch(url, {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        allTags = data.tags || [];

        if (allTags.length > 0) {
            renderTags(allTags);
            emptyState.style.display = 'none';
        } else {
            container.innerHTML = '';
            emptyState.style.display = 'block';
        }

        tagsLoaded = true;
    } catch (error) {
        console.error('Error loading tags:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to load tags.', 'error');
        }
    } finally {
        loading.style.display = 'none';
    }
}

/**
 * Render tags to the container
 */
function renderTags(tags) {
    const container = document.getElementById('tags-container');
    container.innerHTML = '';

    // Create a table for better display
    const table = document.createElement('div');
    table.className = 'tags-table';
    table.innerHTML = `
        <div class="tags-table-header">
            <div class="tags-col-name">Tag Name</div>
            <div class="tags-col-slug">Slug</div>
            <div class="tags-col-count">Entries</div>
            <div class="tags-col-actions">Actions</div>
        </div>
    `;

    tags.forEach(tag => {
        const row = createTagRow(tag);
        table.appendChild(row);
    });

    container.appendChild(table);
}

/**
 * Create a tag row element
 */
function createTagRow(tag) {
    const row = document.createElement('div');
    row.className = 'tags-table-row';
    row.dataset.tagId = tag.id;

    row.innerHTML = `
        <div class="tags-col-name">
            <span class="tag-name-display">${escapeHtml(tag.name)}</span>
            <input type="text" class="tag-name-input" value="${escapeHtml(tag.name)}" style="display: none;" maxlength="100">
        </div>
        <div class="tags-col-slug">
            <code>${escapeHtml(tag.slug)}</code>
        </div>
        <div class="tags-col-count">
            <span class="entry-count">${tag.entry_count || 0}</span>
            ${tag.entry_count > 0 ? `
                <button class="button secondary tiny" onclick="filterEntriesByTag(${tag.id})" title="View entries with this tag">
                    <i class="fa-solid fa-eye"></i>
                </button>
            ` : ''}
        </div>
        <div class="tags-col-actions">
            <button class="button secondary tiny edit-btn" onclick="editTag(${tag.id})" title="Edit tag">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button class="button secondary tiny save-btn" onclick="saveTag(${tag.id})" title="Save changes" style="display: none;">
                <i class="fa-solid fa-check"></i>
            </button>
            <button class="button secondary tiny cancel-btn" onclick="cancelEditTag(${tag.id})" title="Cancel" style="display: none;">
                <i class="fa-solid fa-times"></i>
            </button>
            <button class="button secondary tiny" onclick="openMergeDialog(${tag.id}, '${escapeHtml(tag.name)}')" title="Merge into another tag">
                <i class="fa-solid fa-code-merge"></i>
            </button>
            <button class="button danger tiny" onclick="deleteTag(${tag.id}, '${escapeHtml(tag.name)}')" title="Delete tag">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;

    return row;
}

/**
 * Edit a tag (show inline edit form)
 */
function editTag(tagId) {
    const row = document.querySelector(`.tags-table-row[data-tag-id="${tagId}"]`);
    if (!row) return;

    const nameDisplay = row.querySelector('.tag-name-display');
    const nameInput = row.querySelector('.tag-name-input');
    const editBtn = row.querySelector('.edit-btn');
    const saveBtn = row.querySelector('.save-btn');
    const cancelBtn = row.querySelector('.cancel-btn');

    nameDisplay.style.display = 'none';
    nameInput.style.display = 'inline-block';
    nameInput.focus();
    nameInput.select();

    editBtn.style.display = 'none';
    saveBtn.style.display = 'inline-flex';
    cancelBtn.style.display = 'inline-flex';

    // Handle Enter key
    nameInput.onkeydown = (e) => {
        if (e.key === 'Enter') {
            saveTag(tagId);
        } else if (e.key === 'Escape') {
            cancelEditTag(tagId);
        }
    };
}

/**
 * Cancel editing a tag
 */
function cancelEditTag(tagId) {
    const row = document.querySelector(`.tags-table-row[data-tag-id="${tagId}"]`);
    if (!row) return;

    const nameDisplay = row.querySelector('.tag-name-display');
    const nameInput = row.querySelector('.tag-name-input');
    const editBtn = row.querySelector('.edit-btn');
    const saveBtn = row.querySelector('.save-btn');
    const cancelBtn = row.querySelector('.cancel-btn');

    // Restore original value
    nameInput.value = nameDisplay.textContent;

    nameDisplay.style.display = 'inline';
    nameInput.style.display = 'none';

    editBtn.style.display = 'inline-flex';
    saveBtn.style.display = 'none';
    cancelBtn.style.display = 'none';
}

/**
 * Save edited tag
 */
async function saveTag(tagId) {
    const row = document.querySelector(`.tags-table-row[data-tag-id="${tagId}"]`);
    if (!row) return;

    const nameInput = row.querySelector('.tag-name-input');
    const newName = nameInput.value.trim();

    if (!newName) {
        if (typeof showSnackbar === 'function') {
            showSnackbar('Tag name cannot be empty', 'error');
        }
        return;
    }

    const saveBtn = row.querySelector('.save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const response = await fetch(`/api/admin/tags/${tagId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ name: newName })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to update tag');
        }

        // Update the display
        const nameDisplay = row.querySelector('.tag-name-display');
        const slugDisplay = row.querySelector('.tags-col-slug code');
        
        nameDisplay.textContent = data.tag.name;
        slugDisplay.textContent = data.tag.slug;

        cancelEditTag(tagId);

        if (typeof showSnackbar === 'function') {
            showSnackbar('Tag updated successfully', 'success');
        }

        // Refresh the filter dropdown
        loadTagsForFilter();

    } catch (error) {
        console.error('Error updating tag:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar(error.message, 'error');
        }
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
    }
}

/**
 * Delete a tag
 */
async function deleteTag(tagId, tagName) {
    if (!confirm(`Delete tag "${tagName}"?\n\nThis will remove the tag from all entries. This action cannot be undone.`)) {
        return;
    }

    const row = document.querySelector(`.tags-table-row[data-tag-id="${tagId}"]`);
    if (!row) return;

    try {
        const response = await fetch(`/api/admin/tags/${tagId}`, {
            method: 'DELETE',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to delete tag');
        }

        // Animate removal
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            row.remove();
            
            // Check if empty
            const container = document.getElementById('tags-container');
            const remainingRows = container.querySelectorAll('.tags-table-row');
            if (remainingRows.length === 0) {
                document.getElementById('empty-tags-state').style.display = 'block';
            }
        }, 300);

        if (typeof showSnackbar === 'function') {
            showSnackbar('Tag deleted successfully', 'success');
        }

        // Refresh the filter dropdown
        loadTagsForFilter();

    } catch (error) {
        console.error('Error deleting tag:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar(error.message, 'error');
        }
    }
}

/**
 * Open merge dialog for a tag
 */
function openMergeDialog(sourceId, sourceName) {
    // Create a simple modal dialog
    const existingModal = document.getElementById('merge-modal');
    if (existingModal) {
        existingModal.remove();
    }

    // Build options from all tags except the source
    let options = allTags
        .filter(t => t.id !== sourceId)
        .map(t => `<option value="${t.id}">${escapeHtml(t.name)} (${t.entry_count} entries)</option>`)
        .join('');

    if (!options) {
        if (typeof showSnackbar === 'function') {
            showSnackbar('No other tags to merge into', 'error');
        }
        return;
    }

    const modal = document.createElement('div');
    modal.id = 'merge-modal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-code-merge"></i> Merge Tag</h3>
                <button class="modal-close" onclick="closeMergeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Merge <strong>"${escapeHtml(sourceName)}"</strong> into another tag.</p>
                <p style="color: var(--text-muted); font-size: 0.875rem;">All entries with this tag will be reassigned to the target tag, and this tag will be deleted.</p>
                <div style="margin-top: 1rem;">
                    <label for="merge-target">Target tag:</label>
                    <select id="merge-target" class="source-filter-select" style="width: 100%; margin-top: 0.5rem;">
                        ${options}
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="button secondary" onclick="closeMergeModal()">Cancel</button>
                <button class="button primary" onclick="executeMerge(${sourceId})">
                    <i class="fa-solid fa-code-merge"></i> Merge
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeMergeModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') {
            closeMergeModal();
            document.removeEventListener('keydown', escHandler);
        }
    });
}

/**
 * Close merge modal
 */
function closeMergeModal() {
    const modal = document.getElementById('merge-modal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Execute tag merge
 */
async function executeMerge(sourceId) {
    const targetSelect = document.getElementById('merge-target');
    const targetId = parseInt(targetSelect.value, 10);

    if (!targetId) {
        if (typeof showSnackbar === 'function') {
            showSnackbar('Please select a target tag', 'error');
        }
        return;
    }

    const mergeBtn = document.querySelector('#merge-modal .button.primary');
    mergeBtn.disabled = true;
    mergeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Merging...';

    try {
        const response = await fetch(`/api/admin/tags/${sourceId}/merge`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ target_id: targetId })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to merge tags');
        }

        closeMergeModal();

        if (typeof showSnackbar === 'function') {
            showSnackbar(data.message, 'success');
        }

        // Reload tags
        loadTags();
        loadTagsForFilter();

    } catch (error) {
        console.error('Error merging tags:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar(error.message, 'error');
        }
        mergeBtn.disabled = false;
        mergeBtn.innerHTML = '<i class="fa-solid fa-code-merge"></i> Merge';
    }
}

/**
 * Filter entries by a specific tag (switch to entries view with tag filter)
 */
function filterEntriesByTag(tagId) {
    // Set the tag filter
    const tagFilter = document.getElementById('tag-filter');
    if (tagFilter) {
        tagFilter.value = tagId;
        window.currentTagFilter = tagId.toString();
    }

    // Switch to entries view
    switchView('all');
    resetAndLoadEntries();
}

/**
 * Override resetAndLoadEntries to include tag filter
 */
const originalResetAndLoadEntries = typeof resetAndLoadEntries !== 'undefined' ? resetAndLoadEntries : null;

// Will be called from admin-dashboard.js but we need to track tag filter
function getTagFilterValue() {
    return window.currentTagFilter || '';
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTagsManagement);
} else {
    initTagsManagement();
}
