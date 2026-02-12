/**
 * Admin Short Links JavaScript
 * Handles short link viewing and resolution
 * 
 * Authentication: All requests use session cookies (credentials: 'same-origin')
 */

let shortLinksPage = 0;
let shortLinksLoading = false;
let shortLinksHasMore = true;
const shortLinksPageSize = 20;

/**
 * Initialize short links functionality
 */
function initShortLinks() {
    // Infinite scroll for short links view
    window.addEventListener('scroll', () => {
        const scrollPosition = window.innerHeight + window.scrollY;
        const threshold = document.documentElement.scrollHeight - 500;
        
        if (scrollPosition >= threshold) {
            if (currentView === 'short-links' && !shortLinksLoading && shortLinksHasMore) {
                loadShortLinks();
            }
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShortLinks);
} else {
    initShortLinks();
}

/**
 * Reset and reload short links
 */
function resetAndLoadShortLinks() {
    shortLinksPage = 0;
    shortLinksHasMore = true;
    const container = document.getElementById('short-links-container');
    if (container) {
        container.innerHTML = '';
    }
    loadShortLinks();
}

/**
 * Load short links from API
 */
async function loadShortLinks() {
    if (shortLinksLoading || !shortLinksHasMore) return;
    
    shortLinksLoading = true;
    document.getElementById('loading').style.display = 'block';
    
    try {
        const params = new URLSearchParams({
            page: shortLinksPage.toString(),
            limit: shortLinksPageSize.toString()
        });
        
        const response = await fetch(`/api/admin/short-links?${params}`, {
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to load short links');
        }
        
        const data = await response.json();
        const container = document.getElementById('short-links-container');
        const emptyState = document.getElementById('empty-short-links-state');
        
        if (data.short_links.length === 0 && shortLinksPage === 0) {
            container.style.display = 'none';
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
            container.style.display = 'grid';
            
            data.short_links.forEach(link => {
                const card = createShortLinkCard(link);
                container.appendChild(card);
            });
            
            shortLinksPage++;
            shortLinksHasMore = data.short_links.length === shortLinksPageSize;
        }
    } catch (error) {
        console.error('Error loading short links:', error);
        showSnackbar('Failed to load short links', 'error');
    } finally {
        shortLinksLoading = false;
        document.getElementById('loading').style.display = 'none';
    }
}

/**
 * Create short link card element
 */
function createShortLinkCard(link) {
    const card = document.createElement('div');
    card.className = 'entry-card short-link-card';
    card.dataset.linkId = link.id;
    
    // Determine status
    const isPending = !link.short_link_resolve_failed_at;
    const statusClass = isPending ? 'pending' : 'failed';
    const statusLabel = isPending ? 'Pending' : 'Failed';
    
    // Format failed date if available
    let failedInfo = '';
    if (link.short_link_resolve_failed_at) {
        const failedDate = new Date(link.short_link_resolve_failed_at);
        failedInfo = `Failed: ${formatRelativeTimeShort(failedDate)}`;
    }
    
    // Extract domain from URL
    let domain = '';
    try {
        const url = new URL(link.url);
        domain = url.hostname;
    } catch (e) {
        domain = 'unknown';
    }
    
    card.innerHTML = `
        <div class="entry-header">
            <div class="entry-meta">
                <span class="status-badge ${statusClass}">
                    ${statusLabel}
                </span>
                <span class="entry-date" style="margin-left: 0.5rem;">
                    ${domain}
                </span>
            </div>
        </div>
        
        <div class="short-link-content">
            <div class="short-link-url">
                <a href="${escapeHtmlShort(link.url)}" target="_blank" rel="noopener noreferrer">
                    ${escapeHtmlShort(link.url)}
                    <i class="fa-solid fa-external-link-alt" style="font-size: 0.75rem; margin-left: 0.25rem;"></i>
                </a>
            </div>
            
            ${link.title ? `<div class="short-link-title">${escapeHtmlShort(link.title)}</div>` : ''}
            
            <div class="short-link-meta">
                ${failedInfo ? `<span><i class="fa-solid fa-clock"></i> ${failedInfo}</span>` : '<span><i class="fa-solid fa-hourglass-half"></i> Not tried yet</span>'}
                <span><i class="fa-solid fa-file-lines"></i> ${link.affected_entries} ${link.affected_entries === 1 ? 'entry' : 'entries'} affected</span>
            </div>
        </div>
    `;
    
    return card;
}

/**
 * Resolve short links (run short link resolver)
 */
async function resolveShortLinks() {
    const button = document.querySelector('.btn-resolve-short-links');
    if (!button) return;
    
    const originalText = button.innerHTML;
    button.disabled = true;
    
    // Get config from backend (batch size and rate limit)
    const config = window.SHORT_LINK_CONFIG || { batch_size: 50, rate_limit_ms: 500 };
    const batchSize = config.batch_size;
    
    // Calculate total estimated time
    const msPerUrl = Math.max(config.rate_limit_ms + 200, 300);
    const totalEstimatedMs = batchSize * msPerUrl;
    
    let currentProgress = 0;
    let progressInterval;
    
    // Update progress display
    const updateProgress = (current) => {
        button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Resolving ${current}/${batchSize}...`;
    };
    
    // Start progress simulation
    let estimatedCurrent = 1;
    progressInterval = setInterval(() => {
        currentProgress += 100;
        const progressRatio = currentProgress / totalEstimatedMs;
        const newCurrent = Math.max(1, Math.min(batchSize, Math.ceil(progressRatio * batchSize)));
        
        if (newCurrent !== estimatedCurrent) {
            estimatedCurrent = newCurrent;
            updateProgress(estimatedCurrent);
        }
    }, 100);
    
    updateProgress(1);
    
    try {
        const response = await fetch('/api/admin/short-links/resolve', {
            method: 'POST',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to resolve short links');
        }
        
        const data = await response.json();
        
        // Clear progress interval
        clearInterval(progressInterval);
        
        // Show final actual count
        button.innerHTML = `<i class="fa-solid fa-check"></i> Resolved ${data.resolved}`;
        
        showSnackbar(data.message || `Resolved ${data.resolved} short links`, 'success');
        
        // Reload the page to update stats
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        
    } catch (error) {
        console.error('Error resolving short links:', error);
        clearInterval(progressInterval);
        showSnackbar('Failed to resolve short links', 'error');
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

/**
 * Format relative time (short version)
 */
function formatRelativeTimeShort(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSecs < 60) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 30) return `${diffDays}d ago`;
    
    const diffMonths = Math.floor(diffDays / 30);
    if (diffMonths < 12) return `${diffMonths}mo ago`;
    
    const diffYears = Math.floor(diffMonths / 12);
    return `${diffYears}y ago`;
}

/**
 * Escape HTML to prevent XSS (short links version)
 */
function escapeHtmlShort(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
