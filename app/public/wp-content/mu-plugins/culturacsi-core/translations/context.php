<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Translation context and locale wiring.
 *
 * This module is intentionally first in the load order because every later
 * translation layer depends on a stable current-language decision.
 *
 * Multi-language support: Italian, Spanish, English, French.
 * Language is determined by URL path, query parameter, or cookie.
 */
function culturacsi_get_current_language(): string {
	static $cookie_written = false;

	// Check query parameter first (takes priority)
	if ( isset( $_GET['lang'] ) ) {
		$lang = sanitize_text_field( wp_unslash( $_GET['lang'] ) );

		$allowed = array( 'it', 'es', 'en', 'fr' );
		if ( in_array( $lang, $allowed, true ) ) {
			// Persist language once per request to avoid duplicating Set-Cookie headers.
			if ( ! $cookie_written && ( ! isset( $_COOKIE['culturacsi_lang'] ) || $_COOKIE['culturacsi_lang'] !== $lang ) ) {
				$cookie_expires = time() + ( 30 * DAY_IN_SECONDS );
				$cookie_secure  = is_ssl();
				if ( PHP_VERSION_ID >= 70300 ) {
					setcookie(
						'culturacsi_lang',
						$lang,
						array(
							'expires'  => $cookie_expires,
							'path'     => '/',
							'secure'   => $cookie_secure,
							'httponly' => true,
							'samesite' => 'Lax',
						)
					);
				} else {
					// Fallback for older PHP versions.
					setcookie( 'culturacsi_lang', $lang, $cookie_expires, '/; samesite=Lax', '', $cookie_secure, true );
				}
				$cookie_written = true;
			}
			// Keep current-request reads consistent after switching via query string.
			$_COOKIE['culturacsi_lang'] = $lang;
			return $lang;
		}
	}
	
	// Check cookie if no query parameter
	if ( isset( $_COOKIE['culturacsi_lang'] ) ) {
		$lang = sanitize_text_field( wp_unslash( $_COOKIE['culturacsi_lang'] ) );
		$allowed = array( 'it', 'es', 'en', 'fr' );
		if ( in_array( $lang, $allowed, true ) ) {
			return $lang;
		}
	}
	
	// Check URL path
	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

	if ( preg_match( '#^(it|es|en|fr)(/|$)#', $path, $matches ) ) {

		return $matches[1];
	}
	
	// Default to Italian
	return 'it';
}

/**
 * Get the locale for the current language.
 *
 * @return string WordPress locale code (e.g., 'it_IT', 'es_ES', 'en_US', 'fr_FR')
 */
function culturacsi_get_current_locale(): string {
	$current_lang = culturacsi_get_current_language();
	$lang_locale_map = array(
		'it' => 'it_IT',
		'es' => 'es_ES',
		'en' => 'en_US',
		'fr' => 'fr_FR',
	);
	return $lang_locale_map[ $current_lang ] ?? 'it_IT';
}

// Store current language globally for use throughout the plugin
// Note: this is set once at file load time; filters use culturacsi_get_current_language() directly for accuracy.
$GLOBALS['culturacsi_current_lang'] = culturacsi_get_current_language();

/**
 * Prevent full-page cache from serving stale language variants.
 */
add_action(
	'init',
	static function() {
		if ( isset( $_GET['lang'] ) || isset( $_COOKIE['culturacsi_lang'] ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! defined( 'DONOTCACHEDB' ) ) {
				define( 'DONOTCACHEDB', true );
			}
			if ( ! defined( 'DONOTMINIFY' ) ) {
				define( 'DONOTMINIFY', true );
			}
		}
	},
	1
);

add_action(
	'template_redirect',
	static function() {
		if ( isset( $_GET['lang'] ) || isset( $_COOKIE['culturacsi_lang'] ) ) {
			nocache_headers();
			header( 'Vary: Cookie, Accept-Language', false );
		}
	},
	1
);

add_filter(
	'pre_option_WPLANG',
	static function() {
		return culturacsi_get_current_locale();
	}
);

add_filter(
	'locale',
	static function() {
		return culturacsi_get_current_locale();
	},
	20
);

add_filter(
	'determine_locale',
	static function() {
		return culturacsi_get_current_locale();
	},
	20
);

add_filter(
	'language_attributes',
	static function( $output ) {
		$current_lang = culturacsi_get_current_language();
		$lang_map = array(
			'it' => 'it-IT',
			'es' => 'es',
			'en' => 'en-US',
			'fr' => 'fr-FR',
		);
		$lang_attr = $lang_map[ $current_lang ] ?? 'it-IT';
		return preg_replace( '/lang=("|\')[^\'"]+("|\')/i', 'lang="' . $lang_attr . '"', $output );
	},
	20
);

// Hide the frontend WordPress admin bar for all logged-in users.
add_filter(
	'show_admin_bar',
	static function( $show ) {
		if ( is_admin() ) {
			return $show;
		}
		return false;
	},
	20
);
