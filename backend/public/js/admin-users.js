/**
 * Admin Users Management JavaScript
 * Handles user management operations (delete user, delete entries, delete comments)
 * 
 * Authentication: All requests use session cookies (credentials: 'same-origin')
 * No JWT tokens are sent in Authorization headers from the admin users page.
 */

/**
 * Toggle debug section for a user
 */
function toggleDebug(userId) {
    const debugContent = document.getElementById(`debug-${userId}`);
    const debugIcon = document.getElementById(`debug-icon-${userId}`);
    
    if (debugContent.classList.contains('show')) {
        debugContent.classList.remove('show');
        debugIcon.className = 'fa-solid fa-caret-right';
    } else {
        debugContent.classList.add('show');
        debugIcon.className = 'fa-solid fa-caret-down';
    }
}

/**
 * Delete a user and all their entries
 */
async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? This will also delete all their entries.')) {
        return;
    }

    try {
        const response = await fetch(`/api/admin/users/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin' // Session-based authentication (secure)
        });

        if (response.ok) {
            const userCard = document.getElementById(`user-${id}`);
            userCard.style.opacity = '0';
            userCard.style.transform = 'scale(0.95)';
            setTimeout(() => {
                userCard.remove();
                
                // Check if there are no more users
                const grid = document.querySelector('.users-grid');
                if (grid && grid.children.length === 0) {
                    location.reload();
                }
            }, 300);
        } else {
            const data = await response.json().catch(() => ({ error: 'Unknown error' }));
            alert('Failed to delete user: ' + (data.error || `HTTP ${response.status}`));
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Delete all entries for a user
 */
async function deleteUserEntries(userId, count) {
    if (count === 0) {
        alert('This user has no entries to delete.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete all ${count} entries from this user? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/users/${userId}/entries`, {
            method: 'DELETE',
            credentials: 'same-origin' // Session-based authentication (secure)
        });
        
        if (response.ok) {
            const data = await response.json();
            alert(`Successfully deleted ${data.deleted} entries.`);
            location.reload();
        } else {
            const data = await response.json().catch(() => ({ error: 'Unknown error' }));
            alert('Failed to delete entries: ' + (data.error || `HTTP ${response.status}`));
        }
    } catch (error) {
        console.error('Error deleting entries:', error);
        alert('Error: ' + error.message);
    }
}

/**
 * Delete all comments for a user
 */
async function deleteUserComments(userId, count) {
    if (count === 0) {
        alert('This user has no comments to delete.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete all ${count} comments from this user? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/users/${userId}/comments`, {
            method: 'DELETE',
            credentials: 'same-origin' // Session-based authentication (secure)
        });
        
        if (response.ok) {
            const data = await response.json();
            alert(`Successfully deleted ${data.deleted} comments.`);
            location.reload();
        } else {
            const data = await response.json().catch(() => ({ error: 'Unknown error' }));
            alert('Failed to delete comments: ' + (data.error || `HTTP ${response.status}`));
        }
    } catch (error) {
        console.error('Error deleting comments:', error);
        alert('Error: ' + error.message);
    }
}
