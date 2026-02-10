/**
 * Error Page Initialization
 * 
 * Handles initialization for error pages including:
 * - Optional post composer
 * - Celebration confetti
 * - Character counter
 */

(async function() {
    'use strict';

    // Load configuration first
    const config = await loadConfig();
    const maxTextLength = config.max_text_length || 140;

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
        const submitButton = document.getElementById('postBtn');

        if (postText && charCounter && submitButton) {
            // Set maxlength attribute dynamically
            postText.setAttribute('maxlength', maxTextLength);
            
            // Update initial counter display
            charCounter.textContent = maxTextLength;
            
            // Setup character counter
            setupCharacterCounter({
                textarea: postText,
                counter: charCounter,
                submitButton: submitButton
            }, maxTextLength);

            // Handle button click
            submitButton.addEventListener('click', async (e) => {
                e.preventDefault();
                
                const text = postText.value.trim();
                
                if (!text || text.length > maxTextLength) {
                    return;
                }

                setButtonLoading(submitButton, true, 'Posting...');

                try {
                    await entriesManager.createEntry(text, []);
                    
                    // Celebrate!
                    celebratePost(submitButton);
                    
                    // Redirect to home
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1500);

                } catch (error) {
                    console.error('Error creating post:', error);
                    if (typeof showSnackbar === 'function') {
                        showSnackbar(`Failed to create post: ${error.message}`, 'error');
                    } else {
                        alert(`Failed to create post: ${error.message}`);
                    }
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
})().catch(error => {
    console.error('Failed to initialize error page:', error);
});
