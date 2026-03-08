<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize the current frontend request path for redirect rules.
 */
function culturacsi_routing_current_path(): string {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	return trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
}

/**
 * Preserve the raw query string on alias redirects.
 */
function culturacsi_routing_current_query_string(): string {
	return isset( $_SERVER['QUERY_STRING'] ) ? trim( (string) wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
}

add_action(
	'template_redirect',
	static function() {
		$path = culturacsi_routing_current_path();

		// Single entry point for all dashboard access.
		if ( 'area-riservata' === $path ) {
			$is_auth_post = (
				'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) &&
				isset( $_POST['assoc_auth_action'] )
			);
			if ( $is_auth_post ) {
				// Let assoc_reserved_access shortcode process login/register/recovery POST first.
				return;
			}

			if ( ! is_user_logged_in() ) {
				$auth = isset( $_GET['auth'] ) ? sanitize_key( wp_unslash( $_GET['auth'] ) ) : 'login';
				if ( ! in_array( $auth, array( 'login', 'register', 'recover' ), true ) ) {
					$auth = 'login';
				}
				$assoc_notice = isset( $_GET['assoc_notice'] ) ? sanitize_key( wp_unslash( $_GET['assoc_notice'] ) ) : '';
				$args = array(
					'area_riservata_login' => '1',
					'auth' => $auth,
				);
				if ( $assoc_notice !== '' ) {
					$args['assoc_notice'] = $assoc_notice;
				}
				wp_safe_redirect(
					add_query_arg(
						$args,
						home_url( '/' )
					),
					302
				);
				exit;
			}

			if ( current_user_can( 'manage_options' ) ) {
				wp_safe_redirect( home_url( '/area-riservata/notizie/' ), 302 );
				exit;
			}

			if ( current_user_can( 'association_manager' ) ) {
				wp_safe_redirect( home_url( '/area-riservata/profilo-utente/' ), 302 );
				exit;
			}

			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		if ( 'area-riservata/amministrazione' === $path || 'area-riservata/amministrazione/' === $path ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( home_url( '/area-riservata/' ), 302 );
				exit;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_safe_redirect( home_url( '/area-riservata/' ), 302 );
				exit;
			}

			// Always use a real WP page (editable in Pages).
			$admin_area_page = get_page_by_path( 'area-riservata/amministrazione', OBJECT, 'page' );
			if ( $admin_area_page instanceof WP_Post ) {
				return;
			}

			// No hardcoded fallback rendering.
			wp_safe_redirect( home_url( '/area-riservata/' ), 302 );
			exit;
		}

		// Backward-compat: old calendar links used y/m; map them to ev_y/ev_m to avoid WP 404 on "m".
		if ( in_array( $path, array( 'calendar', 'calendario', 'events' ), true ) && ( isset( $_GET['m'] ) || isset( $_GET['y'] ) ) ) {
			$query_args = array();
			foreach ( (array) $_GET as $key => $value ) {
				if ( is_array( $value ) ) {
					continue;
				}
				$query_args[ sanitize_key( (string) $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}

			if ( ! isset( $query_args['ev_m'] ) && isset( $query_args['m'] ) ) {
				$query_args['ev_m'] = $query_args['m'];
			}
			if ( ! isset( $query_args['ev_y'] ) && isset( $query_args['y'] ) ) {
				$query_args['ev_y'] = $query_args['y'];
			}
			unset( $query_args['m'], $query_args['y'] );

			$target_path = 'calendario' === $path ? '/calendar/' : '/' . $path . '/';
			$target      = add_query_arg( $query_args, home_url( $target_path ) );
			wp_safe_redirect( $target, 302 );
			exit;
		}

		$aliases = array(
			'notizie'                 => '/news/',
			'calendario'              => '/calendar/',
			'sevizi'                  => '/servizi/',
			'dashboard'               => '/area-riservata/',
			'area-riservata/admin'    => '/area-riservata/amministrazione/',
			'area-riservata/eventi/modifica' => '/area-riservata/eventi/nuovo/',
			'area-riservata/utenti/modifica' => '/area-riservata/utenti/nuovo/',
			'area-riservata/associazioni/modifica' => '/area-riservata/associazioni/nuova/',
			// Backward compatibility from legacy dashboard paths to editable reserved-area pages.
			'dashboard/events'        => '/area-riservata/eventi/',
			'dashboard/news'          => '/area-riservata/notizie/',
			'dashboard/news/new'      => '/area-riservata/notizie/nuova/',
			'dashboard/users'         => '/area-riservata/utenti/',
			'dashboard/users/new'     => '/area-riservata/utenti/nuovo/',
			'dashboard/users/edit'    => '/area-riservata/utenti/nuovo/',
			'dashboard/associations'  => '/area-riservata/associazioni/',
			'dashboard/associations/new' => '/area-riservata/associazioni/nuova/',
			'dashboard/associations/edit' => '/area-riservata/associazioni/nuova/',
			'dashboard/profile'       => '/area-riservata/profilo/',
			'dashboard/user-profile'  => '/area-riservata/profilo-utente/',
			'dashboard/association'   => '/area-riservata/associazione/',
			'dashboard/events/new'    => '/area-riservata/eventi/nuovo/',
			'dashboard/events/edit'   => '/area-riservata/eventi/nuovo/',
			'dashboard/cronologia'    => '/area-riservata/cronologia/',
			'cronologia'              => '/area-riservata/cronologia/',
		);

		if ( isset( $aliases[ $path ] ) ) {
			$target = home_url( $aliases[ $path ] );
			$target_path = trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' );
			if ( $target_path === $path ) {
				return;
			}
			$query = culturacsi_routing_current_query_string();
			if ( $query !== '' ) {
				$target .= ( strpos( $target, '?' ) === false ? '?' : '&' ) . $query;
			}
			wp_safe_redirect( $target, 302 );
			exit;
		}
	},
	1
);

add_filter(
	'template_include',
	static function( $template ) {
		if ( ! is_post_type_archive( 'news' ) ) {
			return $template;
		}

		$custom_template = WP_CONTENT_DIR . '/themes/culturacsi/archive-news.php';
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}

		return $template;
	},
	20
);

/**
 * Reserved-area hard fallback shortcodes.
 * These keep the frontend portal operational even if plugin shortcode registration breaks.
 */
