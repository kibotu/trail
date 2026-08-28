/**
 * Admin Collections Management JavaScript
 * Handles collection listing, creation, editing, and deletion.
 *
 * Collections are tag-defined groups. Creating/editing one does not touch
 * entries — membership is computed dynamically from the tag set.
 */

/**
 * Initialize collections functionality
 */
function initCollectionsManagement() {
    // Nothing to pre-load; the view loads lazily via switchView('collections')
}

/**
 * Load collections for the collections management view
 */
async function loadCollections() {
    const container = document.getElementById('collections-container');
    const emptyState = document.getElementById('empty-collections-state');
    const loading = document.getElementById('loading');

    loading.style.display = 'block';
    container.innerHTML = '';

    try {
        const response = await fetch('/api/collections', {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        const collections = data.collections || [];

        if (collections.length > 0) {
            renderCollections(collections);
            emptyState.style.display = 'none';
        } else {
            container.innerHTML = '';
            emptyState.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading collections:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar('Failed to load collections.', 'error');
        }
    } finally {
        loading.style.display = 'none';
    }
}

/**
 * Render the collections table
 */
function renderCollections(collections) {
    const container = document.getElementById('collections-container');
    container.innerHTML = '';

    const table = document.createElement('div');
    table.className = 'tags-table'; // reuse tag table styling
    table.innerHTML = `
        <div class="tags-table-header">
            <div class="tags-col-name">Collection</div>
            <div class="tags-col-slug">Slug</div>
            <div class="tags-col-count">Entries</div>
            <div class="tags-col-actions">Actions</div>
        </div>
    `;

    collections.forEach(collection => {
        table.appendChild(createCollectionRow(collection));
    });

    container.appendChild(table);
}

/**
 * Create a collections table row
 */
function createCollectionRow(collection) {
    const row = document.createElement('div');
    row.className = 'tags-table-row';
    row.dataset.collectionId = collection.id;

    const views = collection.view_count || 0;
    const created = collection.created_at ? new Date(collection.created_at).toLocaleDateString() : '';

    row.innerHTML = `
        <div class="tags-col-name">
            <span class="collection-name-display">${escapeHtml(collection.name)}</span>
            <span class="collection-views" style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.5rem;">${views} views</span>
        </div>
        <div class="tags-col-slug">
            <code>${escapeHtml(collection.slug)}</code>
            <span class="collection-created" style="font-size: 0.75rem; color: var(--text-muted); margin-left: 0.5rem;">${created}</span>
        </div>
        <div class="tags-col-count">
            <span class="entry-count">${collection.entry_count || 0}</span>
        </div>
        <div class="tags-col-actions">
            <button class="button secondary tiny" onclick="editCollection('${escapeHtml(collection.slug)}')" title="Edit collection">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button class="button danger tiny" onclick="deleteCollection(${collection.id}, '${escapeHtml(collection.name)}')" title="Delete collection">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;

    return row;
}

/**
 * Open the collection create/edit form modal
 * @param {string} [slug] - If provided, edit this collection; otherwise create.
 */
function editCollection(slug) {
    openCollectionForm(slug);
}

/**
 * Suggest a slug from a collection name (mirrors Collection::slugify).
 */
function slugifyCollectionName(name) {
    let slug = name.toLowerCase();
    slug = slug.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return slug.slice(0, 64);
}

/**
 * Selected tag ids for the current modal (persists across picker re-renders).
 */
let collectionTagSelection = new Set();

/**
 * Open the create/edit collection modal.
 * Pass a slug to edit; omit to create a new collection.
 */
async function openCollectionForm(slug) {
    // Remove any existing modal
    const existing = document.getElementById('collection-modal');
    if (existing) existing.remove();

    let collection = null;

    if (slug) {
        try {
            const response = await fetch(`/api/collections/${slug}`, { credentials: 'same-origin' });
            if (response.ok) {
                collection = (await response.json()).collection;
            }
        } catch (e) {
            console.error('Error loading collection:', e);
        }
    }

    collectionTagSelection = new Set((collection && collection.tags ? collection.tags : []).map(t => t.id));

    const modal = document.createElement('div');
    modal.id = 'collection-modal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-box"></i> ${collection ? 'Edit' : 'Create'} Collection</h3>
                <button class="modal-close" onclick="closeCollectionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 0.75rem;">
                    <label for="collection-name" style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem;">Name</label>
                    <input type="text" id="collection-name" value="${escapeHtml(collection ? collection.name : '')}" maxlength="140" class="source-filter-select" style="width: 100%;">
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <label for="collection-slug" style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem;">Slug</label>
                    <input type="text" id="collection-slug" value="${escapeHtml(collection ? collection.slug : '')}" maxlength="64" class="source-filter-select" style="width: 100%;">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Lowercase, 2-64 chars, only letters/digits/hyphens. Suggested from the name.</p>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <label for="collection-bio" style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem;">Bio</label>
                    <input type="text" id="collection-bio" value="${escapeHtml(collection ? collection.bio || '' : '')}" maxlength="160" class="source-filter-select" style="width: 100%;">
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <label style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem;">Avatar &amp; header</label>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div style="text-align: center;">
                            <img id="collection-avatar-preview" src="${escapeHtml(collection && collection.avatar_url ? collection.avatar_url : '/assets/app-icon.webp')}" alt="Collection avatar" style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover;">
                            <button class="button secondary tiny" style="margin-top: 0.25rem;" onclick="uploadCollectionImage('avatar')">Avatar</button>
                        </div>
                        <div style="text-align: center;">
                            <img id="collection-header-preview" src="${escapeHtml(collection && collection.header_image_url ? collection.header_image_url : '')}" alt="Collection header" style="width: 120px; height: 56px; object-fit: cover; ${collection && collection.header_image_url ? '' : 'display: none;'}">
                            <button class="button secondary tiny" style="margin-top: 0.25rem;" onclick="uploadCollectionImage('header')">Header</button>
                        </div>
                        <input type="hidden" id="collection-avatar-image-id" value="${collection && collection.avatar_image_id ? collection.avatar_image_id : ''}">
                        <input type="hidden" id="collection-header-image-id" value="${collection && collection.header_image_id ? collection.header_image_id : ''}">
                    </div>
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <label for="collection-tag-search" style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem;">Tags</label>
                    <input type="text" id="collection-tag-search" placeholder="Search tags..." class="source-filter-select" style="width: 100%;">
                    <div id="collection-tag-results" style="max-height: 12rem; overflow-y: auto; margin-top: 0.5rem;"></div>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Any entry carrying one of these tags belongs to the collection.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="button secondary" onclick="closeCollectionModal()">Cancel</button>
                <button class="button primary" onclick="saveCollection(${collection ? collection.id : 'null'})">
                    <i class="fa-solid fa-floppy-disk"></i> ${collection ? 'Update' : 'Create'}
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeCollectionModal();
    });

    // Slug auto-suggested from the name until the slug is edited manually
    const nameInput = document.getElementById('collection-name');
    const slugInput = document.getElementById('collection-slug');
    let slugTouched = !!collection;
    slugInput.addEventListener('input', () => { slugTouched = true; });
    nameInput.addEventListener('input', () => {
        if (!slugTouched) {
            slugInput.value = slugifyCollectionName(nameInput.value);
        }
    });

    // Tag picker against /api/admin/tags (Tag::search with live entry counts)
    const tagSearch = document.getElementById('collection-tag-search');
    const tagResults = document.getElementById('collection-tag-results');

    const renderTagResults = (tags) => {
        tagResults.innerHTML = tags.length ? tags.map(t => `
            <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.5rem; cursor: pointer;">
                <input type="checkbox" value="${t.id}" ${collectionTagSelection.has(t.id) ? 'checked' : ''}>
                <span>#${escapeHtml(t.name)}</span>
                <span style="font-size: 0.75rem; color: var(--text-muted);">(${t.entry_count || 0})</span>
            </label>
        `).join('') : '<p style="font-size: 0.75rem; color: var(--text-muted);">No tags match.</p>';
    };

    tagResults.addEventListener('change', (e) => {
        if (e.target.type !== 'checkbox') return;
        const id = parseInt(e.target.value, 10);
        if (e.target.checked) {
            collectionTagSelection.add(id);
        } else {
            collectionTagSelection.delete(id);
        }
    });

    let tagSearchTimer = null;
    tagSearch.addEventListener('input', () => {
        clearTimeout(tagSearchTimer);
        tagSearchTimer = setTimeout(async () => {
            const query = tagSearch.value.trim();
            try {
                const response = await fetch(`/api/admin/tags${query ? '?search=' + encodeURIComponent(query) : ''}`, { credentials: 'same-origin' });
                const data = await response.json();
                renderTagResults(data.tags || []);
            } catch (err) {
                console.error('Tag search failed:', err);
            }
        }, 300);
    });

    try {
        const initialTagsResponse = await fetch('/api/admin/tags', { credentials: 'same-origin' });
        const initialTagsData = await initialTagsResponse.json();
        renderTagResults(initialTagsData.tags || []);
    } catch (err) {
        console.error('Error loading tags for picker:', err);
    }
}

/**
 * Close the collection modal
 */
function closeCollectionModal() {
    const modal = document.getElementById('collection-modal');
    if (modal) modal.remove();
}

/**
 * Upload an avatar or header image via the shared ImageUploader.
 * @param {'avatar'|'header'} kind
 */
function uploadCollectionImage(kind) {
    if (typeof ImageUploader === 'undefined') {
        if (typeof showSnackbar === 'function') {
            showSnackbar('Image upload feature is not available. Please refresh the page.', 'error');
        }
        return;
    }

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif';
    input.style.display = 'none';

    input.addEventListener('change', async () => {
        const file = input.files && input.files[0];
        if (!file) return;

        try {
            const imageType = kind === 'avatar' ? 'profile' : 'header';
            const uploader = new ImageUploader(
                imageType,
                () => {},
                (result) => {
                    const field = document.getElementById(`collection-${kind}-image-id`);
                    if (field) field.value = result.image_id;
                    const preview = document.getElementById(`collection-${kind}-preview`);
                    if (preview) {
                        preview.src = result.url;
                        preview.style.display = '';
                    }
                    if (typeof showSnackbar === 'function') {
                        showSnackbar(`${kind === 'avatar' ? 'Avatar' : 'Header'} uploaded`, 'success');
                    }
                },
                (error) => {
                    console.error('Upload error:', error);
                    if (typeof showSnackbar === 'function') {
                        showSnackbar(error, 'error');
                    }
                }
            );
            await uploader.upload(file);
        } catch (error) {
            console.error('Upload failed:', error);
        } finally {
            if (input.parentNode) {
                input.parentNode.removeChild(input);
            }
        }
    });

    document.body.appendChild(input);
    input.click();
}

/**
 * Save the collection (create or update)
 * @param {number|null} id - Collection id when editing, null when creating.
 */
async function saveCollection(id) {
    const nameInput = document.getElementById('collection-name');
    const slugInput = document.getElementById('collection-slug');
    const bioInput = document.getElementById('collection-bio');
    const avatarField = document.getElementById('collection-avatar-image-id');
    const headerField = document.getElementById('collection-header-image-id');

    const name = nameInput.value.trim();
    const slug = slugInput.value.trim();
    const bio = bioInput.value.trim();
    const avatar_image_id = avatarField && avatarField.value ? parseInt(avatarField.value, 10) : null;
    const header_image_id = headerField && headerField.value ? parseInt(headerField.value, 10) : null;

    if (!name) {
        showSnackbar('Collection name is required', 'error');
        return;
    }
    if (!/^[a-z0-9-]+$/.test(slug) || slug.length < 2 || slug.length > 64) {
        showSnackbar('Slug must be 2-64 chars, lowercase letters/digits/hyphens', 'error');
        return;
    }

    const saveBtn = document.querySelector('#collection-modal .button.primary');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const payload = {
        name,
        slug,
        bio,
        tag_ids: Array.from(collectionTagSelection),
        avatar_image_id,
        header_image_id
    };
    const url = id ? `/api/admin/collections/${id}` : '/api/admin/collections';
    const method = id ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to save collection');
        }

        closeCollectionModal();

        if (typeof showSnackbar === 'function') {
            showSnackbar(id ? 'Collection updated' : 'Collection created', 'success');
        }

        loadCollections();
    } catch (error) {
        console.error('Error saving collection:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar(error.message, 'error');
        }
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> ${id ? 'Update' : 'Create'}`;
    }
}

/**
 * Delete a collection
 */
async function deleteCollection(id, name) {
    // Delete ≠ destroy: entries revert to poster attribution.
    const message = `Delete collection "${name}"?\n\nEntries return to the poster in the feed. Nothing is deleted.`;
    if (!confirm(message)) return;

    try {
        const response = await fetch(`/api/admin/collections/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to delete collection');
        }

        const row = document.querySelector(`.tags-table-row[data-collection-id="${id}"]`);
        if (row) row.remove();

        const container = document.getElementById('collections-container');
        if (container.querySelectorAll('.tags-table-row').length === 0) {
            document.getElementById('empty-collections-state').style.display = 'block';
        }

        if (typeof showSnackbar === 'function') {
            showSnackbar('Collection deleted', 'success');
        }
    } catch (error) {
        console.error('Error deleting collection:', error);
        if (typeof showSnackbar === 'function') {
            showSnackbar(error.message, 'error');
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCollectionsManagement);
} else {
    initCollectionsManagement();
}
