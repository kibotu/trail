/**
 * Meta Updater - Dynamic Open Graph meta tags
 * 
 * Updates Open Graph meta tags for social media sharing.
 * Used on entry detail pages to provide rich preview information.
 */

/**
 * Update a meta tag with given property and content
 * @param {string} property - Meta tag property (e.g., 'og:title')
 * @param {string} content - Content value
 */
function updateMetaTag(property, content) {
    let meta = document.querySelector(`meta[property="${property}"]`);
    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('property', property);
        document.head.appendChild(meta);
    }
    meta.setAttribute('content', content);
}

/**
 * Update multiple meta tags at once
 * @param {Object} tags - Object with property-content pairs
 */
function updateMetaTags(tags) {
    Object.entries(tags).forEach(([property, content]) => {
        if (content) {
            updateMetaTag(property, content);
        }
    });
}

/**
 * Update meta tags from entry data
 * @param {Object} entry - Entry object with user and content data
 */
function updateMetaTagsFromEntry(entry) {
    const displayName = entry.user_nickname || entry.user_name || 'User';
    const entryText = entry.text.substring(0, 100) + (entry.text.length > 100 ? '...' : '');

    // Update page title
    document.title = `${displayName} on Trail: "${entryText}"`;

    // Update Open Graph meta tags
    const tags = {
        'og:title': `${displayName} on Trail`,
        'og:description': entryText,
        'og:url': window.location.href,
        'og:type': 'article'
    };

    // Add image if available
    if (entry.preview_image) {
        tags['og:image'] = entry.preview_image;
    } else if (entry.images && entry.images.length > 0) {
        tags['og:image'] = entry.images[0].url;
    }

    updateMetaTags(tags);
}

/**
 * Update Twitter card meta tags
 * @param {Object} data - Twitter card data
 */
function updateTwitterCard(data) {
    const {
        card = 'summary',
        title,
        description,
        image
    } = data;

    const tags = {
        'twitter:card': card,
        'twitter:title': title,
        'twitter:description': description,
        'twitter:image': image
    };

    Object.entries(tags).forEach(([name, content]) => {
        if (content) {
            let meta = document.querySelector(`meta[name="${name}"]`);
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('name', name);
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', content);
        }
    });
}

/**
 * Clear all Open Graph meta tags
 */
function clearMetaTags() {
    const ogTags = document.querySelectorAll('meta[property^="og:"]');
    ogTags.forEach(tag => tag.remove());
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        updateMetaTag,
        updateMetaTags,
        updateMetaTagsFromEntry,
        updateTwitterCard,
        clearMetaTags
    };
}
