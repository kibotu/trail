/**
 * Collection Profile Manager - Collection page display
 *
 * Loads collection metadata and renders the profile header: avatar, name,
 * slug, bio, tags and stats. No owner editing (that happens in the admin
 * dashboard), so this is deliberately thinner than UserProfileManager.
 */

class CollectionProfileManager {
    constructor(options = {}) {
        this.slug = options.slug;
        this.apiBase = options.apiBase || '/api';
        this.baseUrl = options.baseUrl || '';
        this.collectionData = null;
        this.isAdmin = false;

        this.elements = {
            collectionHeaderImage: 'collectionHeaderImage',
            profileAvatar: 'profileAvatar',
            profileName: 'profileName',
            profileBio: 'profileBio',
            collectionTags: 'collectionTags',
            profileBannerContainer: 'profileBannerContainer'
        };
    }

    async init() {
        this.isAdmin = document.body.dataset.isAdmin === 'true';

        try {
            await this.loadCollection();
            if (this.isAdmin) {
                this.setupEventListeners();
            }
        } catch (error) {
            console.error('Failed to initialize collection:', error);
            this.showError('Failed to load collection. Please try again.');
        }
    }

    /**
     * Record a collection view (fire-and-forget)
     */
    recordView() {
        fetch(`${this.apiBase}/collections/${this.slug}/views`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fingerprint: this.getBrowserFingerprint() })
        })
        .then(res => res.json())
        .then(data => {
            if (data.recorded && data.view_count !== undefined) {
                const viewCountEl = document.querySelector('#statTotalViews .profile-stat-value');
                if (viewCountEl) {
                    viewCountEl.textContent = this.formatNumber(data.view_count);
                }
            }
        })
        .catch(() => {}); // Silent - views are best-effort
    }

    /**
     * Generate a lightweight browser fingerprint for view deduplication.
     */
    getBrowserFingerprint() {
        // Use cached fingerprint if available from card-template.js
        if (typeof getBrowserFingerprint === 'function') {
            return getBrowserFingerprint();
        }
        const components = [
            screen.width,
            screen.height,
            screen.colorDepth,
            navigator.language,
            navigator.hardwareConcurrency || 0,
            navigator.platform || ''
        ];
        return components.join('|');
    }

    async loadCollection() {
        const response = await fetch(`${this.apiBase}/collections/${this.slug}`, {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Failed to load collection');
        }

        this.collectionData = await response.json();
        this.displayCollection();
        this.recordView();

        return this.collectionData;
    }

    displayCollection() {
        const collection = this.collectionData.collection || this.collectionData;

        // Header image
        const headerEl = document.getElementById(this.elements.collectionHeaderImage);
        if (headerEl) {
            if (collection.header_image_url) {
                headerEl.style.backgroundImage = `url('${collection.header_image_url}')`;
            } else {
                headerEl.style.backgroundImage = '';
            }
        }

        // Avatar
        const avatarEl = document.getElementById(this.elements.profileAvatar);
        if (avatarEl) {
            const baseUrl = this.baseUrl || '';
            avatarEl.src = collection.avatar_url || `${baseUrl}/assets/app-icon.webp`;
        }

        // Name
        const nameEl = document.getElementById(this.elements.profileName);
        if (nameEl) {
            nameEl.textContent = collection.name || `/collection/${this.slug}`;
        }

        // Bio
        const bioEl = document.getElementById(this.elements.profileBio);
        if (bioEl) {
            bioEl.textContent = collection.bio || '';
        }

        // Tags (collapsed behind the "Tags" details; hidden when there are none)
        const tagsEl = document.getElementById(this.elements.collectionTags);
        if (tagsEl) {
            const tags = Array.isArray(collection.tags) ? collection.tags : [];
            tagsEl.innerHTML = tags.map(tag => {
                const link = `/collection/${this.slug}`;
                return `<a href="${link}" class="entry-tag" data-no-navigate>#${escapeHtml(tag.name)}</a>`;
            }).join('');
            tagsEl.closest('details').hidden = tags.length === 0;
        }

        // Stats
        const statsContainer = document.getElementById('profileStats');
        if (statsContainer) {
            const setStat = (id, value) => {
                const el = document.getElementById(id);
                if (!el) return;
                const num = Number(value) || 0;
                if (num === 0) {
                    el.style.display = 'none';
                } else {
                    el.style.display = '';
                    el.querySelector('.profile-stat-value').textContent = this.formatNumber(num);
                }
            };
            setStat('statEntries', collection.entry_count ?? 0);
            setStat('statTotalViews', collection.view_count ?? 0);
            statsContainer.style.display = '';
        }

        // Show container
        const containerEl = document.getElementById(this.elements.profileBannerContainer);
        if (containerEl) {
            containerEl.style.display = 'block';
        }

        // Show upload overlays for admins
        if (this.isAdmin) {
            const headerOverlay = document.getElementById('headerUploadOverlay');
            const avatarOverlay = document.getElementById('avatarUploadOverlay');
            if (headerOverlay) headerOverlay.classList.add('owner');
            if (avatarOverlay) avatarOverlay.classList.add('owner');
        }
    }

    setupEventListeners() {
        const headerImage = document.getElementById(this.elements.collectionHeaderImage);
        const avatarImage = document.getElementById(this.elements.profileAvatar);

        if (headerImage) {
            headerImage.style.cursor = 'pointer';
            headerImage.addEventListener('click', (e) => {
                if (e.target.closest('.header-upload-overlay')) return;
                this.triggerHeaderImageUpload();
            });
        }

        const headerOverlay = document.getElementById('headerUploadOverlay');
        if (headerOverlay) {
            headerOverlay.style.cursor = 'pointer';
            headerOverlay.addEventListener('click', (e) => {
                e.stopPropagation();
                this.triggerHeaderImageUpload();
            });
        }

        if (avatarImage) {
            avatarImage.style.cursor = 'pointer';
            avatarImage.addEventListener('click', (e) => {
                if (e.target.closest('.avatar-upload-overlay')) return;
                this.triggerAvatarImageUpload();
            });
        }

        const avatarOverlay = document.getElementById('avatarUploadOverlay');
        if (avatarOverlay) {
            avatarOverlay.style.cursor = 'pointer';
            avatarOverlay.addEventListener('click', (e) => {
                e.stopPropagation();
                this.triggerAvatarImageUpload();
            });
        }
    }

    triggerHeaderImageUpload() {
        if (typeof ImageUploader === 'undefined') {
            if (typeof showSnackbar === 'function') {
                showSnackbar('Image upload feature is not available. Please refresh the page.', 'error');
            }
            return;
        }

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif';
        fileInput.style.display = 'none';

        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (fileInput.parentNode) fileInput.parentNode.removeChild(fileInput);

            const doUpload = async (uploadFile) => {
                try {
                    const headerOverlay = document.getElementById('headerUploadOverlay');
                    if (headerOverlay) {
                        headerOverlay.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Uploading...</span>';
                    }

                    const uploader = new ImageUploader(
                        'header',
                        () => {},
                        async (result) => {
                            await this.updateCollection({ header_image_id: result.image_id });
                            const headerEl = document.getElementById(this.elements.collectionHeaderImage);
                            if (headerEl) headerEl.style.backgroundImage = `url('${result.url}')`;
                            if (typeof showSnackbar === 'function') {
                                showSnackbar('Header uploaded', 'success');
                            }
                        },
                        (error) => {
                            console.error('Upload error:', error);
                            if (typeof showSnackbar === 'function') {
                                showSnackbar(error, 'error');
                            }
                        }
                    );
                    await uploader.upload(uploadFile);
                } catch (error) {
                    console.error('Upload failed:', error);
                }
            };

            if (typeof ImageCropModal !== 'undefined') {
                ImageCropModal.show(file, {
                    aspectRatio: 3.68,
                    outputWidth: 1920,
                    onCrop: doUpload,
                    onCancel: () => {}
                });
            } else {
                doUpload(file);
            }
        });

        document.body.appendChild(fileInput);
        fileInput.click();
    }

    triggerAvatarImageUpload() {
        if (typeof ImageUploader === 'undefined') {
            if (typeof showSnackbar === 'function') {
                showSnackbar('Image upload feature is not available. Please refresh the page.', 'error');
            }
            return;
        }

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif';
        fileInput.style.display = 'none';

        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (fileInput.parentNode) fileInput.parentNode.removeChild(fileInput);

            const doUpload = async (uploadFile) => {
                try {
                    const avatarOverlay = document.getElementById('avatarUploadOverlay');
                    if (avatarOverlay) {
                        avatarOverlay.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                    }

                    const uploader = new ImageUploader(
                        'profile',
                        () => {},
                        async (result) => {
                            await this.updateCollection({ avatar_image_id: result.image_id });
                            const avatarEl = document.getElementById(this.elements.profileAvatar);
                            if (avatarEl) avatarEl.src = result.url;
                            if (typeof showSnackbar === 'function') {
                                showSnackbar('Avatar uploaded', 'success');
                            }
                        },
                        (error) => {
                            console.error('Upload error:', error);
                            if (typeof showSnackbar === 'function') {
                                showSnackbar(error, 'error');
                            }
                        }
                    );
                    await uploader.upload(uploadFile);
                } catch (error) {
                    console.error('Upload failed:', error);
                }
            };

            if (typeof ImageCropModal !== 'undefined') {
                ImageCropModal.show(file, {
                    aspectRatio: 1,
                    outputWidth: 512,
                    onCrop: doUpload,
                    onCancel: () => {}
                });
            } else {
                doUpload(file);
            }
        });

        document.body.appendChild(fileInput);
        fileInput.click();
    }

    async updateCollection(fields) {
        const c = this.collectionData.collection || this.collectionData;
        const body = {
            name: c.name ?? '',
            slug: c.slug ?? '',
            bio: c.bio ?? '',
            avatar_image_id: c.avatar_image_id ?? null,
            header_image_id: c.header_image_id ?? null,
            ...fields
        };
        const response = await fetch(`${this.apiBase}/admin/collections/${c.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        });
        if (!response.ok) {
            throw new Error('Failed to update collection');
        }
    }

    /**
     * Format a number for display (e.g. 1234 -> "1,234", 12500 -> "12.5K")
     */
    formatNumber(n) {
        const num = Number(n) || 0;
        if (num >= 1_000_000) return (num / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (num >= 10_000) return (num / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
        return num.toLocaleString();
    }

    showError(message) {
        if (typeof showSnackbar === 'function') {
            showSnackbar(message, 'error');
        } else {
            alert(message);
        }
    }

    getCollectionData() {
        return this.collectionData;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CollectionProfileManager };
}
