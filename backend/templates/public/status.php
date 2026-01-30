<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Dynamic Open Graph meta tags will be set by JavaScript -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Trail">
    <meta name="twitter:card" content="summary">
    
    <title>Entry - Trail</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-status">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <button class="back-button" onclick="window.history.back()" aria-label="Go back">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <span class="logo">Trail</span>
        </div>
    </header>

    <main>
        <div id="entry-container" class="entry-container">
            <div class="loading">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Loading entry...</p>
            </div>
        </div>
    </main>

    <!-- Core JavaScript Modules -->
    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script src="/js/ui-interactions.js"></script>
    <script src="/js/entries-manager.js"></script>
    <script src="/js/meta-updater.js"></script>
    
    <script>
        const hashId = <?php echo json_encode($hashId ?? ''); ?>;
        const sessionState = {
            isLoggedIn: <?php echo json_encode($isLoggedIn ?? false) ?>,
            userId: null,
            userEmail: null,
            isAdmin: false
        };

        let currentEntry = null;

        // Initialize entries manager
        const entriesManager = new EntriesManager({ sessionState });

        // Setup menu close handler
        setupMenuCloseHandler();

        // Load entry
        async function loadEntry() {
            const container = document.getElementById('entry-container');
            
            try {
                const entry = await entriesManager.loadSingleEntry(hashId);
                currentEntry = entry;
                
                // Update meta tags
                updateMetaTagsFromEntry(entry);
                
                // Check permissions
                let canModify = false;
                if (sessionState.isLoggedIn) {
                    try {
                        const profileResponse = await fetch('/api/profile', {
                            credentials: 'same-origin'
                        });
                        
                        if (profileResponse.ok) {
                            const profile = await profileResponse.json();
                            sessionState.userId = profile.id;
                            sessionState.userEmail = profile.email;
                            sessionState.isAdmin = profile.is_admin === true;
                            canModify = profile.id === entry.user_id || profile.is_admin === true;
                        }
                    } catch (e) {
                        console.error('Failed to check permissions:', e);
                    }
                }
                
                // Render entry
                container.innerHTML = '';
                const card = createEntryCard(entry, {
                    canModify: canModify,
                    enablePermalink: false,
                    isLoggedIn: sessionState.isLoggedIn,
                    currentUserId: sessionState.userId
                });
                
                // Store entry data in card for edit functionality
                if (entry.images && Array.isArray(entry.images)) {
                    card.dataset.imageIds = JSON.stringify(entry.images.map(img => img.id));
                }
                
                container.appendChild(card);
                
            } catch (error) {
                console.error('Error loading entry:', error);
                if (error.message === 'Entry not found') {
                    container.innerHTML = '<div class="error-message">Entry not found</div>';
                } else {
                    container.innerHTML = '<div class="error-message">Failed to load entry</div>';
                }
            }
        }

        // Override edit/delete to use currentEntry for API calls
        window.editEntry = function(entryId) {
            entriesManager.editEntry(entryId);
        };

        window.deleteEntry = async function(entryId) {
            await entriesManager.deleteEntry(currentEntry ? currentEntry.id : entryId, {
                redirectOnSuccess: true,
                redirectUrl: '/'
            });
        };

        window.cancelEdit = function(entryId) {
            entriesManager.cancelEdit(entryId);
        };

        window.saveEdit = async function(entryId) {
            // Custom save that uses currentEntry ID
            const textarea = document.getElementById(`edit-text-${entryId}`);
            const newText = textarea.value.trim();

            if (!newText) {
                showSnackbar('Entry text cannot be empty', 'error');
                return;
            }

            const saveButton = document.querySelector('.save-button');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Saving...</span>';
            }

            try {
                const apiEntryId = currentEntry ? currentEntry.id : entryId;
                const payload = { text: newText };
                
                // Preserve images
                if (currentEntry) {
                    let imageIds = null;
                    if (currentEntry.image_ids) {
                        imageIds = typeof currentEntry.image_ids === 'string' 
                            ? JSON.parse(currentEntry.image_ids) 
                            : currentEntry.image_ids;
                    } else if (currentEntry.images && Array.isArray(currentEntry.images)) {
                        imageIds = currentEntry.images.map(img => img.id);
                    }
                    if (imageIds && Array.isArray(imageIds) && imageIds.length > 0) {
                        payload.image_ids = imageIds;
                    }
                }
                
                const response = await fetch(`/api/entries/${apiEntryId}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to update entry');
                }

                showSnackbar('Entry updated successfully', 'success');
                setTimeout(() => window.location.reload(), 500);

            } catch (error) {
                console.error('Error updating entry:', error);
                showSnackbar(`Failed to update entry: ${error.message}`, 'error');
                
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i><span>Save</span>';
                }
            }
        };

        // Load entry on page load
        loadEntry();
    </script>
</body>
</html>
