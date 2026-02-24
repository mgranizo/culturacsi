/**
 * Cultura ACSI Theme JavaScript
 * 
 * @package CulturaCSI
 */

(function() {
    'use strict';

    /**
     * Initialize theme functionality
     */
    function init() {
        // Mobile menu toggle (if needed in future)
        initMobileMenu();
        
        // Smooth scroll for anchor links
        initSmoothScroll();
        
        // Initialize any other functionality
    }

    /**
     * Initialize mobile menu toggle
     */
    function initMobileMenu() {
        // Placeholder for mobile menu functionality
        // Can be expanded when mobile menu is needed
    }

    /**
     * Initialize smooth scroll for anchor links
     */
    function initSmoothScroll() {
        const anchorLinks = document.querySelectorAll('a[href^="#"]');
        anchorLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
