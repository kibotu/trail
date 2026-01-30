/**
 * Infinite Scroll - Reusable infinite scroll handler
 * 
 * Provides automatic content loading when user scrolls near the bottom of the page.
 * Handles pagination state and prevents duplicate requests.
 */

class InfiniteScroll {
    /**
     * Create an infinite scroll handler
     * @param {Function} loadFunction - Async function to call when loading more content
     * @param {Object} options - Configuration options
     * @param {number} options.threshold - Distance from bottom to trigger load (px)
     * @param {HTMLElement} options.loadingElement - Element to show during loading
     * @param {HTMLElement} options.endElement - Element to show when no more content
     * @param {boolean} options.enabled - Whether scroll is initially enabled
     */
    constructor(loadFunction, options = {}) {
        this.loadFunction = loadFunction;
        this.threshold = options.threshold || 500;
        this.loadingElement = options.loadingElement || null;
        this.endElement = options.endElement || null;
        
        this.isLoading = false;
        this.hasMore = true;
        this.enabled = options.enabled !== false;
        
        // Bind handlers
        this.handleScroll = this.handleScroll.bind(this);
        this.handleResize = this.handleResize.bind(this);
        
        // Start listening
        if (this.enabled) {
            this.start();
        }
    }

    /**
     * Start listening for scroll events
     */
    start() {
        this.enabled = true;
        window.addEventListener('scroll', this.handleScroll);
        window.addEventListener('resize', this.handleResize);
        
        // Check immediately in case content is short
        setTimeout(() => this.checkScroll(), 100);
    }

    /**
     * Stop listening for scroll events
     */
    stop() {
        this.enabled = false;
        window.removeEventListener('scroll', this.handleScroll);
        window.removeEventListener('resize', this.handleResize);
    }

    /**
     * Handle scroll event
     */
    handleScroll() {
        if (!this.enabled) return;
        this.checkScroll();
    }

    /**
     * Handle resize event
     */
    handleResize() {
        if (!this.enabled) return;
        this.checkScroll();
    }

    /**
     * Check if we should load more content
     */
    checkScroll() {
        if (this.isLoading || !this.hasMore || !this.enabled) return;

        const scrollPosition = window.innerHeight + window.scrollY;
        const threshold = document.documentElement.scrollHeight - this.threshold;

        if (scrollPosition >= threshold) {
            this.loadMore();
        }
    }

    /**
     * Load more content
     */
    async loadMore() {
        if (this.isLoading || !this.hasMore) return;

        this.isLoading = true;
        
        // Show loading indicator
        if (this.loadingElement) {
            this.loadingElement.style.display = 'block';
        }

        try {
            // Call the load function
            const result = await this.loadFunction();
            
            // Update state based on result
            if (result && typeof result.hasMore !== 'undefined') {
                this.hasMore = result.hasMore;
            }
            
            // Show end message if no more content
            if (!this.hasMore && this.endElement) {
                this.endElement.style.display = 'block';
            }
            
        } catch (error) {
            console.error('Error loading more content:', error);
            
            // Stop infinite scroll on error to prevent endless loops
            // Especially important for 404 errors (non-existing users/resources)
            this.hasMore = false;
            this.stop();
            
            // Hide loading indicator on error
            if (this.loadingElement) {
                this.loadingElement.style.display = 'none';
            }
        } finally {
            this.isLoading = false;
            
            // Hide loading indicator
            if (this.loadingElement) {
                this.loadingElement.style.display = 'none';
            }
            
            // Check again in case the loaded content is still short
            // Only if we still have more content to load
            if (this.hasMore) {
                setTimeout(() => this.checkScroll(), 100);
            }
        }
    }

    /**
     * Reset pagination state
     */
    reset() {
        this.isLoading = false;
        this.hasMore = true;
        
        if (this.loadingElement) {
            this.loadingElement.style.display = 'none';
        }
        if (this.endElement) {
            this.endElement.style.display = 'none';
        }
    }

    /**
     * Set whether more content is available
     * @param {boolean} hasMore - Whether more content is available
     */
    setHasMore(hasMore) {
        this.hasMore = hasMore;
        
        if (!hasMore && this.endElement) {
            this.endElement.style.display = 'block';
        }
    }

    /**
     * Destroy the infinite scroll handler
     */
    destroy() {
        this.stop();
        this.loadFunction = null;
        this.loadingElement = null;
        this.endElement = null;
    }
}

/**
 * Create a simple infinite scroll setup
 * @param {Function} loadFunction - Function to load more content
 * @param {Object} options - Configuration options
 * @returns {InfiniteScroll} Infinite scroll instance
 */
function createInfiniteScroll(loadFunction, options = {}) {
    return new InfiniteScroll(loadFunction, options);
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { InfiniteScroll, createInfiniteScroll };
}
