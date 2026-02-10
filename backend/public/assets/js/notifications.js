// Notification System JavaScript
// Poll unread count every 30 seconds
let notificationPollInterval;

function startNotificationPolling() {
    // Initial load
    loadNotificationBadge();
    
    // Poll every 30 seconds
    notificationPollInterval = setInterval(async () => {
        try {
            const response = await fetch('/api/notifications?limit=5');
            if (!response.ok) throw new Error('Failed to fetch notifications');
            
            const data = await response.json();
            updateBadge(data.unread_count);
            
            // Update dropdown if it's open
            const dropdown = document.getElementById('notification-dropdown');
            if (dropdown && !dropdown.classList.contains('hidden')) {
                updateDropdownList(data.notifications);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    }, 30000);
}

async function loadNotificationBadge() {
    try {
        const response = await fetch('/api/notifications?limit=1');
        if (!response.ok) throw new Error('Failed to fetch notifications');
        
        const data = await response.json();
        updateBadge(data.unread_count);
    } catch (error) {
        console.error('Failed to load notification badge:', error);
    }
}

function updateBadge(unreadCount) {
    const badge = document.getElementById('notification-badge');
    if (!badge) return;
    
    if (unreadCount > 0) {
        badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    if (!dropdown) return;
    
    dropdown.classList.toggle('hidden');
    
    if (!dropdown.classList.contains('hidden')) {
        loadDropdownNotifications();
    }
}

async function loadDropdownNotifications() {
    try {
        const response = await fetch('/api/notifications?limit=5');
        if (!response.ok) throw new Error('Failed to fetch notifications');
        
        const data = await response.json();
        updateDropdownList(data.notifications);
    } catch (error) {
        console.error('Failed to load dropdown notifications:', error);
        const list = document.getElementById('notification-dropdown-list');
        if (list) {
            list.innerHTML = '<p class="no-notifications">Failed to load notifications</p>';
        }
    }
}

function updateDropdownList(notifications) {
    const list = document.getElementById('notification-dropdown-list');
    if (!list) return;
    
    if (notifications.length === 0) {
        list.innerHTML = '<p class="no-notifications">No new notifications</p>';
        return;
    }
    
    list.innerHTML = notifications.map(n => {
        // Check if this is a grouped clap notification
        if (n.actors && n.actors.length > 0) {
            // Grouped clap notification with multiple avatars
            const avatarsHtml = n.actors.slice(0, 3).map(actor => 
                `<img src="${escapeHtml(actor.avatar_url)}" 
                     alt="${escapeHtml(actor.name)}"
                     class="avatar-stacked-small">`
            ).join('');
            
            const clapBadge = n.clap_count > 1 ? 
                `<span class="clap-count-badge-small">${n.clap_count}</span>` : '';
            
            return `
                <a href="${escapeHtml(n.link)}" 
                   class="dropdown-notification-item ${n.is_read ? '' : 'unread'}"
                   onclick="markAsRead(${n.id}); event.stopPropagation();">
                    <div class="notification-avatars-small">
                        ${avatarsHtml}
                    </div>
                    <div class="dropdown-notification-content">
                        <p>${escapeHtml(n.action_text)} ${clapBadge}</p>
                        ${n.preview_text ? `<p class="preview-text">"${escapeHtml(n.preview_text)}"</p>` : ''}
                        <span class="time">${escapeHtml(n.relative_time)}</span>
                    </div>
                    ${!n.is_read ? '<span class="unread-dot-small"></span>' : ''}
                </a>
            `;
        } else {
            // Regular notification
            return `
                <a href="${escapeHtml(n.link)}" 
                   class="dropdown-notification-item ${n.is_read ? '' : 'unread'}"
                   onclick="markAsRead(${n.id}); event.stopPropagation();">
                    <img src="${escapeHtml(n.actor_avatar_url)}" 
                         alt="${escapeHtml(n.actor_display_name)}"
                         class="avatar-small">
                    <div class="dropdown-notification-content">
                        <p><strong>${escapeHtml(n.actor_display_name)}</strong> ${escapeHtml(n.action_text)}</p>
                        ${n.preview_text ? `<p class="preview-text">"${escapeHtml(n.preview_text)}"</p>` : ''}
                        <span class="time">${escapeHtml(n.relative_time)}</span>
                    </div>
                    ${!n.is_read ? '<span class="unread-dot-small"></span>' : ''}
                </a>
            `;
        }
    }).join('');
}

async function markAsRead(notificationId) {
    try {
        const response = await fetch(`/api/notifications/${notificationId}/read`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) throw new Error('Failed to mark notification as read');
        
        // Update UI
        const item = document.querySelector(`[data-id="${notificationId}"]`);
        if (item) {
            item.classList.remove('unread');
            const dot = item.querySelector('.unread-dot');
            if (dot) dot.remove();
        }
        
        // Update badge
        const badge = document.getElementById('notification-badge');
        if (badge && !badge.classList.contains('hidden')) {
            const currentCount = parseInt(badge.textContent);
            updateBadge(Math.max(0, currentCount - 1));
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

async function markAllAsRead() {
    try {
        const response = await fetch('/api/notifications/read-all', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) throw new Error('Failed to mark all as read');
        
        // Reload page or update all items
        location.reload();
    } catch (error) {
        console.error('Failed to mark all as read:', error);
        alert('Failed to mark all notifications as read');
    }
}

async function deleteNotification(event, notificationId) {
    event.stopPropagation();
    
    if (!confirm('Delete this notification?')) return;
    
    try {
        const response = await fetch(`/api/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) throw new Error('Failed to delete notification');
        
        // Remove from UI
        const item = document.querySelector(`[data-id="${notificationId}"]`);
        if (item) {
            item.remove();
            
            // Check if group is now empty
            const group = item.closest('.notification-group');
            if (group && group.querySelectorAll('.notification-item').length === 0) {
                group.remove();
            }
        }
        
        // Update badge
        loadNotificationBadge();
    } catch (error) {
        console.error('Failed to delete notification:', error);
        alert('Failed to delete notification');
    }
}

function handleNotificationClick(notificationId, link) {
    markAsRead(notificationId);
    window.location.href = link;
}

async function savePreferences(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const preferences = {
        email_on_mention: formData.get('email_on_mention') === 'on',
        email_on_comment: formData.get('email_on_comment') === 'on',
        email_on_clap: formData.get('email_on_clap') === 'on'
    };
    
    try {
        const response = await fetch('/api/notifications/preferences', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(preferences)
        });
        
        if (!response.ok) throw new Error('Failed to save preferences');
        
        // Show success message
        const successMsg = document.getElementById('save-success');
        const errorMsg = document.getElementById('save-error');
        
        if (successMsg) {
            successMsg.classList.remove('hidden');
            setTimeout(() => successMsg.classList.add('hidden'), 3000);
        }
        
        if (errorMsg) {
            errorMsg.classList.add('hidden');
        }
    } catch (error) {
        console.error('Failed to save preferences:', error);
        
        // Show error message
        const errorMsg = document.getElementById('save-error');
        const successMsg = document.getElementById('save-success');
        
        if (errorMsg) {
            errorMsg.classList.remove('hidden');
            setTimeout(() => errorMsg.classList.add('hidden'), 3000);
        }
        
        if (successMsg) {
            successMsg.classList.add('hidden');
        }
    }
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notification-dropdown');
    const bell = document.getElementById('notification-bell');
    
    if (dropdown && bell && !dropdown.contains(event.target) && !bell.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});

// Start polling when page loads
document.addEventListener('DOMContentLoaded', () => {
    // Only start polling if notification bell exists (user is logged in)
    if (document.getElementById('notification-bell')) {
        startNotificationPolling();
    }
});
