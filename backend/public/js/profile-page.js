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
            const bio = document.getElementById('bio').value.trim();
            const saveBtn = document.getElementById('save-btn');

            // Validate nickname
            const validation = profileManager.validateNickname(nickname);
            if (!validation.valid) {
                profileManager.showAlert(validation.error, 'error');
                return;
            }

            // Validate bio length
            if (bio.length > 160) {
                profileManager.showAlert('Bio must be 160 characters or less', 'error');
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }

            try {
                await profileManager.updateProfile({ nickname, bio });
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

    // Setup bio character counter
    window.addEventListener('DOMContentLoaded', () => {
        const bioEl = document.getElementById('bio');
        if (bioEl) {
            bioEl.addEventListener('input', () => {
                profileManager.updateBioCounter();
            });
        }

        // Load muted users
        profileManager.loadMutedUsers();
    });
})();
