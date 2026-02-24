<?php
define('WP_USE_THEMES', false);
require_once dirname(__FILE__) . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'snippets';

$snippet_id = 14;

$new_code = <<< 'EOT'
add_action('wp_footer', function() {
    if (!is_page('calendario') && !is_page('calendar')) return;

    $m_val = isset($_GET['ev_m']) ? $_GET['ev_m'] : (isset($_GET['m']) ? $_GET['m'] : date('n'));
    $y_val = isset($_GET['ev_y']) ? $_GET['ev_y'] : (isset($_GET['y']) ? $_GET['y'] : date('Y'));
    $current_month = intval($m_val) > 0 ? intval($m_val) : date('n');
    $current_year = intval($y_val) > 0 ? intval($y_val) : date('Y');
    $month_name = mb_strtoupper((string) date_i18n( 'F', mktime( 0, 0, 0, $current_month, 1, $current_year ) ), 'UTF-8');
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // === CONFIGURATION ================== //
            const CONFIG = {
                textWidthMultiplier: 1.0,      // 1.0 means exactly 100% width. Change to 1.1 to make it 10% larger.
                textVerticalShift: -2.2,       // Shift text up or down
                blueTintOpacity: 0.60,         // Opacity of the blue overlay
                heroImageHeightCrop: 1.5       // 1.5 creates a panoramic 2/3 height crop
            };
            // ==================================== //

            const heroText = "<?php echo esc_js($month_name); ?>";
            const entryContent = document.querySelector('.entry-content');
            
            if (entryContent) {
                let heroCandidate = entryContent.querySelector('.wp-block-image, .wp-block-kadence-image, .kb-image-wrap');
                if (heroCandidate && heroCandidate.tagName === 'IMG') heroCandidate = heroCandidate.parentElement;
                
                if (!heroCandidate || heroCandidate.closest('.assoc-portal-calendar-browser')) {
                    heroCandidate = entryContent.querySelector('.kb-row-layout-wrap');
                }

                if (heroCandidate) {
                    heroCandidate.style.position = 'relative';
                    heroCandidate.style.overflow = 'hidden';
                    heroCandidate.style.display = 'block';

                    const heroImg = heroCandidate.tagName === 'IMG' ? heroCandidate : heroCandidate.querySelector('img');
                    if (heroImg) {
                        heroImg.style.width = '100%';
                        const fixImageHeight = () => {
                            if (heroImg.naturalWidth && heroImg.naturalHeight) {
                                const naturalAspect = heroImg.naturalWidth / heroImg.naturalHeight;
                                heroImg.style.aspectRatio = (naturalAspect * CONFIG.heroImageHeightCrop).toString();
                                heroImg.style.objectFit = 'cover';
                            }
                        };
                        if (heroImg.complete) fixImageHeight();
                        else heroImg.addEventListener('load', fixImageHeight);
                    }

                    const tintOverlay = document.createElement('div');
                    tintOverlay.className = 'calendar-hero-tint-overlay';
                    tintOverlay.style.cssText = "position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background-color: rgba(17, 89, 175, " + CONFIG.blueTintOpacity + ") !important; z-index: 5 !important; pointer-events: none !important;";
                    heroCandidate.appendChild(tintOverlay);

                    const textOverlay = document.createElement('div');
                    textOverlay.className = 'calendar-hero-month-overlay';
                    textOverlay.style.cssText = "position: absolute !important; left: 50% !important; bottom: " + CONFIG.textVerticalShift + "% !important; width: 100% !important; text-align: center !important; font-family: 'Raleway', 'Roboto', sans-serif !important; font-weight: 800 !important; color: #ffffff !important; text-transform: uppercase !important; z-index: 10 !important; pointer-events: none !important; margin: 0 !important; padding: 0 !important; text-shadow: none !important; white-space: nowrap !important; transform: translateX(-50%) !important; display: flex; justify-content: center;";
                    
                    const textSpan = document.createElement('span');
                    textSpan.style.display = 'inline-block'; 
                    textSpan.style.transformOrigin = 'bottom center';
                    textSpan.style.lineHeight = '0.73'; 
                    textSpan.style.marginLeft = '-0.03em'; 
                    textSpan.style.marginRight = '-0.03em';
                    textSpan.textContent = heroText;
                    
                    textOverlay.appendChild(textSpan);
                    heroCandidate.appendChild(textOverlay);

                    const resizeText = () => {
                        textSpan.style.transform = 'scale(1)'; 
                        textSpan.style.fontSize = '100px'; 
                        requestAnimationFrame(() => {
                            const containerWidth = heroCandidate.clientWidth;
                            const textRect = textSpan.getBoundingClientRect();
                            if (textRect.width > 0 && containerWidth > 0) {
                                const widthRatio = containerWidth / textRect.width;
                                const finalScale = widthRatio * CONFIG.textWidthMultiplier;
                                textSpan.style.transform = "scale(" + finalScale + ")";
                            }
                        });
                    };
                    
                    resizeText(); 
                    window.addEventListener('resize', resizeText); 
                    setTimeout(resizeText, 500);
                    if (document.fonts && document.fonts.ready) {
                        document.fonts.ready.then(resizeText);
                    }
                    if (window.ResizeObserver) {
                        new ResizeObserver(resizeText).observe(heroCandidate);
                    }
                }
            }
        });
    </script>
    <?php
});
EOT;

$result = $wpdb->update($table_name, ['code' => $new_code], ['id' => $snippet_id]);
if ($result === false) {
    echo "Error updating snippet: " . $wpdb->last_error . "\n";
} else {
    echo "Successfully updated snippet 14\n";
    // Also update snippet cache if running Code Snippets
    delete_transient('code_snippets');
    delete_transient('code_snippets_active_snippets');
}
?>
