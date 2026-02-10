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
        this.initialValues = {};
        this.bioSaveTimeout = null;
        
        // Element IDs
        this.elements = {
            loading: options.loadingId || 'loading',
            profileContent: options.profileContentId || 'profile-content',
            nickname: options.nicknameId || 'nickname',
            bio: options.bioId || 'bio',
            bioCounter: options.bioCounterId || 'bio-counter',
            bioCounterFill: options.bioCounterFillId || 'bio-counter-fill',
            mutedCount: options.mutedCountId || 'muted-count',
            mutedUsersList: options.mutedUsersListId || 'muted-users-list',
            // Sidebar elements
            identityAvatar: 'identity-avatar',
            identityName: 'identity-name',
            identityNickname: 'identity-nickname',
            identityNicknameText: 'identity-nickname-text',
            identityEmail: 'identity-email',
            identityMemberSince: 'identity-member-since'
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
        // Populate sidebar
        this.displaySidebar(profile);
        
        // Populate form fields
        const nicknameEl = document.getElementById(this.elements.nickname);
        const bioEl = document.getElementById(this.elements.bio);
        const rssLinkEl = document.getElementById('profileRssLink');

        if (nicknameEl) nicknameEl.value = profile.nickname || '';
        
        // Set bio
        if (bioEl) {
            bioEl.value = profile.bio || '';
            this.updateBioCounter();
        }

        // Store initial values for dirty tracking
        this.initialValues = {
            nickname: profile.nickname || '',
            bio: profile.bio || ''
        };

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
     * Display profile data in the sidebar
     * @param {Object} profile - Profile data
     */
    displaySidebar(profile) {
        const avatarEl = document.getElementById(this.elements.identityAvatar);
        const nameEl = document.getElementById(this.elements.identityName);
        const nicknameEl = document.getElementById(this.elements.identityNickname);
        const nicknameTextEl = document.getElementById(this.elements.identityNicknameText);
        const emailEl = document.getElementById(this.elements.identityEmail);
        const memberSinceEl = document.getElementById(this.elements.identityMemberSince);
        const viewProfileEl = document.getElementById(this.elements.viewProfileLink);

        // Set avatar
        if (avatarEl) {
            const avatarUrl = profile.profile_image_url ||
                profile.photo_url || 
                `https://www.gravatar.com/avatar/${profile.gravatar_hash}?s=240&d=mp`;
            avatarEl.src = avatarUrl;
        }

        // Set name
        if (nameEl) nameEl.textContent = profile.name || 'User';

        // Set nickname badge
        if (profile.nickname && nicknameEl && nicknameTextEl) {
            const profileUrl = `${window.location.origin}/@${profile.nickname}`;
            nicknameEl.href = profileUrl;
            nicknameTextEl.textContent = profile.nickname;
            nicknameEl.style.display = 'inline-flex';
        }

        // Set email
        if (emailEl) emailEl.textContent = profile.email;

        // Set member since
        if (memberSinceEl && profile.created_at) {
            memberSinceEl.textContent = `Member since ${this.formatDate(profile.created_at)}`;
        }

        // Set statistics
        if (profile.stats) {
            this.displayStats(profile.stats);
        }
    }

    /**
     * Format date for display
     * @param {string} dateString - ISO date string
     * @returns {string} Formatted date
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'long' };
        return date.toLocaleDateString('en-US', options);
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
            
            // Only show alert for nickname changes (bio auto-saves silently)
            if (data.nickname && data.nickname !== this.initialValues.nickname) {
                this.showAlert('Nickname updated successfully!', 'success');
            }
            return result;

        } catch (error) {
            console.error('Error updating profile:', error);
            this.showAlert(error.message || 'Failed to update profile. Please try again.', 'error');
            throw error;
        }
    }

    /**
     * Update bio character counter and visual bar
     */
    updateBioCounter() {
        const bioEl = document.getElementById(this.elements.bio);
        const counterEl = document.getElementById(this.elements.bioCounter);
        const fillEl = document.getElementById(this.elements.bioCounterFill);
        
        if (bioEl && counterEl) {
            const length = bioEl.value.length;
            const percentage = (length / 160) * 100;
            
            counterEl.textContent = length;
            
            // Update visual bar
            if (fillEl) {
                fillEl.style.width = `${Math.min(100, percentage)}%`;
                
                // Change color based on usage
                fillEl.classList.remove('warning', 'error');
                if (length > 160) {
                    fillEl.classList.add('error');
                    counterEl.style.color = 'var(--error)';
                } else if (length > 140) {
                    fillEl.classList.add('warning');
                    counterEl.style.color = 'var(--warning)';
                } else {
                    counterEl.style.color = '';
                }
            }
        }
    }

    /**
     * Handle nickname change on blur
     */
    async handleNicknameBlur() {
        const nicknameEl = document.getElementById(this.elements.nickname);
        if (!nicknameEl) return;
        
        const currentNickname = nicknameEl.value.trim();
        
        // Don't save if nickname hasn't changed
        if (currentNickname === this.initialValues.nickname) return;
        
        // Validate nickname
        const validation = this.validateNickname(currentNickname);
        if (!validation.valid) {
            this.showAlert(validation.error, 'error');
            // Revert to original value
            nicknameEl.value = this.initialValues.nickname;
            return;
        }
        
        // Confirm nickname change
        const confirmed = confirm(
            `Change your nickname to "${currentNickname}"?\n\n` +
            'This will update your profile URL and how others see you. ' +
            'Are you sure you want to continue?'
        );
        
        if (!confirmed) {
            // Revert to original value
            nicknameEl.value = this.initialValues.nickname;
            return;
        }
        
        // Disable input during save
        nicknameEl.disabled = true;
        
        try {
            const bioEl = document.getElementById(this.elements.bio);
            const currentBio = bioEl ? bioEl.value.trim() : '';
            
            await this.updateProfile({ 
                nickname: currentNickname, 
                bio: currentBio 
            });
            
            // Update initial value after successful save
            this.initialValues.nickname = currentNickname;
        } catch (error) {
            // Error already handled by updateProfile
            // Revert to original value on error
            nicknameEl.value = this.initialValues.nickname;
        } finally {
            nicknameEl.disabled = false;
        }
    }

    /**
     * Auto-save bio after user stops typing (debounced)
     */
    autoSaveBio() {
        const bioEl = document.getElementById(this.elements.bio);
        if (!bioEl) return;
        
        const currentBio = bioEl.value.trim();
        
        // Don't save if bio hasn't changed
        if (currentBio === this.initialValues.bio) return;
        
        // Clear existing timeout
        if (this.bioSaveTimeout) {
            clearTimeout(this.bioSaveTimeout);
        }
        
        // Set new timeout to save after 1 second of no typing
        this.bioSaveTimeout = setTimeout(async () => {
            try {
                await this.updateProfile({ 
                    nickname: this.initialValues.nickname, 
                    bio: currentBio 
                });
                // Update initial value after successful save
                this.initialValues.bio = currentBio;
            } catch (error) {
                // Error already handled by updateProfile
            }
        }, 1000);
    }

    /**
     * Setup form change listeners
     */
    setupDirtyTracking() {
        const nicknameEl = document.getElementById(this.elements.nickname);
        const bioEl = document.getElementById(this.elements.bio);
        
        if (nicknameEl) {
            nicknameEl.addEventListener('blur', () => this.handleNicknameBlur());
        }
        
        if (bioEl) {
            bioEl.addEventListener('input', () => {
                this.updateBioCounter();
                this.autoSaveBio();
            });
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
                            ${user.nickname ? `<span class="muted-user-nickname">@${escapeHtml(user.nickname)}</span>` : ''}
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
     * Show alert message using snackbar
     * @param {string} message - Alert message
     * @param {string} type - Alert type: 'success' or 'error'
     */
    showAlert(message, type = 'info') {
        if (typeof showSnackbar === 'function') {
            showSnackbar(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    /**
     * Display profile statistics in the identity card
     * @param {Object} stats - Stats object from API
     */
    displayStats(stats) {
        // Numeric counters
        const statsContainer = document.getElementById('identityStats');
        if (statsContainer) {
            const set = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = this.formatNumber(value);
            };
            set('identityStatEntries',  stats.entry_count  ?? 0);
            set('identityStatLinks',    stats.link_count    ?? 0);
            set('identityStatComments', stats.comment_count ?? 0);
            statsContainer.style.display = '';
        }

        // View stats container
        const viewStatsContainer = document.getElementById('identityViewStats');
        if (viewStatsContainer) {
            const set = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = this.formatNumber(value);
            };
            set('identityStatEntryViews',   stats.total_entry_views   ?? 0);
            set('identityStatCommentViews', stats.total_comment_views ?? 0);
            set('identityStatProfileViews', stats.total_profile_views ?? 0);
            viewStatsContainer.style.display = '';
        }

        // Meta section (last seen, last entry)
        const metaContainer = document.getElementById('identityMeta');
        let hasMetaItems = false;

        if (stats.previous_login_at) {
            const el = document.getElementById('identityLastSeen');
            if (el) {
                el.querySelector('span').textContent = `Last seen ${this.formatRelativeDate(stats.previous_login_at)}`;
                el.style.display = '';
                hasMetaItems = true;
            }
        }

        if (stats.last_entry_at) {
            const el = document.getElementById('identityLastEntry');
            if (el) {
                el.querySelector('span').textContent = `Last post ${this.formatRelativeDate(stats.last_entry_at)}`;
                el.style.display = '';
                hasMetaItems = true;
            }
        }

        if (metaContainer && hasMetaItems) {
            metaContainer.style.display = '';
        }
    }

    /**
     * Format a number for display (e.g. 1234 -> "1,234", 12500 -> "12.5K")
     * @param {number} n - Number to format
     * @returns {string} Formatted number
     */
    formatNumber(n) {
        const num = Number(n) || 0;
        if (num >= 1_000_000) return (num / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (num >= 10_000)    return (num / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
        return num.toLocaleString();
    }

    /**
     * Format a date string into a human-friendly relative string
     * @param {string} dateString - ISO date string
     * @returns {string} Relative date string
     */
    formatRelativeDate(dateString) {
        const date = new Date(dateString);
        const now  = new Date();
        const diffMs = now - date;
        const diffSec  = Math.floor(diffMs / 1000);
        const diffMin  = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay  = Math.floor(diffHour / 24);

        if (diffSec < 60)   return 'just now';
        if (diffMin < 60)   return `${diffMin}m ago`;
        if (diffHour < 24)  return `${diffHour}h ago`;
        if (diffDay < 7)    return `${diffDay}d ago`;
        if (diffDay < 30)   return `${Math.floor(diffDay / 7)}w ago`;

        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const month = months[date.getMonth()];
        const day   = date.getDate();
        const year  = date.getFullYear();
        if (year === now.getFullYear()) return `${month} ${day}`;
        return `${month} ${day}, ${year}`;
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
