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
        } catch (error) {
            console.error('Failed to initialize profile:', error);
            this.showError('Failed to load profile. Please try again.');
        }
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
            
            console.log('Profile loaded:', {
                isLoggedIn: this.sessionState.isLoggedIn,
                sessionUserId: this.sessionState.userId,
                profileUserId: this.profileData.id,
                isOwner: this.isOwner
            });
            
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
        
        // Set header image
        const headerEl = document.getElementById(this.elements.profileHeaderImage);
        if (headerEl) {
            if (profile.header_image_url) {
                headerEl.style.backgroundImage = `url('${profile.header_image_url}')`;
            } else {
                headerEl.style.backgroundImage = '';
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
            console.log('Added owner class to header overlay');
        }
        if (avatarOverlay) {
            avatarOverlay.classList.add('owner');
            console.log('Added owner class to avatar overlay');
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
            console.log('✓ Header container found - setting up click listener');
            headerContainer.addEventListener('click', (e) => {
                console.log('🎯 Header container clicked!');
                e.stopPropagation();
                e.preventDefault();
                this.triggerHeaderImageUpload();
            });
            console.log('✓ Header click listener attached');
        } else {
            console.error('✗ Header container element not found');
        }

        // Avatar image upload - click on the avatar image itself
        const avatarImg = document.getElementById(this.elements.profileAvatar);
        if (avatarImg) {
            console.log('✓ Avatar image found - setting up click listener');
            avatarImg.addEventListener('click', (e) => {
                console.log('🎯 Avatar image clicked!');
                e.stopPropagation();
                e.preventDefault();
                this.triggerAvatarImageUpload();
            });
            console.log('✓ Avatar click listener attached');
        } else {
            console.error('✗ Avatar image element not found');
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
