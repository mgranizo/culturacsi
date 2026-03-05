<?php
/**
 * One-time MU plugin migrations.
 *
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'culturacsi_run_pending_mu_migrations', 5 );

/**
 * Run pending one-time data updates.
 *
 * @return void
 */
function culturacsi_run_pending_mu_migrations() {
	$done_key = '__update_snippet_14_done';
	$status   = (string) get_option( $done_key, '0' );

	if ( '1' === $status || 'skipped' === $status ) {
		return;
	}

	global $wpdb;
	if ( ! ( $wpdb instanceof wpdb ) ) {
		return;
	}

	$table_name = $wpdb->prefix . 'snippets';

	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
	if ( $table_name !== $table_exists ) {
		update_option( $done_key, 'skipped', false );
		return;
	}

	// Identify the snippet by its name (stable across environments) rather than
	// by auto-increment ID, which can differ between local and production DBs.
	// The snippet name contains 'calendario' or 'calendar' hero overlay code.
	$target_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
			"SELECT id FROM {$table_name} WHERE snippet_name LIKE %s ORDER BY id ASC LIMIT 1",
			'%calendar%'
		)
	);

	if ( $target_id <= 0 ) {
		// Snippet not found by name. Skip safely and log for visibility.
		$fallback_id = 14;
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(1) FROM {$table_name} WHERE id = %d", $fallback_id )
		);
		if ( $exists > 0 ) {
			$target_id = $fallback_id;
		} else {
			error_log( '[culturacsi migrations] Migration ' . $done_key . ': snippet not found by name or ID. Skipping.' );
			update_option( $done_key, 'skipped', false );
			return;
		}
	}

	$new_code = <<<'EOT'
add_action('wp_footer', function() {
    if (!is_page('calendario') && !is_page('calendar')) return;

    $m_val = absint(isset($_GET['ev_m']) ? $_GET['ev_m'] : (isset($_GET['m']) ? $_GET['m'] : 0));
    $y_val = absint(isset($_GET['ev_y']) ? $_GET['ev_y'] : (isset($_GET['y']) ? $_GET['y'] : 0));
    $current_month = $m_val > 0 ? $m_val : (int) date('n');
    $current_year  = $y_val > 0 ? $y_val  : (int) date('Y');
    $month_name = mb_strtoupper((string) date_i18n( 'F', mktime( 0, 0, 0, $current_month, 1, $current_year ) ), 'UTF-8');
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // === CONFIGURATION ================== //
            const CONFIG = {
                textWidthMultiplier: 1.0,      
                textVerticalShift: -2.2,       
                blueTintOpacity: 0.6,          
                heroImageHeightCrop: 1.5       
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

	$updated = $wpdb->update(
		$table_name,
		array( 'code' => $new_code ),
		array( 'id' => $target_id ),
		array( '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return;
	}

	delete_transient( 'code_snippets' );
	delete_transient( 'code_snippets_active_snippets' );
	update_option( $done_key, '1', false );
}
