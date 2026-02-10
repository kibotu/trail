/**
 * User Profile Manager - Public profile display and editing
 * 
 * Handles loading and displaying public user profiles with Twitter-like design.
 * Supports inline editing for profile owner (bio, avatar, header image).
 */

class UserProfileManager {
    constructor(options = {}) {
        this.nickname = options.nickname;
        this.sessionState = options.sessionState || {};
        this.apiBase = options.apiBase || '/api';
        this.profileData = null;
        this.isOwner = false;
        this.bioOriginalContent = '';
        
        // Image uploaders
        this.profileImageUploader = null;
        this.headerImageUploader = null;
        
        // Shader background
        this.shaderBackground = null;
        this.shaderCanvas = null;
        
        // Element IDs
        this.elements = {
            profileHeaderImage: 'profileHeaderImage',
            headerUploadOverlay: 'headerUploadOverlay',
            profileAvatar: 'profileAvatar',
            avatarUploadOverlay: 'avatarUploadOverlay',
            profileName: 'profileName',
            profileNickname: 'profileNickname',
            profileBio: 'profileBio',
            profileJoined: 'profileJoined',
            profileBannerContainer: 'profileBannerContainer'
        };
    }

    /**
     * Initialize and load profile
     */
    async init() {
        try {
            await this.loadProfile();
            this.setupEventListeners();
            this.recordProfileView();
        } catch (error) {
            console.error('Failed to initialize profile:', error);
            this.showError('Failed to load profile. Please try again.');
        }
    }

