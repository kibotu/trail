/**
 * Admin Dashboard JavaScript
 * Handles entry loading, editing, deletion, and admin operations
 */

// JWT token for admin operations (will be set by the page)
let jwtToken = '';

// Load max text length from config
let MAX_TEXT_LENGTH = 140; // default
loadConfig().then(config => {
    MAX_TEXT_LENGTH = config.max_text_length || 140;
});

let currentPage = 0;
let loading = false;
let hasMore = true;
let currentSourceFilter = '';
const pageSize = 20;

/**
 * Initialize admin dashboard
 */
function initAdminDashboard(token) {
    jwtToken = token;
    
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

    // Infinite scroll
    window.addEventListener('scroll', () => {
        if (loading || !hasMore) return;
        
        const scrollPosition = window.innerHeight + window.scrollY;
        const threshold = document.documentElement.scrollHeight - 500;
        
        if (scrollPosition >= threshold) {
            loadEntries();
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

    const token = jwtToken || localStorage.getItem('trail_jwt');
    if (!token) {
        alert('You must be logged in to edit entries');
        return;
    }

    try {
        const response = await fetch(`/api/admin/entries/${entryId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
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

    const token = jwtToken || localStorage.getItem('trail_jwt');
    if (!token) {
        alert('Authentication token not found. Please refresh the page and log in again.');
        return;
    }

    try {
        const response = await fetch(`/api/admin/entries/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + token
            }
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
    if (!jwtToken) {
        alert('Authentication token not found. Please refresh the page and log in again.');
        return;
    }
    
    if (!confirm('Clear all temporary upload cache files older than 1 hour?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/cache/clear', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + jwtToken
            }
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
    if (!jwtToken) {
        alert('Authentication token not found. Please refresh the page and log in again.');
        return;
    }
    
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
            headers: {
                'Authorization': 'Bearer ' + jwtToken
            }
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
