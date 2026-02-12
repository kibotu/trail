/**
 * SearchManager - Handles search functionality across Trail pages
 * 
 * Features:
 * - Debounced search input
 * - URL synchronization (shareable search results)
 * - Clear button when search is active
 * - Loading states
 * - Keyboard shortcuts
 */

class SearchManager {
    constructor(options = {}) {
        this.onSearch = options.onSearch || (() => {});
        this.userNickname = options.userNickname || null;
        this.debounceDelay = options.debounceDelay || 300;
        this.debounceTimer = null;
        this.currentQuery = '';
        this.isSearching = false;
    }

    /**
     * Render the search card into a container
     * @param {HTMLElement} container - Container element
     */
    render(container) {
        if (!container) {
            console.error('SearchManager: Container element not found');
            return;
        }

        const searchQuery = this.getQueryFromURL();
        this.currentQuery = searchQuery;

        container.innerHTML = `
            <div class="search-card">
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input 
                        type="text" 
                        class="search-input" 
                        id="searchInput"
                        placeholder="Search entries or #tags..."
                        value="${this.escapeHtml(searchQuery)}"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <button 
                        class="search-clear-button" 
                        id="searchClearButton"
                        style="display: ${searchQuery ? 'flex' : 'none'};"
                        aria-label="Clear search"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="search-loading" id="searchLoading" style="display: none;">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </div>
                </div>
                ${searchQuery ? `
                    <div class="search-results-info" id="searchResultsInfo">
                        Showing results for: <strong>${this.escapeHtml(searchQuery)}</strong>
                    </div>
                ` : ''}
            </div>
        `;

        this.attachEventListeners();

        // If there's a search query in URL, trigger search on load
        if (searchQuery) {
            this.onSearch(searchQuery);
        }
    }

    /**
     * Attach event listeners to search elements
     */
    attachEventListeners() {
        const searchInput = document.getElementById('searchInput');
        const clearButton = document.getElementById('searchClearButton');

        if (!searchInput) return;

        // Input event with debouncing
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            this.handleInputChange(query);
        });

        // Enter key to search immediately
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(this.debounceTimer);
                const query = e.target.value.trim();
                this.performSearch(query);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.clearSearch();
            }
        });

        // Clear button
        if (clearButton) {
            clearButton.addEventListener('click', () => {
                this.clearSearch();
            });
        }
    }

    /**
     * Handle input change with debouncing
     * @param {string} query - Search query
     */
    handleInputChange(query) {
        // Show/hide clear button
        const clearButton = document.getElementById('searchClearButton');
        if (clearButton) {
            clearButton.style.display = query ? 'flex' : 'none';
        }

        // Debounce the search
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceDelay);
    }

    /**
     * Perform the search
     * @param {string} query - Search query
     */
    performSearch(query) {
        // Don't search if query hasn't changed
        if (query === this.currentQuery) {
            return;
        }

        this.currentQuery = query;

        // Update URL
        this.updateURL(query);

        // Update results info
        this.updateResultsInfo(query);

        // Show loading state
        this.setLoadingState(true);

        // Trigger search callback
        this.onSearch(query);

        // Hide loading state after a short delay (actual loading handled by entries manager)
        setTimeout(() => {
            this.setLoadingState(false);
        }, 500);
    }

    /**
     * Clear the search
     */
    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }

        const clearButton = document.getElementById('searchClearButton');
        if (clearButton) {
            clearButton.style.display = 'none';
        }

        this.performSearch('');
    }

    /**
     * Update the URL with search query (for sharing)
     * @param {string} query - Search query
     */
    updateURL(query) {
        const url = new URL(window.location.href);
        
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }

        // Update URL without reloading page
        window.history.pushState({}, '', url.toString());
    }

    /**
     * Get search query from URL
     * @returns {string} Search query from URL or empty string
     */
    getQueryFromURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get('q') || '';
    }

    /**
     * Update the results info message
     * @param {string} query - Search query
     * @param {number} count - Number of results (optional)
     */
    updateResultsInfo(query, count = null) {
        let resultsInfo = document.getElementById('searchResultsInfo');
        
        if (query) {
            if (!resultsInfo) {
                // Create results info element
                const searchCard = document.querySelector('.search-card');
                if (searchCard) {
                    resultsInfo = document.createElement('div');
                    resultsInfo.id = 'searchResultsInfo';
                    resultsInfo.className = 'search-results-info';
                    searchCard.appendChild(resultsInfo);
                }
            }
            
            if (resultsInfo) {
                let html = `Showing results for: <strong>${this.escapeHtml(query)}</strong>`;
                if (count !== null) {
                    html += ` <span class="search-results-count">(${count} ${count === 1 ? 'result' : 'results'})</span>`;
                }
                resultsInfo.innerHTML = html;
                resultsInfo.style.display = 'block';
            }
        } else {
            if (resultsInfo) {
                resultsInfo.style.display = 'none';
            }
        }
    }

    /**
     * Update the results count
     * @param {number} count - Number of results
     */
    updateResultsCount(count) {
        const query = this.getCurrentQuery();
        if (query) {
            this.updateResultsInfo(query, count);
        }
    }

    /**
     * Set loading state
     * @param {boolean} isLoading - Whether search is loading
     */
    setLoadingState(isLoading) {
        this.isSearching = isLoading;
        
        const loadingElement = document.getElementById('searchLoading');
        const searchIcon = document.querySelector('.search-icon');
        
        if (loadingElement) {
            loadingElement.style.display = isLoading ? 'flex' : 'none';
        }
        
        if (searchIcon) {
            searchIcon.style.display = isLoading ? 'none' : 'flex';
        }
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get current search query
     * @returns {string} Current search query
     */
    getCurrentQuery() {
        return this.currentQuery;
    }

    /**
     * Check if currently searching
     * @returns {boolean} True if search is active
     */
    isActive() {
        return this.currentQuery !== '';
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SearchManager };
}
