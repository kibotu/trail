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

    // Load profile and setup form
    profileManager.loadProfile().then(() => {
        // Setup auto-save tracking after profile is loaded
        profileManager.setupDirtyTracking();
    });

    // Load muted users
    window.addEventListener('DOMContentLoaded', () => {
        profileManager.loadMutedUsers();
    });
})();
