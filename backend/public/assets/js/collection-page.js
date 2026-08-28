/**
 * Collection Page Initialization
 *
 * Drives templates/public/collection.php and the embeddable widget
 * (templates/public/collection-embed.php): collection profile header and
 * the paginated entries belonging to the collection. In embed mode it
 * mirrors the user embed: optional search, data-limit, and the shared
 * embed behaviors (auto-resize, click overrides, share modal).
 */

(async function() {
    'use strict';

    await loadConfig();

    const body = document.body;
    const slug = body.dataset.slug;
    const parseBool = (value) => value === 'true' || value === '1' || value === 1;
    const isEmbed = body.dataset.embed === '1';
    const baseUrl = body.dataset.baseUrl || '';
    const limit = isEmbed ? (parseInt(body.dataset.limit, 10) || 20) : 100;

    const sessionState = isEmbed
        ? { isLoggedIn: false, userId: null }
        : {
            isLoggedIn: parseBool(body.dataset.isLoggedIn),
            userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null
        };

    let nextCursor = null;
    let currentSearchQuery = '';

    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');

    if (!slug || !entriesContainer || !loadingElement || !endMessage) return;

    // Collection profile header
    const collectionProfileManager = new CollectionProfileManager({
        slug: slug,
        apiBase: '/api',
        baseUrl: ''
    });
    collectionProfileManager.init();

    // Embed mode: auto-resize + click overrides + share modal
    const embedBehaviors = isEmbed ? initEmbedBehaviors(entriesContainer, baseUrl) : null;
    const postHeight = embedBehaviors ? embedBehaviors.postHeight : () => {};

    const entriesManager = new EntriesManager({ sessionState });

    const infiniteScroll = new InfiniteScroll(async () => {
        const result = await entriesManager.loadEntries(`/api/collections/${slug}/entries`, {
            cursor: nextCursor,
            limit: limit,
            container: entriesContainer,
            searchQuery: currentSearchQuery || null,
            cardOptions: {
                showSourceBadge: false,
                canModify: (entry) => isEmbed ? false : canModifyEntry(entry),
                isLoggedIn: sessionState.isLoggedIn,
                currentUserId: sessionState.userId,
                onTagClick: (tagSlug) => {
                    if (searchManager) {
                        searchManager.setQuery('#' + tagSlug);
                        document.getElementById('searchInput')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } else {
                        window.open(`${baseUrl || window.location.origin}/?q=${encodeURIComponent('#' + tagSlug)}`, '_blank', 'noopener');
                    }
                }
            }
        });

        nextCursor = result.next_cursor;

        if (searchManager && currentSearchQuery && entriesContainer.children.length === result.entries.length) {
            const count = result.total_count !== undefined ? result.total_count : result.entries.length;
            searchManager.updateResultsCount(count);
        }

        if (result.entries.length === 0 && entriesContainer.children.length === 0) {
            if (currentSearchQuery) {
                showEmptyState(entriesContainer, {
                    icon: 'fa-magnifying-glass',
                    title: 'No results found',
                    message: `No entries match "${currentSearchQuery}"`
                });
                if (searchManager) searchManager.updateResultsCount(0);
            } else {
                showEmptyState(entriesContainer, {
                    icon: 'fa-file-lines',
                    title: 'No entries yet',
                    message: 'This collection has no entries yet.'
                });
            }
        }

        postHeight();
        return { hasMore: result.has_more };
    }, {
        threshold: 500,
        loadingElement: loadingElement,
        endElement: endMessage
    });

    // Search (embed mode only)
    let searchManager = null;
    if (isEmbed && body.dataset.showSearch === '1' && typeof SearchManager !== 'undefined') {
        const searchSection = document.getElementById('searchSection');
        if (searchSection) {
            searchManager = new SearchManager({
                onSearch: (query) => {
                    currentSearchQuery = query;
                    entriesContainer.innerHTML = '';
                    nextCursor = null;
                    infiniteScroll.reset();
                    infiniteScroll.loadMore();
                }
            });
            searchManager.render(searchSection);
        }
    }

    // Edit/delete handlers (non-embed pages only)
    if (!isEmbed) {
        window.editEntry = function(entryId) {
            entriesManager.editEntry(entryId);
        };

        window.deleteEntry = function(entryId) {
            entriesManager.deleteEntry(entryId);
        };

        window.cancelEdit = function(entryId) {
            entriesManager.cancelEdit(entryId);
        };

        window.saveEdit = function(entryId) {
            entriesManager.saveEdit(entryId);
        };
    }
})().catch(error => {
    console.error('Failed to initialize collection page:', error);
});
