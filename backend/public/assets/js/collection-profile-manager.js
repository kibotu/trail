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
        try {
            await this.loadCollection();
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

        // Tags
        const tagsEl = document.getElementById(this.elements.collectionTags);
        if (tagsEl && Array.isArray(collection.tags)) {
            tagsEl.innerHTML = collection.tags.map(tag => {
                const link = `/collection/${this.slug}`;
                return `<a href="${link}" class="entry-tag" data-no-navigate>#${escapeHtml(tag.name)}</a>`;
            }).join('');
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
