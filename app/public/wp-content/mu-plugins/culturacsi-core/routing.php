<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action(
	'template_redirect',
	static function() {
		$uri_path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$path     = trim( (string) $uri_path, '/' );

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
		);

		if ( isset( $aliases[ $path ] ) ) {
			$target = home_url( $aliases[ $path ] );
			$query  = isset( $_SERVER['QUERY_STRING'] ) ? trim( (string) $_SERVER['QUERY_STRING'] ) : '';
			if ( $query !== '' ) {
				$target .= ( strpos( $target, '?' ) === false ? '?' : '&' ) . $query;
			}
			wp_safe_redirect( $target, 302 );
			exit;
		}
	},
	1
);

/**
 * Reserved-area hard fallback shortcodes.
 * These keep the frontend portal operational even if plugin shortcode registration breaks.
 */
