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

    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script>
        const hashId = <?php echo json_encode($hashId ?? ''); ?>;
        const isLoggedIn = <?php echo json_encode($isLoggedIn ?? false); ?>;
        // JWT token is stored in httpOnly cookie - not accessible to JavaScript for security
        
        // Store current entry data globally for edit/delete operations
        let currentEntry = null;

        async function loadEntry() {
            const container = document.getElementById('entry-container');
            
            try {
                const response = await fetch(`/api/entries/${hashId}`);
                
                if (!response.ok) {
                    if (response.status === 404) {
                        container.innerHTML = '<div class="error-message">Entry not found</div>';
                    } else {
                        container.innerHTML = '<div class="error-message">Failed to load entry</div>';
                    }
                    return;
                }
                
                const entry = await response.json();
                
                // Store entry data globally
                currentEntry = entry;
                
                // Update page title and meta tags
                const displayName = entry.user_nickname || entry.user_name;
                const entryText = entry.text.substring(0, 100) + (entry.text.length > 100 ? '...' : '');
                
                document.title = `${displayName} on Trail: "${entryText}"`;
                
                // Update Open Graph meta tags
                updateMetaTag('og:title', `${displayName} on Trail`);
                updateMetaTag('og:description', entryText);
                updateMetaTag('og:url', window.location.href);
                
                if (entry.preview_image) {
                    updateMetaTag('og:image', entry.preview_image);
                }
                
                // Check if current user can modify this entry
                let canModify = false;
                if (isLoggedIn) {
                    try {
                        const profileResponse = await fetch('/api/profile', {
                            credentials: 'same-origin' // Include httpOnly cookie with JWT
                        });
                        
                        if (profileResponse.ok) {
                            const profile = await profileResponse.json();
                            canModify = profile.id === entry.user_id || profile.is_admin === true;
                        }
                    } catch (e) {
                        console.error('Failed to check permissions:', e);
                    }
                }
                
                // Render the entry card (without permalink on status page)
                container.innerHTML = '';
                const card = createEntryCard(entry, {
                    canModify: canModify,
                    enablePermalink: false,
                    isLoggedIn: isLoggedIn,
                    currentUserId: null
                });
                container.appendChild(card);
                
            } catch (error) {
                console.error('Error loading entry:', error);
                container.innerHTML = '<div class="error-message">Failed to load entry</div>';
            }
        }

        function updateMetaTag(property, content) {
            let meta = document.querySelector(`meta[property="${property}"]`);
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('property', property);
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', content);
        }

        // Toggle menu dropdown
        function toggleMenu(event, entryId) {
            event.stopPropagation();
            const menu = document.getElementById(`menu-${entryId}`);
            const allMenus = document.querySelectorAll('.menu-dropdown');
            
            // Close all other menus
            allMenus.forEach(m => {
                if (m !== menu) {
                    m.classList.remove('active');
                }
            });
            
            // Toggle current menu
            menu.classList.toggle('active');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.entry-menu')) {
                const allMenus = document.querySelectorAll('.menu-dropdown');
                allMenus.forEach(m => m.classList.remove('active'));
            }
        });

        // Edit entry
        async function editEntry(entryId) {
            // Close the menu
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) {
                console.error('Card not found for entry:', entryId);
                return;
            }

            const contentDiv = card.querySelector('.entry-content');
            const textDiv = card.querySelector('.entry-text');
            const currentText = textDiv ? textDiv.textContent : '';

            // Store the original HTML to restore it later
            const originalHtml = contentDiv.innerHTML;
            
            // Store original HTML in a data attribute for cancel
            contentDiv.dataset.originalHtml = originalHtml;

            // Get images and preview card HTML to show during editing
            const imagesDiv = contentDiv.querySelector('.entry-images');
            const imagesHtml = imagesDiv ? imagesDiv.outerHTML : '';
            
            const previewCard = contentDiv.querySelector('.link-preview-card, .link-preview-wrapper');
            const previewHtml = previewCard ? previewCard.outerHTML : '';

            // Add edit-textarea styles if not already present
            if (!document.getElementById('edit-textarea-styles')) {
                const style = document.createElement('style');
                style.id = 'edit-textarea-styles';
                style.textContent = `
                    .edit-textarea {
                        width: 100%;
                        min-height: 80px;
                        background: var(--bg-primary);
                        color: var(--text-primary);
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        padding: 0.75rem;
                        font-size: 1rem;
                        font-family: inherit;
                        line-height: 1.6;
                        resize: vertical;
                    }
                    .edit-textarea:focus {
                        outline: none;
                        border-color: var(--accent);
                    }
                    .action-button {
                        border: none;
                        padding: 0.5rem 1rem;
                        border-radius: 6px;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        font-size: 0.875rem;
                        font-weight: 500;
                        transition: all 0.2s;
                    }
                    .action-button:hover {
                        opacity: 0.9;
                        transform: translateY(-1px);
                    }
                    .cancel-button {
                        background: var(--bg-tertiary);
                        color: var(--text-primary);
                    }
                    .save-button {
                        background: var(--accent);
                        color: white;
                    }
                    .edit-form .entry-images,
                    .edit-form .link-preview-card,
                    .edit-form .link-preview-wrapper {
                        opacity: 0.7;
                        margin-top: 0.75rem;
                    }
                    .edit-form .entry-images img,
                    .edit-form .link-preview-card,
                    .edit-form .link-preview-wrapper {
                        pointer-events: none;
                    }
                `;
                document.head.appendChild(style);
            }

            // Create edit form with images and preview shown
            contentDiv.innerHTML = `
                <div class="edit-form">
                    <textarea class="edit-textarea" id="edit-text-${entryId}" maxlength="280">${escapeHtml(currentText)}</textarea>
                    ${imagesHtml}
                    ${previewHtml}
                    <div class="edit-actions" style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem;">
                        <button class="action-button cancel-button" data-entry-id="${entryId}">
                            <i class="fa-solid fa-xmark"></i>
                            <span>Cancel</span>
                        </button>
                        <button class="action-button save-button" data-entry-id="${entryId}">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save</span>
                        </button>
                    </div>
                </div>
            `;

            // Add event listeners to the buttons
            const cancelButton = contentDiv.querySelector('.cancel-button');
            const saveButton = contentDiv.querySelector('.save-button');
            
            cancelButton.addEventListener('click', () => cancelEdit(entryId));
            saveButton.addEventListener('click', () => saveEdit(entryId));

            // Prevent click-through to status page on edit form
            const editForm = contentDiv.querySelector('.edit-form');
            if (editForm) {
                editForm.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }

            // Focus textarea
            const textarea = document.getElementById(`edit-text-${entryId}`);
            if (textarea) {
                // Prevent click-through on textarea specifically
                textarea.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
        }

        // Cancel edit
        function cancelEdit(entryId) {
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const originalHtml = contentDiv.dataset.originalHtml;
            
            if (originalHtml) {
                contentDiv.innerHTML = originalHtml;
                delete contentDiv.dataset.originalHtml;
            }
        }

        // Save edit
        async function saveEdit(entryId) {
            const textarea = document.getElementById(`edit-text-${entryId}`);
            const newText = textarea.value.trim();

            if (!newText) {
                if (typeof showSnackbar === 'function') {
                    showSnackbar('Entry text cannot be empty', 'error');
                } else {
                    alert('Entry text cannot be empty');
                }
                return;
            }

            if (!isLoggedIn) {
                if (typeof showSnackbar === 'function') {
                    showSnackbar('You must be logged in to edit entries', 'error');
                } else {
                    alert('You must be logged in to edit entries');
                }
                return;
            }

            // Disable save button during request
            const saveButton = document.querySelector('.save-button');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Saving...</span>';
            }

            try {
                // Use the numeric entry ID from currentEntry, or fall back to entryId parameter
                const apiEntryId = currentEntry ? currentEntry.id : entryId;
                
                // Preserve existing image_ids from currentEntry
                const payload = { text: newText };
                
                // Check if entry has images and preserve them
                if (currentEntry) {
                    // Try to get image_ids from either image_ids field or images array
                    let imageIds = null;
                    
                    if (currentEntry.image_ids) {
                        // Parse image_ids if it's a JSON string, or use as-is if it's already an array
                        try {
                            imageIds = typeof currentEntry.image_ids === 'string' 
                                ? JSON.parse(currentEntry.image_ids) 
                                : currentEntry.image_ids;
                        } catch (e) {
                            console.warn('Failed to parse image_ids:', e);
                        }
                    } else if (currentEntry.images && Array.isArray(currentEntry.images)) {
                        // Extract IDs from images array
                        imageIds = currentEntry.images.map(img => img.id);
                    }
                    
                    if (imageIds && Array.isArray(imageIds) && imageIds.length > 0) {
                        payload.image_ids = imageIds;
                    }
                }
                
                const response = await fetch(`/api/entries/${apiEntryId}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to update entry');
                }

                const data = await response.json();

                // Show success message and reload
                if (typeof showSnackbar === 'function') {
                    showSnackbar('Entry updated successfully', 'success');
                }
                
                // Reload the page to show updated content
                setTimeout(() => {
                    window.location.reload();
                }, 500);

            } catch (error) {
                console.error('Error updating entry:', error);
                if (typeof showSnackbar === 'function') {
                    showSnackbar(`Failed to update entry: ${error.message}`, 'error');
                } else {
                    alert(`Failed to update entry: ${error.message}`);
                }
                
                // Re-enable save button on error
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i><span>Save</span>';
                }
            }
        }

        // Delete entry
        async function deleteEntry(entryId) {
            // Close the menu
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            if (!confirm('Are you sure you want to delete this entry?')) {
                return;
            }

            if (!isLoggedIn) {
                if (typeof showSnackbar === 'function') {
                    showSnackbar('You must be logged in to delete entries', 'error');
                } else {
                    alert('You must be logged in to delete entries');
                }
                return;
            }

            try {
                // Use the numeric entry ID from currentEntry, or fall back to entryId parameter
                const apiEntryId = currentEntry ? currentEntry.id : entryId;
                
                const response = await fetch(`/api/entries/${apiEntryId}`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to delete entry');
                }

                // Redirect to home page after successful deletion
                if (typeof showSnackbar === 'function') {
                    showSnackbar('Entry deleted successfully', 'success');
                }
                
                setTimeout(() => {
                    window.location.href = '/';
                }, 1000);

            } catch (error) {
                console.error('Error deleting entry:', error);
                if (typeof showSnackbar === 'function') {
                    showSnackbar(`Failed to delete entry: ${error.message}`, 'error');
                } else {
                    alert(`Failed to delete entry: ${error.message}`);
                }
            }
        }

        // Load the entry on page load
        loadEntry();
    </script>
</body>
</html>
