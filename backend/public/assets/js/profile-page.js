/**
 * Profile Page Initialization
 * 
 * Handles initialization for user profile settings page including:
 * - Profile loading and updates
 * - Form dirty-state tracking
 * - Muted users management
 */

(function() {
    'use strict';

    // Initialize profile manager
    const profileManager = new ProfileManager({ apiBase: '/api' });

    // Initialize account manager (data export + deletion)
    const accountManager = new AccountManager({ apiBase: '/api' });

    // Load profile and setup form
    profileManager.loadProfile().then(() => {
        // Setup auto-save tracking after profile is loaded
        profileManager.setupDirtyTracking();

        // Setup feedback section
        profileManager.setupFeedback();

        // Pass nickname to account manager for deletion confirmation
        const nicknameEl = document.getElementById('identity-nickname-text');
        if (nicknameEl && nicknameEl.textContent) {
            accountManager.setNickname(nicknameEl.textContent.trim());
        }
    });

    // Load muted users
    window.addEventListener('DOMContentLoaded', () => {
        profileManager.loadMutedUsers();
    });
})();
