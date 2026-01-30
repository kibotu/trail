/**
 * Profile Manager - User profile operations
 * 
 * Handles loading, updating, and displaying user profile information,
 * including profile images and muted users management.
 */

class ProfileManager {
    constructor(options = {}) {
        this.apiBase = options.apiBase || '/api';
        this.currentProfile = null;
        this.mutedUsersData = [];
        
        // Element IDs
        this.elements = {
            loading: options.loadingId || 'loading',
            profileContent: options.profileContentId || 'profile-content',
            profileName: options.profileNameId || 'profile-name',
            profileEmail: options.profileEmailId || 'profile-email',
            nickname: options.nicknameId || 'nickname',
            bio: options.bioId || 'bio',
            bioCounter: options.bioCounterId || 'bio-counter',
            profileAvatar: options.profileAvatarId || 'profile-avatar',
            profileUrl: options.profileUrlId || 'profile-url',
            profileUrlText: options.profileUrlTextId || 'profile-url-text',
            profileLinkGroup: options.profileLinkGroupId || 'profile-link-group',
            alertContainer: options.alertContainerId || 'alert-container',
            mutedCount: options.mutedCountId || 'muted-count',
            mutedUsersList: options.mutedUsersListId || 'muted-users-list'
        };
    }

    /**
     * Load profile data from API
     * @returns {Promise<Object>} Profile data
     */
    async loadProfile() {
        try {
            const response = await fetch(`${this.apiBase}/profile`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load profile');
            }

            this.currentProfile = await response.json();
            this.displayProfile(this.currentProfile);
            return this.currentProfile;

        } catch (error) {
            console.error('Error loading profile:', error);
            this.showAlert('Failed to load profile. Please try again.', 'error');
            throw error;
        } finally {
            const loading = document.getElementById(this.elements.loading);
            const content = document.getElementById(this.elements.profileContent);
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        }
    }

    /**
     * Display profile data in the UI
     * @param {Object} profile - Profile data
     */
    displayProfile(profile) {
        const nameEl = document.getElementById(this.elements.profileName);
        const emailEl = document.getElementById(this.elements.profileEmail);
        const nicknameEl = document.getElementById(this.elements.nickname);
        const bioEl = document.getElementById(this.elements.bio);
        const bioCounterEl = document.getElementById(this.elements.bioCounter);
        const avatarEl = document.getElementById(this.elements.profileAvatar);
        const urlEl = document.getElementById(this.elements.profileUrl);
        const urlTextEl = document.getElementById(this.elements.profileUrlText);
        const linkGroupEl = document.getElementById(this.elements.profileLinkGroup);
        const rssLinkEl = document.getElementById('profileRssLink');

        if (nameEl) nameEl.textContent = profile.name || 'User';
        if (emailEl) emailEl.textContent = profile.email;
        if (nicknameEl) nicknameEl.value = profile.nickname || '';
        
        // Set bio
        if (bioEl) {
            bioEl.value = profile.bio || '';
            this.updateBioCounter();
        }

        // Set avatar
        if (avatarEl) {
            const avatarUrl = profile.profile_image_url ||
                profile.photo_url || 
                `https://www.gravatar.com/avatar/${profile.gravatar_hash}?s=160&d=mp`;
            avatarEl.src = avatarUrl;
        }

        // Show profile link if nickname exists
        if (profile.nickname && urlEl && urlTextEl && linkGroupEl) {
            const profileUrl = `${window.location.origin}/@${profile.nickname}`;
            urlEl.href = profileUrl;
            urlTextEl.textContent = `@${profile.nickname}`;
            linkGroupEl.style.display = 'block';
        }

        // Show RSS link if nickname exists
        if (profile.nickname && rssLinkEl) {
            const rssUrl = `/api/users/@${profile.nickname}/rss`;
            rssLinkEl.href = rssUrl;
            rssLinkEl.style.display = '';
        } else if (rssLinkEl) {
            rssLinkEl.style.display = 'none';
        }
    }

    /**
     * Update profile with new data
     * @param {Object} data - Profile update data
     * @returns {Promise<Object>} Updated profile data
     */
    async updateProfile(data) {
        try {
            const response = await fetch(`${this.apiBase}/profile`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Failed to update profile');
            }

            this.currentProfile = result;
            this.displayProfile(result);
            this.showAlert('Profile updated successfully!', 'success');
            return result;

        } catch (error) {
            console.error('Error updating profile:', error);
            this.showAlert(error.message || 'Failed to update profile. Please try again.', 'error');
            throw error;
        }
    }

    /**
     * Update bio character counter
     */
    updateBioCounter() {
        const bioEl = document.getElementById(this.elements.bio);
        const counterEl = document.getElementById(this.elements.bioCounter);
        
        if (bioEl && counterEl) {
            const length = bioEl.value.length;
            counterEl.textContent = length;
            
            // Change color if approaching limit
            if (length > 140) {
                counterEl.style.color = 'var(--error)';
            } else if (length > 120) {
                counterEl.style.color = 'var(--warning, orange)';
            } else {
                counterEl.style.color = '';
            }
        }
    }

    /**
     * Validate nickname format
     * @param {string} nickname - Nickname to validate
     * @returns {Object} Validation result with valid flag and error message
     */
    validateNickname(nickname) {
        if (!/^[a-zA-Z0-9_-]{3,50}$/.test(nickname)) {
            return {
                valid: false,
                error: 'Invalid nickname format. Use 3-50 characters (letters, numbers, underscore, hyphen only)'
            };
        }
        return { valid: true };
    }

