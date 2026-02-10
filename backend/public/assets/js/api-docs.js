/**
 * API Documentation Page JavaScript
 * Handles interactive features for the API documentation
 */

// Initialize Mermaid v11 with dark theme
mermaid.initialize({ 
    startOnLoad: true,
    theme: 'dark',
    themeVariables: {
        primaryColor: '#3b82f6',
        primaryTextColor: '#f8fafc',
        primaryBorderColor: '#3b82f6',
        lineColor: '#94a3b8',
        secondaryColor: '#1e293b',
        tertiaryColor: '#334155',
        background: '#0f172a',
        mainBkg: '#1e293b',
        secondBkg: '#334155',
        textColor: '#f8fafc',
        border1: '#3b82f6',
        border2: '#94a3b8',
        fontSize: '16px'
    },
    securityLevel: 'loose',
    fontFamily: 'Inter, sans-serif',
    logLevel: 'error'
});

/**
 * Copy text to clipboard
 * @param {HTMLElement} button - The copy button element
 */
function copyToClipboard(button) {
    const codeElement = button.closest('.curl-container').querySelector('.curl-code');
    const text = codeElement.textContent;
    
    navigator.clipboard.writeText(text).then(() => {
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.style.background = '#10b981';
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '';
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

/**
 * Filter endpoints based on search term
 */
function filterEndpoints() {
    const searchTerm = document.getElementById('endpoint-search').value.toLowerCase();
    const endpointCards = document.querySelectorAll('.endpoint-card');
    
    endpointCards.forEach(card => {
        const path = card.dataset.path.toLowerCase();
        const method = card.dataset.method.toLowerCase();
        const description = card.dataset.description.toLowerCase();
        
        const matches = path.includes(searchTerm) || 
                       method.includes(searchTerm) || 
                       description.includes(searchTerm);
        
        card.style.display = matches ? 'block' : 'none';
    });
    
    // Hide empty groups
    const groups = document.querySelectorAll('.endpoint-group');
    groups.forEach(group => {
        const visibleCards = group.querySelectorAll('.endpoint-card[style="display: block;"], .endpoint-card:not([style*="display: none"])');
        group.style.display = visibleCards.length > 0 ? 'block' : 'none';
    });
}

/**
 * Toggle collapsible sections
 * @param {HTMLElement} header - The header element to toggle
 */
function toggleCollapsible(header) {
    const content = header.nextElementSibling;
    const icon = header.querySelector('.toggle-icon');
    
    content.classList.toggle('active');
    if (icon) {
        icon.textContent = content.classList.contains('active') ? '−' : '+';
    }
}

// Smooth scroll for TOC links
document.querySelectorAll('.toc-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = link.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Update active link
            document.querySelectorAll('.toc-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        }
    });
});

// Intersection Observer for TOC active state
const observerOptions = {
    root: null,
    rootMargin: '-20% 0px -70% 0px',
    threshold: 0
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const id = entry.target.getAttribute('id');
            document.querySelectorAll('.toc-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${id}`) {
                    link.classList.add('active');
                }
            });
        }
    });
}, observerOptions);

// Observe all sections
document.querySelectorAll('section[id]').forEach(section => {
    observer.observe(section);
});

// Initialize - set first TOC link as active
document.addEventListener('DOMContentLoaded', () => {
    const firstLink = document.querySelector('.toc-link');
    if (firstLink) {
        firstLink.classList.add('active');
    }
});
