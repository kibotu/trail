/**
 * Scroll to Top Button
 * 
 * Displays a floating action button that appears when the user scrolls down
 * and allows them to quickly return to the top of the page.
 */

(function() {
    'use strict';

    // Create the scroll-to-top button
    function createScrollToTopButton() {
        const button = document.createElement('button');
        button.className = 'scroll-to-top';
        button.setAttribute('aria-label', 'Scroll to top');
        button.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
        document.body.appendChild(button);
        return button;
    }

    // Initialize scroll-to-top functionality
    function initScrollToTop() {
        const scrollButton = createScrollToTopButton();
        const scrollThreshold = 300; // Show button after scrolling 300px

        // Show/hide button based on scroll position
        function toggleButtonVisibility() {
            if (window.pageYOffset > scrollThreshold) {
                scrollButton.classList.add('visible');
            } else {
                scrollButton.classList.remove('visible');
            }
        }

        // Smooth scroll to top
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Event listeners
        window.addEventListener('scroll', toggleButtonVisibility, { passive: true });
        scrollButton.addEventListener('click', scrollToTop);

        // Initial check
        toggleButtonVisibility();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScrollToTop);
    } else {
        initScrollToTop();
    }
})();