    /**
     * Load muted users list
     * @returns {Promise<Array>} Array of muted users
     */
    async loadMutedUsers() {
        try {
            const response = await fetch(`${this.apiBase}/filters`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load muted users');
            }

            const data = await response.json();
            const mutedUserIds = data.muted_users || [];

            // Update count
            const countEl = document.getElementById(this.elements.mutedCount);
            if (countEl) countEl.textContent = mutedUserIds.length;

            if (mutedUserIds.length === 0) {
                this.displayEmptyMutedState();
                return [];
            }

            // Fetch user details for each muted user
            const userPromises = mutedUserIds.map(userId => 
                fetch(`${this.apiBase}/users/${userId}/info`, { credentials: 'same-origin' })
                    .then(r => r.ok ? r.json() : null)
                    .catch(() => null)
            );

            const users = await Promise.all(userPromises);
            this.mutedUsersData = users.filter(u => u !== null).map((user, index) => ({
                ...user,
                id: mutedUserIds[index]
            }));

            this.displayMutedUsers(this.mutedUsersData);
            return this.mutedUsersData;

        } catch (error) {
            console.error('Error loading muted users:', error);
            const listEl = document.getElementById(this.elements.mutedUsersList);
            if (listEl) {
                listEl.innerHTML = `
                    <div class="empty-muted">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <p>Failed to load muted users</p>
                    </div>
                `;
            }
            throw error;
        }
    }

    /**
     * Display empty muted users state
     */
    displayEmptyMutedState() {
        const listEl = document.getElementById(this.elements.mutedUsersList);
        if (!listEl) return;

        listEl.innerHTML = `
            <div class="empty-muted">
                <i class="fa-solid fa-volume-xmark"></i>
                <p>No muted users</p>
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">You haven't muted anyone yet.</p>
            </div>
        `;
    }

    /**
     * Display muted users list
     * @param {Array} users - Array of muted users
     */
    displayMutedUsers(users) {
        const container = document.getElementById(this.elements.mutedUsersList);
        if (!container) return;

        if (users.length === 0) {
            this.displayEmptyMutedState();
            return;
        }

        container.innerHTML = users.map(user => {
            const avatarUrl = user.photo_url || user.avatar_url || 
                `https://www.gravatar.com/avatar/${user.gravatar_hash || '00000000000000000000000000000000'}?s=96&d=mp`;
            const displayName = user.nickname || user.name || 'Unknown User';

            return `
                <div class="muted-user-item" data-user-id="${user.id}">
                    <div class="muted-user-info">
                        <img src="${avatarUrl}" alt="${escapeHtml(displayName)}" class="muted-user-avatar" loading="lazy">
                        <div class="muted-user-details">
                            <span class="muted-user-name">${escapeHtml(displayName)}</span>
                            ${user.nickname ? `<span class="muted-user-date">@${escapeHtml(user.nickname)}</span>` : ''}
                        </div>
                    </div>
                    <button class="btn-unmute" data-user-id="${user.id}">
                        <i class="fa-solid fa-volume-high"></i>
                        <span>Unmute</span>
                    </button>
                </div>
            `;
        }).join('');

        // Add event listeners to unmute buttons
        container.querySelectorAll('.btn-unmute').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.userId, 10);
                this.unmuteUser(userId);
            });
        });
    }

    /**
     * Unmute a user
     * @param {number} userId - User ID to unmute
     * @returns {Promise<void>}
     */
    async unmuteUser(userId) {
        try {
            const response = await fetch(`${this.apiBase}/users/${userId}/mute`, {
                method: 'DELETE',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to unmute user');
            }

            // Show success message
            if (typeof showSnackbar === 'function') {
                showSnackbar('User unmuted successfully', 'success');
            }

            // Remove from list with animation
            const item = document.querySelector(`.muted-user-item[data-user-id="${userId}"]`);
            if (item) {
                item.style.transition = 'opacity 0.3s, transform 0.3s';
                item.style.opacity = '0';
                item.style.transform = 'scale(0.95)';

                setTimeout(() => {
                    item.remove();

                    // Update data and count
                    this.mutedUsersData = this.mutedUsersData.filter(u => u.id !== userId);
                    const countEl = document.getElementById(this.elements.mutedCount);
                    if (countEl) countEl.textContent = this.mutedUsersData.length;

                    // Show empty state if no more muted users
                    if (this.mutedUsersData.length === 0) {
                        this.displayEmptyMutedState();
                    }
                }, 300);
            }

        } catch (error) {
            console.error('Error unmuting user:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar('Failed to unmute user. Please try again.', 'error');
            }
            throw error;
        }
    }

    /**
     * Show alert message
     * @param {string} message - Alert message
     * @param {string} type - Alert type: 'success' or 'error'
     */
    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById(this.elements.alertContainer);
        if (!alertContainer) return;

        alertContainer.innerHTML = `
            <div class="alert alert-${type}">
                ${escapeHtml(message)}
            </div>
        `;

        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
    }

    /**
     * Get current profile data
     * @returns {Object|null} Current profile data
     */
    getCurrentProfile() {
        return this.currentProfile;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ProfileManager };
}
