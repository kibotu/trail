/**
 * Profile Page Initialization
 * 
 * Handles initialization for user profile settings page including:
 * - Profile loading and updates
 * - Image uploads
 * - Muted users management
 */

(function() {
    'use strict';

    // Initialize profile manager
    const profileManager = new ProfileManager({ apiBase: '/api' });

    // Load profile on page load
    profileManager.loadProfile();

    // Handle profile form submission
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nickname = document.getElementById('nickname').value.trim();
            const saveBtn = document.getElementById('save-btn');

            // Validate nickname
            const validation = profileManager.validateNickname(nickname);
            if (!validation.valid) {
                profileManager.showAlert(validation.error, 'error');
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }

            try {
                await profileManager.updateProfile({ nickname });
            } catch (error) {
                // Error already handled by profileManager
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                }
            }
        });
    }

    // Initialize image uploaders
    window.addEventListener('DOMContentLoaded', () => {
        // Profile image uploader
        const profileUploadContainer = document.getElementById('profile-image-upload');
        if (profileUploadContainer) {
            createImageUploadUI(
                'profile',
                'profile-image-upload',
                (result) => {
                    if (!result.removed) {
                        profileManager.updateProfileWithImage('profile', result.image_id);
                    }
                }
            );
        }
        
        // Header image uploader
        const headerUploadContainer = document.getElementById('header-image-upload');
        if (headerUploadContainer) {
            createImageUploadUI(
                'header',
                'header-image-upload',
                (result) => {
                    if (!result.removed) {
                        profileManager.updateProfileWithImage('header', result.image_id);
                    }
                }
            );
        }

        // Load muted users
        profileManager.loadMutedUsers();
    });
})();
