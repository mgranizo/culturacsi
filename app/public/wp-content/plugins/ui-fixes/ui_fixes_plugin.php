<?php
/* Plugin Name: UI Fixes Plugin 
   Description: Fixes Chi Siamo menu active state and Calendar month duplicate
*/
add_action('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fix Progetti menu active state on Chi Siamo (dynamic class matching)
        if (window.location.href.indexOf('/chi-siamo') !== -1) {
            const menuItems = document.querySelectorAll('li.menu-item');
            menuItems.forEach(li => {
                const link = li.querySelector('a');
                if (link && link.textContent.trim() === 'Progetti') {
                    li.classList.remove('current-menu-item', 'current-menu-ancestor');
                }
            });
        }

        // Remove duplicate FEBBRAIO text under calendar hero
        setTimeout(function() {
            const overlay = document.querySelector('.calendar-hero-month-overlay');
            if (overlay) {
                const parent = overlay.parentElement;
                if (parent) {
                    // Hide any headings or spans that contain only FEBBRAIO but are not the overlay
                    const monthTexts = Array.from(parent.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span')).filter(el => {
                        return !el.classList.contains('calendar-hero-month-overlay') && 
                               !el.closest('.calendar-hero-month-overlay') &&
                               el.textContent.trim().toUpperCase() === 'FEBBRAIO';
                    });
                    monthTexts.forEach(el => el.style.display = 'none');
                    
                    // Also clear any text nodes acting as duplicates
                    Array.from(parent.childNodes).forEach(node => {
                        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim().toUpperCase() === 'FEBBRAIO') {
                            node.textContent = '';
                        }
                    });
                }
            }
        }, 1000); // Wait a bit for other scripts
    });
    </script>
    <?php
});
