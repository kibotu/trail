/**
 * Error Page Initialization
 * 
 * Handles initialization for error pages including:
 * - Optional post composer
 * - Celebration confetti
 * - Character counter
 */

(function() {
    'use strict';

    // Get session state from body attributes
    const body = document.body;
    const sessionState = {
        isLoggedIn: body.dataset.isLoggedIn === 'true',
        userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null,
        userEmail: body.dataset.userEmail || null,
        isAdmin: body.dataset.isAdmin === 'true'
    };

    // Initialize entries manager if logged in
    if (sessionState.isLoggedIn) {
        const entriesManager = new EntriesManager({ sessionState });
        
        const postText = document.getElementById('postBox');
        const charCounter = document.getElementById('count');
        const submitButton = document.getElementById('post');
        const postForm = document.getElementById('postForm');

        if (postText && charCounter && submitButton && postForm) {
            // Setup character counter
            setupCharacterCounter({
                textarea: postText,
                counter: charCounter,
                submitButton: submitButton
            }, 280);

            // Handle form submission
            postForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const text = postText.value.trim();
                
                if (!text || text.length > 280) {
                    return;
                }

                setButtonLoading(submitButton, true, 'Posting...');

                try {
                    await entriesManager.createEntry(text, []);
                    
                    // Celebrate!
                    celebratePost('post');
                    
                    // Redirect to home
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1500);

                } catch (error) {
                    console.error('Error creating post:', error);
                    alert(`Failed to create post: ${error.message}`);
                } finally {
                    setButtonLoading(submitButton, false);
                }
            });
        }
    }

    // Page load confetti celebration
    createPageLoadConfetti('.error-box');

    // Home button handler
    const homeBtn = document.getElementById('home');
    if (homeBtn) {
        homeBtn.addEventListener('click', () => {
            window.location.href = '/';
        });
    }
})();