    /**
     * Record a profile view (fire-and-forget)
     */
    recordProfileView() {
        fetch(`${this.apiBase}/users/${this.nickname}/views`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fingerprint: this.getBrowserFingerprint() })
        })
        .then(res => res.json())
        .then(data => {
            // Update the view count in the UI if it exists
            const viewCountEl = document.querySelector('#statProfileViews .profile-stat-value');
            if (viewCountEl && data.view_count !== undefined) {
                viewCountEl.textContent = this.formatNumber(data.view_count);
            }
        })
        .catch(() => {}); // Silent - views are best-effort
    }

    /**
     * Generate a lightweight browser fingerprint for view deduplication.
     * Differentiates devices behind the same IP without cross-site tracking.
     * Uses only stable properties that don't change during normal use.
     */
    getBrowserFingerprint() {
        // Use cached fingerprint if available from card-template.js
        if (typeof getBrowserFingerprint === 'function') {
            return getBrowserFingerprint();
        }
        // Fallback: generate inline (stable properties only)
        const components = [
            screen.width,
            screen.height,
            screen.colorDepth,
            navigator.language,
            navigator.hardwareConcurrency || 0,
            navigator.platform || ''
        ];
        return components.join('|');
    }

    /**
     * Load public profile data from API
     */
    async loadProfile() {
        try {
            const response = await fetch(`${this.apiBase}/users/${this.nickname}`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load profile');
            }

            this.profileData = await response.json();
            this.isOwner = this.sessionState.isLoggedIn && 
                          this.sessionState.userId === this.profileData.id;
            
            this.displayProfile();
            return this.profileData;

        } catch (error) {
            console.error('Error loading profile:', error);
            throw error;
        }
    }

    /**
     * Display profile data in the UI
     */
    displayProfile() {
        const profile = this.profileData;
        
        // Set header image or shader background
        const headerEl = document.getElementById(this.elements.profileHeaderImage);
        if (headerEl) {
            if (profile.header_image_url) {
                // User has a custom header image - destroy shader if exists
                this.destroyShaderBackground();
                headerEl.style.backgroundImage = `url('${profile.header_image_url}')`;
            } else {
                // No header image - use shader background
                headerEl.style.backgroundImage = '';
                this.initShaderBackground();
            }
        }

        // Set avatar
        const avatarEl = document.getElementById(this.elements.profileAvatar);
        if (avatarEl) {
            const avatarUrl = profile.profile_image_url || 
                            profile.photo_url || 
                            `https://www.gravatar.com/avatar/${profile.gravatar_hash}?s=320&d=mp`;
            avatarEl.src = avatarUrl;
        }

        // Set name
        const nameEl = document.getElementById(this.elements.profileName);
        if (nameEl) {
            nameEl.textContent = profile.name || `@${profile.nickname}`;
        }

        // Set nickname
        const nicknameEl = document.getElementById(this.elements.profileNickname);
        if (nicknameEl) {
            nicknameEl.textContent = `@${profile.nickname}`;
        }

        // Set bio
        const bioEl = document.getElementById(this.elements.profileBio);
        if (bioEl) {
            bioEl.textContent = profile.bio || '';
            if (this.isOwner) {
                bioEl.setAttribute('contenteditable', 'true');
                bioEl.setAttribute('placeholder', 'Add a bio (max 160 characters)');
                if (!profile.bio) {
                    bioEl.classList.add('empty');
                }
            }
        }

        // Set joined date
        const joinedEl = document.getElementById(this.elements.profileJoined);
        if (joinedEl && profile.created_at) {
            const joinedDate = this.formatJoinedDate(profile.created_at);
            joinedEl.innerHTML = `<i class="fa-solid fa-calendar"></i> ${joinedDate}`;
        }

        // Set statistics
        if (profile.stats) {
            this.displayStats(profile.stats);
        }

        // Show/hide edit overlays
        if (this.isOwner) {
            this.showEditControls();
        } else {
            this.hideEditControls();
        }

        // Show profile container
        const containerEl = document.getElementById(this.elements.profileBannerContainer);
        if (containerEl) {
            containerEl.style.display = 'block';
        }
    }

    /**
     * Format joined date (e.g., "Joined January 2026")
     */
    formatJoinedDate(dateString) {
        const date = new Date(dateString);
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const month = months[date.getMonth()];
        const year = date.getFullYear();
        return `Joined ${month} ${year}`;
    }

    /**
     * Show edit controls for profile owner
     */
    showEditControls() {
        const headerOverlay = document.getElementById(this.elements.headerUploadOverlay);
        const avatarOverlay = document.getElementById(this.elements.avatarUploadOverlay);
        const headerContainer = document.getElementById(this.elements.profileHeaderImage);
        const avatarImg = document.getElementById(this.elements.profileAvatar);
        
        // Add owner class to overlays for visual feedback
        if (headerOverlay) {
            headerOverlay.classList.add('owner');
        }
        if (avatarOverlay) {
            avatarOverlay.classList.add('owner');
        }
        
        // Add clickable class to containers
        if (headerContainer) {
            headerContainer.classList.add('clickable');
        }
        if (avatarImg) {
            avatarImg.classList.add('clickable');
        }
    }

    /**
     * Hide edit controls for non-owners
     */
    hideEditControls() {
        const headerOverlay = document.getElementById(this.elements.headerUploadOverlay);
        const avatarOverlay = document.getElementById(this.elements.avatarUploadOverlay);
        const headerContainer = document.getElementById(this.elements.profileHeaderImage);
        const avatarImg = document.getElementById(this.elements.profileAvatar);
        
        // Remove owner class from overlays
        if (headerOverlay) headerOverlay.classList.remove('owner');
        if (avatarOverlay) avatarOverlay.classList.remove('owner');
        
        // Remove clickable class from containers
        if (headerContainer) headerContainer.classList.remove('clickable');
        if (avatarImg) avatarImg.classList.remove('clickable');
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        if (!this.isOwner) return;

        // Bio editing
        const bioEl = document.getElementById(this.elements.profileBio);
        if (bioEl) {
            bioEl.addEventListener('focus', () => this.onBioFocus());
            bioEl.addEventListener('blur', () => this.onBioBlur());
            bioEl.addEventListener('keydown', (e) => this.onBioKeydown(e));
            bioEl.addEventListener('input', () => this.onBioInput());
        }

        // Header image upload - click on the header container itself
        const headerContainer = document.getElementById(this.elements.profileHeaderImage);
        if (headerContainer) {
            headerContainer.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                this.triggerHeaderImageUpload();
            });
        }

        // Avatar image upload - click on the avatar image itself
        const avatarImg = document.getElementById(this.elements.profileAvatar);
        if (avatarImg) {
            avatarImg.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                this.triggerAvatarImageUpload();
            });
        }
    }

    /**
     * Bio focus handler
     */
    onBioFocus() {
        const bioEl = document.getElementById(this.elements.profileBio);
        if (bioEl) {
            this.bioOriginalContent = bioEl.textContent;
            bioEl.classList.remove('empty');
        }
    }

    /**
     * Bio blur handler - save changes
     */
    async onBioBlur() {
        const bioEl = document.getElementById(this.elements.profileBio);
        if (!bioEl) return;

        const newBio = bioEl.textContent.trim();
        
        // Check if bio is empty
        if (newBio === '') {
            bioEl.classList.add('empty');
        }

        // Only save if changed
        if (newBio !== this.bioOriginalContent) {
            await this.saveBio(newBio);
        }
    }

    /**
     * Bio keydown handler
     */
    onBioKeydown(e) {
        // Enter key - save and blur
        if (e.key === 'Enter') {
            e.preventDefault();
            const bioEl = document.getElementById(this.elements.profileBio);
            if (bioEl) bioEl.blur();
        }
        
        // Escape key - cancel and restore
        if (e.key === 'Escape') {
            e.preventDefault();
            const bioEl = document.getElementById(this.elements.profileBio);
            if (bioEl) {
                bioEl.textContent = this.bioOriginalContent;
                bioEl.blur();
            }
        }
    }

    /**
     * Bio input handler - enforce character limit
     */
    onBioInput() {
        const bioEl = document.getElementById(this.elements.profileBio);
        if (!bioEl) return;

        const text = bioEl.textContent;
        if (text.length > 160) {
            // Truncate to 160 characters
            bioEl.textContent = text.substring(0, 160);
            
            // Move cursor to end
            const range = document.createRange();
            const sel = window.getSelection();
            range.selectNodeContents(bioEl);
            range.collapse(false);
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    /**
     * Save bio to server
     */
    async saveBio(bio) {
        try {
            const response = await fetch(`${this.apiBase}/profile`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nickname: this.profileData.nickname,
                    bio: bio
                })
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to update bio');
            }

            const result = await response.json();
            this.profileData.bio = result.bio;
            this.bioOriginalContent = bio;

            if (typeof showSnackbar === 'function') {
                showSnackbar('Bio updated successfully', 'success');
            }

        } catch (error) {
            console.error('Error saving bio:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar(error.message || 'Failed to update bio', 'error');
            }
            
            // Restore original content
            const bioEl = document.getElementById(this.elements.profileBio);
            if (bioEl) {
                bioEl.textContent = this.bioOriginalContent;
            }
        }
    }

    /**
     * Trigger header image upload
     */
    triggerHeaderImageUpload() {
        // Check if ImageUploader is available
        if (typeof ImageUploader === 'undefined') {
            console.error('ImageUploader class is not available');
            if (typeof showSnackbar === 'function') {
                showSnackbar('Image upload feature is not available. Please refresh the page.', 'error');
            }
            return;
        }
        
        // Create hidden file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif';
        fileInput.style.display = 'none';
        
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            try {
                // Show loading state
                const headerOverlay = document.getElementById(this.elements.headerUploadOverlay);
                if (headerOverlay) {
                    headerOverlay.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Uploading...</span>';
                }
                
                // Create uploader and upload
                const uploader = new ImageUploader(
                    'header',
                    (progress) => this.onUploadProgress('header', progress),
                    (result) => this.onHeaderImageUploaded(result),
                    (error) => {
                        console.error('Upload error:', error);
                        if (typeof showSnackbar === 'function') {
                            showSnackbar(error, 'error');
                        }
                        // Restore overlay
                        if (headerOverlay) {
                            headerOverlay.innerHTML = '<i class="fa-solid fa-camera"></i><span>Change header</span>';
                        }
                    }
                );
                
                await uploader.upload(file);
                
            } catch (error) {
                console.error('Upload failed:', error);
            } finally {
                // Clean up - check if element exists in DOM
                if (fileInput && fileInput.parentNode) {
                    fileInput.parentNode.removeChild(fileInput);
                }
            }
        });
        
        // Trigger file picker
        document.body.appendChild(fileInput);
        fileInput.click();
    }

    /**
     * Trigger avatar image upload
     */
    triggerAvatarImageUpload() {
        // Check if ImageUploader is available
        if (typeof ImageUploader === 'undefined') {
            console.error('ImageUploader class is not available');
            if (typeof showSnackbar === 'function') {
                showSnackbar('Image upload feature is not available. Please refresh the page.', 'error');
            }
            return;
        }
        
        // Create hidden file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif';
        fileInput.style.display = 'none';
        
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            try {
                // Show loading state
                const avatarOverlay = document.getElementById(this.elements.avatarUploadOverlay);
                if (avatarOverlay) {
                    avatarOverlay.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                }
                
                // Create uploader and upload
                const uploader = new ImageUploader(
                    'profile',
                    (progress) => this.onUploadProgress('profile', progress),
                    (result) => this.onProfileImageUploaded(result),
                    (error) => {
                        console.error('Upload error:', error);
                        if (typeof showSnackbar === 'function') {
                            showSnackbar(error, 'error');
                        }
                        // Restore overlay
                        if (avatarOverlay) {
                            avatarOverlay.innerHTML = '<i class="fa-solid fa-camera"></i>';
                        }
                    }
                );
                
                await uploader.upload(file);
                
            } catch (error) {
                console.error('Upload failed:', error);
            } finally {
                // Clean up - check if element exists in DOM
                if (fileInput && fileInput.parentNode) {
                    fileInput.parentNode.removeChild(fileInput);
                }
            }
        });
        
        // Trigger file picker
        document.body.appendChild(fileInput);
        fileInput.click();
    }

    /**
     * Handle header image upload completion
     */
    async onHeaderImageUploaded(result) {
        try {
            const response = await fetch(`${this.apiBase}/profile`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nickname: this.profileData.nickname,
                    header_image_id: result.image_id
                })
            });

            if (!response.ok) {
                throw new Error('Failed to update header image');
            }

            const data = await response.json();
            this.profileData.header_image_url = data.header_image_url;
            
            // Destroy shader background since we now have a custom image
            this.destroyShaderBackground();
            
            // Update display
            const headerEl = document.getElementById(this.elements.profileHeaderImage);
            if (headerEl && data.header_image_url) {
                headerEl.style.backgroundImage = `url('${data.header_image_url}')`;
            }
            
            // Restore overlay
            const headerOverlay = document.getElementById(this.elements.headerUploadOverlay);
            if (headerOverlay) {
                headerOverlay.innerHTML = '<i class="fa-solid fa-camera"></i><span>Change header</span>';
            }

            if (typeof showSnackbar === 'function') {
                showSnackbar('Header image updated successfully', 'success');
            }

        } catch (error) {
            console.error('Error updating header image:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar('Failed to update header image', 'error');
            }
            
            // Restore overlay
            const headerOverlay = document.getElementById(this.elements.headerUploadOverlay);
            if (headerOverlay) {
                headerOverlay.innerHTML = '<i class="fa-solid fa-camera"></i><span>Change header</span>';
            }
        }
    }

    /**
     * Handle profile image upload completion
     */
    async onProfileImageUploaded(result) {
        try {
            const response = await fetch(`${this.apiBase}/profile`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nickname: this.profileData.nickname,
                    profile_image_id: result.image_id
                })
            });

            if (!response.ok) {
                throw new Error('Failed to update profile image');
            }

            const data = await response.json();
            this.profileData.profile_image_url = data.profile_image_url;
            
            // Update display
            const avatarEl = document.getElementById(this.elements.profileAvatar);
            if (avatarEl && data.profile_image_url) {
                avatarEl.src = data.profile_image_url;
            }
            
            // Restore overlay
            const avatarOverlay = document.getElementById(this.elements.avatarUploadOverlay);
            if (avatarOverlay) {
                avatarOverlay.innerHTML = '<i class="fa-solid fa-camera"></i>';
            }

            if (typeof showSnackbar === 'function') {
                showSnackbar('Profile image updated successfully', 'success');
            }

        } catch (error) {
            console.error('Error updating profile image:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar('Failed to update profile image', 'error');
            }
            
            // Restore overlay
            const avatarOverlay = document.getElementById(this.elements.avatarUploadOverlay);
            if (avatarOverlay) {
                avatarOverlay.innerHTML = '<i class="fa-solid fa-camera"></i>';
            }
        }
    }

    /**
     * Handle upload progress
     */
    onUploadProgress(type, progress) {
        const overlayId = type === 'header' ? this.elements.headerUploadOverlay : this.elements.avatarUploadOverlay;
        const overlay = document.getElementById(overlayId);
        
        if (overlay && progress < 100) {
            if (type === 'header') {
                overlay.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i><span>Uploading ${progress}%</span>`;
            } else {
                overlay.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
            }
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        if (typeof showSnackbar === 'function') {
            showSnackbar(message, 'error');
        } else {
            alert(message);
        }
    }

    /**
     * Initialize shader background
     */
    initShaderBackground() {
        // Check if shader function is available
        if (typeof initProteanCloudsShader === 'undefined') {
            console.warn('Protean Clouds shader not available');
            return;
        }

        // Destroy existing shader if any
        this.destroyShaderBackground();

        // Create canvas element
        const headerEl = document.getElementById(this.elements.profileHeaderImage);
        if (!headerEl) return;

        this.shaderCanvas = document.createElement('canvas');
        this.shaderCanvas.style.position = 'absolute';
        this.shaderCanvas.style.top = '0';
        this.shaderCanvas.style.left = '0';
        this.shaderCanvas.style.width = '100%';
        this.shaderCanvas.style.height = '100%';
        this.shaderCanvas.style.pointerEvents = 'none';
        
        headerEl.appendChild(this.shaderCanvas);

        // Initialize shader
        this.shaderBackground = initProteanCloudsShader(this.shaderCanvas);
    }

    /**
     * Destroy shader background
     */
    destroyShaderBackground() {
        if (this.shaderBackground) {
            this.shaderBackground.destroy();
            this.shaderBackground = null;
        }
        
        if (this.shaderCanvas && this.shaderCanvas.parentNode) {
            this.shaderCanvas.parentNode.removeChild(this.shaderCanvas);
            this.shaderCanvas = null;
        }
    }

    /**
     * Display profile statistics in the header card
     */
    displayStats(stats) {
        // -- Numeric counters --
        const statsContainer = document.getElementById('profileStats');
        if (statsContainer) {
            const setValue = (id, value) => {
                const el = document.getElementById(id);
                if (el) {
                    el.querySelector('.profile-stat-value').textContent = this.formatNumber(value);
                }
            };
            setValue('statEntries',  stats.entry_count  ?? 0);
            setValue('statComments', stats.comment_count ?? 0);
            setValue('statProfileViews', stats.total_profile_views ?? 0);
            statsContainer.style.display = '';
        }

        // -- Last seen (previous login) --
        if (stats.previous_login_at) {
            const lastSeenEl = document.getElementById('profileLastSeen');
            if (lastSeenEl) {
                const span = lastSeenEl.querySelector('span');
                span.textContent = `Last seen ${this.formatRelativeDate(stats.previous_login_at)}`;
                lastSeenEl.style.display = '';
            }
        }

        // -- Last entry --
        if (stats.last_entry_at) {
            const lastEntryEl = document.getElementById('profileLastEntry');
            if (lastEntryEl) {
                const span = lastEntryEl.querySelector('span');
                span.textContent = `Last post ${this.formatRelativeDate(stats.last_entry_at)}`;
                lastEntryEl.style.display = '';
            }
        }
    }

    /**
     * Format a number for display (e.g. 1234 -> "1,234", 12500 -> "12.5K")
     */
    formatNumber(n) {
        const num = Number(n) || 0;
        if (num >= 1_000_000) return (num / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (num >= 10_000)    return (num / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
        return num.toLocaleString();
    }

    /**
     * Format a date string into a human-friendly relative string
     * e.g. "2 hours ago", "3 days ago", "Jan 15, 2025"
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

        // Older than a month – show a short absolute date
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const month = months[date.getMonth()];
        const day   = date.getDate();
        const year  = date.getFullYear();
        if (year === now.getFullYear()) return `${month} ${day}`;
        return `${month} ${day}, ${year}`;
    }

    /**
     * Get current profile data
     */
    getProfileData() {
        return this.profileData;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UserProfileManager };
}
