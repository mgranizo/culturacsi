<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI asset helpers.
 *
 * This file now acts as the loader/orchestrator for frontend portal admin UX.
 * Large reserved-area and submit-modal CSS/JS live in `assets/` so future
 * maintainers can update behavior without digging through mixed PHP templates.
 */
function culturacsi_admin_ui_asset_path( string $relative_path ): string {
	return __DIR__ . '/assets/' . ltrim( $relative_path, '/' );
}

function culturacsi_admin_ui_asset_url( string $relative_path ): string {
	return content_url( 'mu-plugins/culturacsi-core/assets/' . ltrim( $relative_path, '/' ) );
}

/**
 * Return a cache-busting version string for an admin UI asset.
 */
function culturacsi_admin_ui_asset_version( string $relative_path ): ?string {
	$asset_path = culturacsi_admin_ui_asset_path( $relative_path );

	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	return (string) filemtime( $asset_path );
}

/**
 * Normalize the current request path once so route checks stay consistent.
 */
function culturacsi_current_request_path(): string {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	return trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
}

function culturacsi_is_reserved_frontend_request(): bool {
	if ( is_admin() ) {
		return false;
	}

	$path = culturacsi_current_request_path();
	return 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' );
}

/**
 * Load reserved-area presentation and progressive-enhancement assets.
 *
 * Scope matters here: the selectors are intentionally broad because the portal
 * shortcodes share class names across many screens, so we only enqueue them on
 * reserved-area routes.
 */
function culturacsi_enqueue_reserved_frontend_assets(): void {
	if ( ! culturacsi_is_reserved_frontend_request() ) {
		return;
	}

	wp_enqueue_style(
		'culturacsi-admin-ui-reserved',
		culturacsi_admin_ui_asset_url( 'admin-ui-reserved.css' ),
		array(),
		culturacsi_admin_ui_asset_version( 'admin-ui-reserved.css' )
	);

	wp_enqueue_script(
		'culturacsi-admin-ui-reserved',
		culturacsi_admin_ui_asset_url( 'admin-ui-reserved.js' ),
		array(),
		culturacsi_admin_ui_asset_version( 'admin-ui-reserved.js' ),
		true
	);

	wp_localize_script(
		'culturacsi-admin-ui-reserved',
		'culturacsiAdminUiReserved',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'modalNonce' => wp_create_nonce( 'culturacsi_portal_ajax' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'culturacsi_enqueue_reserved_frontend_assets', 40 );

/**
 * Load the submit-status modal assets for frontend portal forms.
 *
 * This stays separate from reserved-area assets because portal forms can exist
 * outside the reserved dashboard, while the collapsible search/table UI cannot.
 */
function culturacsi_enqueue_submit_modal_assets(): void {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'culturacsi-admin-ui-submit-modal',
		culturacsi_admin_ui_asset_url( 'admin-ui-submit-modal.css' ),
		array(),
		culturacsi_admin_ui_asset_version( 'admin-ui-submit-modal.css' )
	);

	wp_enqueue_script(
		'culturacsi-admin-ui-submit-modal',
		culturacsi_admin_ui_asset_url( 'admin-ui-submit-modal.js' ),
		array(),
		culturacsi_admin_ui_asset_version( 'admin-ui-submit-modal.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'culturacsi_enqueue_submit_modal_assets', 41 );

/**
 * Load shared wp-admin CSS for the custom portal maintenance tools.
 *
 * This replaces scattered inline admin `<style>` output with one versioned asset
 * that other modules can extend via `wp_add_inline_style()`.
 */
function culturacsi_enqueue_admin_backend_assets(): void {
	if ( ! is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'culturacsi-admin-ui-admin',
		culturacsi_admin_ui_asset_url( 'admin-ui-admin.css' ),
		array(),
		culturacsi_admin_ui_asset_version( 'admin-ui-admin.css' )
	);

	wp_enqueue_script(
		'culturacsi-admin-ui-admin',
		culturacsi_admin_ui_asset_url( 'admin-ui-admin.js' ),
		array(),
		culturacsi_admin_ui_asset_version( 'admin-ui-admin.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'culturacsi_enqueue_admin_backend_assets', 20 );

/**
 * Legacy inline reserved-area renderer kept temporarily for rollback safety.
 *
 * It is intentionally no longer hooked; the active implementation lives in
 * `assets/admin-ui-reserved.css` and `assets/admin-ui-reserved.js`.
 */
function culturacsi_portal_reserved_inline_styles(): void {
	if ( is_admin() ) {
		return;
	}
	$path = culturacsi_current_request_path();
	if ( 'area-riservata' !== $path && 0 !== strpos( $path, 'area-riservata/' ) ) {
		return;
	}
	?>
		<style id="culturacsi-reserved-inline">
			.assoc-reserved-nav,
			.assoc-search-panel,
			.assoc-portal-section{
				box-sizing:border-box;
				width:100%;
			}

			/* Reserved pages: keep predictable spacing and allow editor Spacer/Separator blocks
			   to control distance between shortcode blocks. */
			body.assoc-reserved-page .entry-content > .wp-block-shortcode + .wp-block-shortcode,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-shortcode + .wp-block-shortcode{
				margin-top:1rem;
			}
			body.assoc-reserved-page .entry-content > .wp-block-separator,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-separator{
				margin:1.25rem auto;
			}
			body.assoc-reserved-page .entry-content > .wp-block-spacer,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-spacer{
				margin:0;
			}
			body.assoc-reserved-page .entry-content > .wp-block-shortcode + .wp-block-spacer,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-shortcode + .wp-block-spacer,
			body.assoc-reserved-page .entry-content > .wp-block-shortcode + .wp-block-separator,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-shortcode + .wp-block-separator,
			body.assoc-reserved-page .entry-content > .wp-block-spacer + .wp-block-shortcode,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-spacer + .wp-block-shortcode,
			body.assoc-reserved-page .entry-content > .wp-block-separator + .wp-block-shortcode,
			body.assoc-reserved-page .entry-content .wp-block-group > .wp-block-separator + .wp-block-shortcode{
				margin-top:0;
			}
			.assoc-reserved-nav{position:relative;}
			.assoc-reserved-nav-head{position:relative;display:block;margin-bottom:4px;min-height:42px}
			.assoc-reserved-nav-brand{display:flex;align-items:center;justify-content:center;width:min(300px,92%);margin:0 auto 12px;padding:0;border:0;background:transparent}
			.assoc-reserved-nav-brand img{display:block;width:100%;height:auto;max-height:69px;object-fit:contain}
		.assoc-reserved-nav-title-wrap{text-align:center;margin:0 0 14px}
		.assoc-reserved-nav-title{display:block;font-size:1.96rem;font-weight:900;letter-spacing:.03em;text-transform:uppercase;color:#0f172a;line-height:1.1}
		.assoc-reserved-nav-subtitle{display:inline-flex;align-items:center;justify-content:center;margin-top:6px;padding:5px 10px;border-radius:999px;font-size:.83rem;font-weight:700;letter-spacing:.02em;color:#0b3d91;background:#eaf2ff;border:1px solid #c7daf8}
		.assoc-reserved-nav-logout{text-decoration:none;font-size:.9rem;font-weight:700;color:#0b3d91;border:1px solid #0b3d91;border-radius:999px;padding:7px 12px;background:#fff}
		.assoc-reserved-nav-head .assoc-reserved-nav-logout{position:absolute;right:0;top:0}
		.assoc-reserved-nav-logout:hover,.assoc-reserved-nav-logout:focus{background:#0b3d91;color:#fff}
			.assoc-reserved-nav-list{display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:0 0 8px;padding:0;justify-content:center;align-items:flex-end;border-bottom:1px solid #c5d2e2}
			.assoc-reserved-nav-list li{margin:0 0 -1px;padding:0;list-style:none}
			.assoc-reserved-nav-link{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 22px;border-radius:10px 10px 0 0;border:1px solid #c5d2e2;background:#eef4fb;color:#1f2937;font-size:.96rem;font-weight:700;text-decoration:none;transition:background-color .16s ease,color .16s ease,border-color .16s ease}
			.assoc-reserved-nav-link:hover,.assoc-reserved-nav-link:focus{background:#f8fbff;border-color:#9eb8df;color:#0b3d91}
			.assoc-reserved-nav-link.is-dark-tab{background:#d7e0ec;border-color:#9caac0;color:#1f2f46}
			.assoc-reserved-nav-link.is-dark-tab:hover,.assoc-reserved-nav-link.is-dark-tab:focus{background:#c8d4e4;border-color:#7f91ab;color:#122948}
			.assoc-reserved-nav-link.is-active{background:#fff;border-color:#9eb8df;border-bottom-color:#fff;color:#0b3d91;box-shadow:inset 0 3px 0 #0b3d91;position:relative;z-index:2}
			.assoc-reserved-nav-link.is-dark-tab.is-active{border-color:#7f91ab;border-bottom-color:#fff;color:#0b3d91;box-shadow:inset 0 3px 0 #334155}
		.assoc-portal-section{width:100%;max-width:100%;}
		.assoc-page-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 12px}
		.assoc-page-title{margin:0;font-size:1.5rem;line-height:1.2;color:#0f172a}
		.assoc-search-panel{margin-bottom:12px;}
		.assoc-search-panel.assoc-search-collapsible{border:1px solid #d5e0ef;border-radius:12px;background:#f8fbff;padding:10px 12px}
		.assoc-search-panel.assoc-search-collapsible .assoc-search-toggle{min-height:34px;padding:0 12px;border-radius:8px;border:1px solid #8ea7c7;background:#e6eef9;color:#0b3d91;font-weight:700}
		.assoc-search-panel.assoc-search-collapsible .assoc-search-toggle:hover,.assoc-search-panel.assoc-search-collapsible .assoc-search-toggle:focus{background:#dbe8f8;border-color:#6f90ba}
		.assoc-search-panel.assoc-search-collapsible:not(.is-open) > :not(.assoc-search-head){display:none !important}
		.assoc-search-panel.assoc-search-collapsible:not(.is-open) .assoc-search-head{margin-bottom:0}
		.assoc-search-panel.assoc-search-collapsible:not(.is-open) .assoc-search-actions a.button{display:none}
		.assoc-search-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin:0 0 10px}
		.assoc-search-meta{min-width:0}
		.assoc-search-title{margin:0 0 6px;font-size:1.04rem;color:#0f172a}
		.assoc-search-count{margin:0;color:#0f172a;font-weight:700;font-size:.9rem}
		.assoc-search-form{display:grid;grid-auto-flow:row;gap:10px 10px;align-items:end}
		.assoc-search-field{margin:0;grid-column:auto;min-width:0}
		.assoc-search-field label{display:block;margin:0 0 3px;font-weight:700;color:#334155}
		.assoc-search-field input,.assoc-search-field select{width:100%;min-height:36px;padding:7px 10px;border:1px solid #c7d3e4;border-radius:8px;background:#fff}
		.assoc-search-field select{-webkit-appearance:none;-moz-appearance:none;appearance:none;padding-right:34px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%23355a86' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;background-size:12px 8px}
			.assoc-search-actions{margin:0;display:flex;flex-wrap:nowrap;gap:10px;align-items:center;justify-content:flex-end}
			.assoc-search-actions .button{display:inline-flex;align-items:center;justify-content:center;min-height:36px;height:36px;white-space:nowrap;padding:0 14px;margin:0 !important;line-height:1}
			.assoc-events-search .assoc-search-form{grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr))}
			.assoc-events-search .assoc-search-field{grid-row:1}
			.assoc-events-search .assoc-search-field.is-q{grid-column:1}
			.assoc-events-search .assoc-search-field.is-date{grid-column:2}
			.assoc-events-search .assoc-search-field.is-author{grid-column:3}
			.assoc-events-search .assoc-search-field.is-status{grid-column:4}
			/* Notizie: align sizing with Calendar (Eventi) */
			.assoc-news-search .assoc-search-form{grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr))}
			.assoc-news-search .assoc-search-field{grid-row:1}
			.assoc-news-search .assoc-search-field.is-q{grid-column:1}
			.assoc-news-search .assoc-search-field.is-date{grid-column:2}
			.assoc-news-search .assoc-search-field.is-author{grid-column:3}
			.assoc-news-search .assoc-search-field.is-status{grid-column:4}
			/* Place Associazione on a second row, full width for clarity */
			.assoc-news-search .assoc-search-field.is-association{grid-row:2;grid-column:1 / span 2}
			.assoc-users-search .assoc-search-form{grid-template-columns:repeat(3,minmax(0,1fr))}
			.assoc-users-search .assoc-search-field{grid-row:1}
			.assoc-users-search .assoc-search-field.is-q{grid-column:1}
			.assoc-users-search .assoc-search-field.is-role{grid-column:2}
			.assoc-users-search .assoc-search-field.is-status{grid-column:3}
			/* Cronologia: two compact fields side by side, each 1/4 width */
			.assoc-cronologia-search .assoc-search-form{grid-template-columns:repeat(4,minmax(0,1fr))}
			.assoc-cronologia-search .assoc-search-field{grid-row:1}
			.assoc-cronologia-search .assoc-search-field.is-type{grid-column:1}
			.assoc-cronologia-search .assoc-search-field.is-q{grid-column:2}
			.assoc-associations-search .assoc-search-form{grid-template-columns:repeat(6,minmax(0,1fr))}
			.assoc-associations-search .assoc-search-field{grid-row:1}
			.assoc-associations-search .assoc-search-field.is-q{grid-column:1}
			.assoc-associations-search .assoc-search-field.is-category{grid-column:2}
			.assoc-associations-search .assoc-search-field.is-province{grid-column:3}
			.assoc-associations-search .assoc-search-field.is-region{grid-column:4}
			.assoc-associations-search .assoc-search-field.is-city{grid-column:5}
			.assoc-associations-search .assoc-search-field.is-status{grid-column:6}
		.assoc-portal-events-list,.assoc-portal-users-list,.assoc-portal-associations-list{width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:0 6px;box-sizing:border-box;border-radius:10px}
		.assoc-admin-table{width:100%;border:1px solid #d9e2ec;table-layout:fixed;min-width:640px}
		.assoc-admin-table thead th{background:#eff4fb;color:#0f172a;font-weight:800}
		.assoc-admin-table thead th .assoc-admin-sort-link{display:inline-flex;align-items:center;justify-content:center;gap:4px;width:100%;color:inherit;text-decoration:none;font-weight:inherit}
		.assoc-admin-table thead th.assoc-col-title .assoc-admin-sort-link,
		.assoc-admin-table thead th.assoc-col-category .assoc-admin-sort-link,
		.assoc-admin-table thead th.assoc-col-email .assoc-admin-sort-link,
		.assoc-admin-table thead th.assoc-col-role .assoc-admin-sort-link{justify-content:flex-start}
		.assoc-admin-table thead th .assoc-admin-sort-link:hover,.assoc-admin-table thead th .assoc-admin-sort-link:focus{color:#0b3d91;text-decoration:none}
		.assoc-admin-table thead th .assoc-admin-sort-link.is-active{color:#0b3d91}
		.assoc-admin-sort-indicator{font-size:.78em;line-height:1;opacity:.8}
		.assoc-admin-table th,.assoc-admin-table td{vertical-align:middle;padding:8px 9px}
		.assoc-admin-table th.assoc-col-index,.assoc-admin-table td.assoc-col-index{text-align:center;padding-left:4px;padding-right:4px;white-space:nowrap}
		.assoc-admin-table th.assoc-col-date,.assoc-admin-table td.assoc-col-date,
		.assoc-admin-table th.assoc-col-status,.assoc-admin-table td.assoc-col-status,
		.assoc-admin-table th.assoc-col-ext,.assoc-admin-table td.assoc-col-ext,
		.assoc-admin-table th.assoc-col-actions,.assoc-admin-table td.assoc-col-actions{text-align:center;white-space:nowrap}
			.assoc-admin-table td.assoc-col-title{font-weight:800}
			.assoc-admin-table th.assoc-col-title,.assoc-admin-table td.assoc-col-title{word-break:break-word;overflow-wrap:anywhere}
			.assoc-table-events th.assoc-col-title,.assoc-table-events td.assoc-col-title,
			.assoc-table-news th.assoc-col-title,.assoc-table-news td.assoc-col-title,
			.assoc-table-assocs th.assoc-col-title,.assoc-table-assocs td.assoc-col-title{width:40% !important}
			.assoc-table-users th:nth-child(1),.assoc-table-users td:nth-child(1){width:4ch !important}
			.assoc-user-list-thumb{display:inline-block;vertical-align:middle;margin-right:8px;width:32px;height:32px;border-radius:999px;overflow:hidden;border:1px solid #d1dbe5;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
			.assoc-user-list-thumb img{display:block;width:100%;height:100%;object-fit:cover}
			.assoc-user-list-name{display:inline-block;vertical-align:middle;max-width:calc(100% - 45px);white-space:normal;line-height:1.2}
			.assoc-table-users td.assoc-col-title{vertical-align:middle}
			.assoc-table-users{table-layout:fixed !important}
			.assoc-table-users th:nth-child(2),.assoc-table-users td:nth-child(2){width:27% !important}
			.assoc-table-users th:nth-child(3),.assoc-table-users td:nth-child(3){width:24% !important}
			.assoc-table-users th:nth-child(4),.assoc-table-users td:nth-child(4){width:11% !important;min-width:7rem}
			.assoc-table-users th:nth-child(5),.assoc-table-users td:nth-child(5){width:6rem !important}
			.assoc-table-users th:nth-child(6),.assoc-table-users td:nth-child(6){width:120px !important}
			.assoc-table-users th:nth-child(7),.assoc-table-users td:nth-child(7){width:6rem !important}
			.assoc-table-users th.assoc-col-date,.assoc-table-users td.assoc-col-date{min-width:6rem}
			.assoc-table-users td.assoc-col-email{word-break:break-all;overflow-wrap:anywhere;white-space:normal;line-height:1.2;font-size:12px}
			.assoc-table-users td.assoc-col-role{word-break:normal;overflow-wrap:break-word;white-space:normal;line-height:1.2;font-size:12px}
			.assoc-table-users th.assoc-col-actions,.assoc-table-users td.assoc-col-actions{min-width:120px;padding-left:4px;padding-right:4px}
			.assoc-table-users .assoc-action-group{grid-template-columns:3.2rem 3.2rem;justify-content:center}
			.assoc-table-users .assoc-action-chip{width:3.2rem;font-size:10px;padding:0 2px}
			.assoc-admin-table th.assoc-col-category,.assoc-admin-table td.assoc-col-category{word-break:break-word;overflow-wrap:anywhere}
			.assoc-admin-table td.assoc-col-category .assoc-category-activities{display:block;font-weight:800;color:#0f172a}
			.assoc-association-name{display:block;font-weight:800}
			.assoc-association-location{display:block;margin-top:2px;font-size:.78rem;line-height:1.25;font-weight:600;color:#475569;word-break:break-word;overflow-wrap:anywhere}
		.assoc-admin-table td.assoc-col-actions{vertical-align:middle;padding-top:10px;padding-bottom:10px}
		.assoc-admin-table tbody tr:nth-child(odd) td{background:#eef5ff}
		.assoc-admin-table tbody tr:nth-child(even) td{background:#fff}
		.assoc-admin-table tbody tr:hover td{background:#e3eeff}
		.assoc-pagination{margin:10px 0 4px}
		.assoc-pagination-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
		.assoc-pagination-item{margin:0;padding:0}
		.assoc-pagination-item a,.assoc-pagination-item span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:32px;padding:0 9px;border-radius:8px;border:1px solid #c9d7ea;background:#fff;color:#0b3d91;text-decoration:none;font-weight:700;font-size:.86rem;line-height:1}
		.assoc-pagination-item a:hover,.assoc-pagination-item a:focus{background:#edf3ff;border-color:#9db8df}
		.assoc-pagination-item.is-current span,.assoc-pagination-item .current{background:#0b3d91;border-color:#0b3d91;color:#fff}
			.assoc-action-group{display:grid;grid-template-columns:3.2rem 3.2rem;grid-template-rows:minmax(1.65rem,auto) minmax(1.65rem,auto);column-gap:4px;row-gap:5px;justify-content:center;justify-items:center;align-content:center;align-items:center}
			.assoc-action-group>a.assoc-action-chip{grid-column:1;grid-row:1;place-self:center}
			.assoc-action-group>form.assoc-row-action-form:not(.is-toggle){grid-column:2;grid-row:1;place-self:center}
			.assoc-action-group>form.assoc-row-action-form.is-toggle{grid-column:1 / -1;grid-row:2;place-self:center}
			.assoc-row-action-form{display:flex;align-items:center;justify-content:center;margin:0 !important;padding:0 !important;border:0 !important;width:auto;min-height:1.65rem;height:auto}
			.assoc-action-chip{display:inline-flex;align-items:center;justify-content:center;width:3.2rem;height:1.65rem;padding:0 4px;border-radius:7px;border:1px solid #c9d7ea;background:#fff;color:#0b3d91;font-size:10px;font-weight:700;line-height:1;text-decoration:none;white-space:nowrap;cursor:pointer;transition:all .14s ease;box-sizing:border-box;vertical-align:middle}
			a.assoc-action-chip{line-height:1 !important;padding:0 4px !important}
			.assoc-row-action-form .assoc-action-chip{margin:0 !important}
			button.assoc-action-chip{-webkit-appearance:none;appearance:none;display:inline-flex !important;align-items:center !important;justify-content:center !important;font-family:inherit;line-height:1 !important;height:1.65rem !important;min-height:1.65rem !important;padding:0 6px !important;margin:0 !important;position:static !important;top:auto !important;transform:none !important;vertical-align:middle !important}
			.assoc-action-chip:hover,.assoc-action-chip:focus{background:#edf3ff;border-color:#9db8df;color:#0b3d91}
		.assoc-action-chip.chip-edit{border-color:#86aee0;background:#eaf3ff;color:#0b3d91}
		.assoc-action-chip.chip-delete{border-color:#b91c1c;background:#dc2626;color:#fff}
		.assoc-action-chip.chip-delete:hover,.assoc-action-chip.chip-delete:focus{background:#b91c1c;border-color:#991b1b;color:#fff}
		.assoc-action-chip.chip-approve{border-color:#9ad4b3;background:#ebfaef;color:#166534}
		.assoc-action-chip.chip-reject{border-color:#efb0b0;background:#fff2f2;color:#991b1b}
		.assoc-action-chip.chip-hold{border-color:#efcf8d;background:#fff8e8;color:#7a4b0e}
		.assoc-status-pill{display:inline-flex;align-items:center;justify-content:center;min-width:5.4rem;height:1.55rem;padding:0 10px;border-radius:999px;border:1px solid #cad5e4;background:#f3f7fd;color:#1f2937;font-weight:700;font-size:11px;line-height:1;white-space:nowrap}
		.assoc-status-pill.status-publish,.assoc-status-pill.status-approved{border-color:#9ad4b3;background:#ebfaef;color:#166534}
		.assoc-status-pill.status-pending,.assoc-status-pill.status-hold{border-color:#efcf8d;background:#fff8e8;color:#7a4b0e}
		.assoc-status-pill.status-draft{border-color:#d5dbe6;background:#f5f7fb;color:#475569}
		.assoc-status-pill.status-private,.assoc-status-pill.status-admin{border-color:#c9d6ea;background:#eef4ff;color:#1e3a8a}
		.assoc-status-pill.status-rejected{border-color:#efb0b0;background:#fff2f2;color:#991b1b}

		.assoc-portal-form p{margin:0 0 12px}
		.assoc-portal-form label{display:block;margin:0 0 4px;font-weight:600}
		.assoc-portal-form input[type="text"],.assoc-portal-form input[type="email"],.assoc-portal-form input[type="url"],.assoc-portal-form input[type="password"],.assoc-portal-form input[type="datetime-local"],.assoc-portal-form textarea,.assoc-portal-form select{width:100%;max-width:none;min-height:40px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
		.assoc-user-avatar-preview{display:block;width:96px;height:96px;border-radius:999px;object-fit:cover;border:1px solid #cbd5e1;background:#fff}
		.assoc-avatar-remove label{display:inline-flex;align-items:center;gap:8px;font-weight:600}
		.assoc-portal-events-list .button,.assoc-portal-form .button{background:#0b3d91;border-color:#0b3d91;color:#fff}
		.assoc-portal-events-list .button:hover,.assoc-portal-form .button:hover{background:#0a347c;border-color:#0a347c}
		.assoc-admin-notice{margin:0 0 12px;border-radius:10px;padding:12px 14px;text-align:center;font-weight:700;border:2px solid #d1deef;background:#f7faff;color:#0f172a}
			.assoc-admin-notice-success{border-color:#8bcaa5;background:#e8f8ee;color:#14532d}
			.assoc-admin-notice-warning{border-color:#f5be62;background:#fff7e8;color:#7c4303}
			.assoc-admin-notice-error{border-color:#dc2626;background:#fee2e2;color:#991b1b}
			@media (max-width: 1100px){
				.assoc-events-search .assoc-search-form{grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr))}
				.assoc-news-search .assoc-search-form{grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr))}
				.assoc-users-search .assoc-search-form{grid-template-columns:repeat(3,minmax(0,1fr))}
				.assoc-associations-search .assoc-search-form{grid-template-columns:repeat(3,minmax(0,1fr))}
				.assoc-cronologia-search .assoc-search-form{grid-template-columns:repeat(4,minmax(0,1fr))}
				.assoc-associations-search .assoc-search-field.is-q{grid-column:1}
				.assoc-associations-search .assoc-search-field.is-category{grid-column:2}
				.assoc-associations-search .assoc-search-field.is-province{grid-column:3}
				.assoc-associations-search .assoc-search-field.is-region{grid-column:1}
				.assoc-associations-search .assoc-search-field.is-city{grid-column:2}
				.assoc-associations-search .assoc-search-field.is-status{grid-column:3}
			}
			@media (max-width: 700px){
				.assoc-reserved-nav-head .assoc-reserved-nav-logout{position:static;display:inline-flex;margin:0 auto 8px}
				.assoc-reserved-nav-head{text-align:center}
			.assoc-reserved-nav-brand{width:min(220px,90%);margin:0 auto 10px}
			.assoc-reserved-nav-title{font-size:1.64rem}
			.assoc-page-toolbar{flex-direction:column;align-items:stretch}
			.assoc-search-head{flex-direction:column;align-items:stretch}
			.assoc-page-toolbar .button{width:100%}
			.assoc-page-toolbar > div{flex-direction:column;width:100%}
			.assoc-page-toolbar > div .button{width:100%;text-align:center;justify-content:center}
			.assoc-search-form{grid-template-columns:minmax(0,1fr) !important}
			.assoc-search-field{grid-column:1 / -1 !important;grid-row:auto !important;min-width:0 !important}
			.assoc-search-actions{justify-content:flex-start}
			.assoc-action-chip{width:4.2rem}
			/* Table mobile scroll: force explicit width so table overflows container and scroll kicks in */
			.assoc-portal-events-list,.assoc-portal-users-list,.assoc-portal-associations-list{overflow-x:auto;-webkit-overflow-scrolling:touch;padding:0}
			.assoc-admin-table{width:800px !important;min-width:800px !important;table-layout:auto !important}
			.assoc-table-users{width:900px !important;min-width:900px !important}
			}

		/* Modal Styles */
		.assoc-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000; align-items: center; justify-content: center; padding: 20px; }
		.assoc-modal.is-open { display: flex; }
		.assoc-modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
		.assoc-modal-container { position: relative; background: #fff; width: 100%; max-width: 650px; max-height: 90vh; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; display: flex; flex-direction: column; animation: assocModalSlideUp 0.3s ease-out; }
		@keyframes assocModalSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
		.assoc-modal-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
		.assoc-modal-title { margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; }
		.assoc-modal-close { background: transparent; border: 0; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; display: flex; align-items: center; justify-content: center; }
		.assoc-modal-close:hover { background: #e2e8f0; }
		.assoc-modal-close::before { content: "\2715"; font-size: 1.2rem; color: #64748b; font-weight: bold; }
		.assoc-modal-content { padding: 24px; overflow-y: auto; flex-grow: 1; color: #334155; }
		.assoc-modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: center; gap: 12px; }

		/* Details Styles in Modal */
		.assoc-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
		.assoc-details-item { margin-bottom: 14px; }
		.assoc-details-label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px; letter-spacing: 0.025em; }
		.assoc-details-value { font-size: 0.95rem; color: #0f172a; font-weight: 500; word-break: break-word; }
		.assoc-details-full { grid-column: 1 / -1; }
		/* Keep admin row-details readable even if theme CSS overrides inline span defaults. */
		.assoc-row-details .assoc-details-label { display: block !important; color: #0b3d91 !important; font-weight: 700 !important; margin: 0 0 4px 0 !important; text-transform: none !important; }
		.assoc-row-details .assoc-details-value { display: block !important; color: #0f172a !important; line-height: 1.35 !important; }
		.assoc-modal-avatar { width: 80px; height: 80px; border-radius: 999px; object-fit: cover; margin-bottom: 15px; border: 3px solid #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
		
		.assoc-modal-loader { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-radius: 50%; border-top-color: #0b3d91; animation: assocSpin 0.8s linear infinite; margin: 40px auto; }
		@keyframes assocSpin { to { transform: rotate(360deg); } }

		/* Clickable rows */
		.assoc-admin-table tbody tr { cursor: pointer; transition: background 0.1s; }
		.assoc-admin-table tbody tr:hover td { background-color: #f1f7ff !important; }
		.assoc-admin-table td.assoc-col-actions { position: relative; z-index: 5; } /* Actions should stay clickable separately */
		.assoc-action-group { position: relative; z-index: 10; }
		/* Inline row details (dropdown) */
		.assoc-row-details td{ background:#f8fafc !important; padding:0 !important; }
		.assoc-row-details-inner{ padding:16px 18px; }
		.assoc-row-details-footer{ padding:12px 18px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:center; gap:12px; }
		@media (max-width: 640px) {
			.assoc-details-grid { grid-template-columns: 1fr; }
		}
	</style>
	<script id="culturacsi-reserved-autosubmit">
		document.addEventListener('DOMContentLoaded', function() {
			// Ensure AJAX nonce is available even if modal HTML is not rendered
			try { if (!window.assocPortalNonce) { window.assocPortalNonce = "<?php echo esc_js( wp_create_nonce( 'culturacsi_portal_ajax' ) ); ?>"; } } catch(e) {}
			const searchPanels = document.querySelectorAll('.assoc-search-panel');
			searchPanels.forEach(function(panel) {
				if (!panel || panel.dataset.csiCollapsible === '1') { return; }
				const head = panel.querySelector('.assoc-search-head');
				if (!head) { return; }
				let actions = head.querySelector('.assoc-search-actions');
				if (!actions) {
					actions = document.createElement('p');
					actions.className = 'assoc-search-actions';
					head.appendChild(actions);
				}
				const toggle = document.createElement('button');
				toggle.type = 'button';
				toggle.className = 'assoc-search-toggle';
				toggle.textContent = 'Apri ricerca';
				toggle.setAttribute('aria-expanded', 'false');
				toggle.addEventListener('click', function() {
					const willOpen = !panel.classList.contains('is-open');
					panel.classList.toggle('is-open', willOpen);
					toggle.textContent = willOpen ? 'Chiudi ricerca' : 'Apri ricerca';
					toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
				});
				actions.insertBefore(toggle, actions.firstChild);
				panel.classList.add('assoc-search-collapsible');
				panel.classList.remove('is-open');
				panel.dataset.csiCollapsible = '1';
			});
			const searchForms = document.querySelectorAll('.assoc-search-form');
			searchForms.forEach(form => {
				const inputs = form.querySelectorAll('input, select');
				let debounceTimer;
				
				inputs.forEach(input => {
					input.addEventListener('change', () => form.submit());
					if (input.type === 'text') {
						input.addEventListener('input', () => {
							clearTimeout(debounceTimer);
							debounceTimer = setTimeout(() => form.submit(), 600);
						});
					}
				});
			});

			/* Inline Row Details (Dropdown expansion) */
			function removeOpenDetailsInTbody(tbody){
				const open = tbody.querySelectorAll('tr.assoc-row-details');
				open.forEach(el => el.remove());
			}
			function toggleRowDetails(row){
				const id = row.getAttribute('data-id');
				const type = row.getAttribute('data-type');
				if(!id || !type) return;
				const next = row.nextElementSibling;
				if(next && next.classList.contains('assoc-row-details')){ next.remove(); return; }
				const tbody = row.parentElement;
				removeOpenDetailsInTbody(tbody);
				const colSpan = row.children.length || 8;
				const cache = (window.assocRowCache = window.assocRowCache || {});
				const cacheKey = `${type}:${id}`;
				const detailsRow = document.createElement('tr');
				detailsRow.className = 'assoc-row-details';
				detailsRow.setAttribute('data-for-id', id);
				detailsRow.innerHTML = `<td colspan="${colSpan}"><div class="assoc-row-details-inner"><div class=\"assoc-modal-loader\"></div></div><div class=\"assoc-row-details-footer\" id=\"assoc-row-details-footer-${id}\"></div></td>`;
				row.insertAdjacentElement('afterend', detailsRow);
				// Serve from cache if available
				if (cache[cacheKey]){
					const inner = detailsRow.querySelector('.assoc-row-details-inner');
					const footer = detailsRow.querySelector(`#assoc-row-details-footer-${id}`);
					inner.innerHTML = cache[cacheKey].html;
					footer.innerHTML = cache[cacheKey].footer || '';
					// Normalize labels if needed (e.g., Attività)
					try{ inner.querySelectorAll('.assoc-details-label').forEach(l=>{ const t=(l.textContent||'').trim(); if(t.startsWith('Attivit')) l.textContent='Attività'; }); }catch(e){}
					return;
				}
				fetch(`/wp-admin/admin-ajax.php?action=culturacsi_get_modal_data&id=${id}&type=${type}&nonce=${window.assocPortalNonce}`)
					.then(r=>r.json())
					.then(res=>{
						const inner = detailsRow.querySelector('.assoc-row-details-inner');
						const footer = detailsRow.querySelector(`#assoc-row-details-footer-${id}`);
						if(res && res.success){
							inner.innerHTML = res.data.html;
							footer.innerHTML = res.data.footer || '';
							cache[cacheKey] = { html: res.data.html, footer: res.data.footer || '' };
							try{ inner.querySelectorAll('.assoc-details-label').forEach(l=>{ const t=(l.textContent||'').trim(); if(t.startsWith('Attivit')) l.textContent='Attività'; }); }catch(e){}
						}else{
							inner.innerHTML = `<p style=\"padding:20px;text-align:center;color:#ef4444;\">${res?.data || 'Errore di caricamento.'}</p>`;
							footer.innerHTML = '';
						}
					})
					.catch(()=>{
						const inner = detailsRow.querySelector('.assoc-row-details-inner');
						inner.innerHTML = `<p style=\"padding:20px;text-align:center;color:#ef4444;\">Errore di caricamento.</p>`;
					});
			}
			document.addEventListener('click', (e)=>{
				const row = e.target.closest('.assoc-admin-table tbody tr[data-id]');
				if(!row) return;
				if(e.target.closest('.assoc-action-group') || e.target.closest('a') || e.target.closest('button')) return;
				toggleRowDetails(row);
			});
		});
	</script>
	<?php
}

/**
 * Show site logo in the WordPress backend admin top bar.
 */
function culturacsi_admin_bar_logo( WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! is_admin() ) {
		return;
	}

	$logo_url = culturacsi_get_site_logo_url( 'thumbnail', true );
	if ( '' === $logo_url ) {
		return;
	}

	$wp_admin_bar->remove_node( 'wp-logo' );
	$wp_admin_bar->add_node(
		array(
			'id'    => 'culturacsi-logo',
			'title' => '<span class="ab-icon"><img src="' . esc_url( $logo_url ) . '" alt="" /></span><span class="ab-label">' . esc_html( get_bloginfo( 'name' ) ) . '</span>',
			'href'  => admin_url(),
			'meta'  => array(
				'class' => 'culturacsi-adminbar-logo',
				'title' => get_bloginfo( 'name' ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'culturacsi_admin_bar_logo', 8 );

function culturacsi_admin_bar_logo_styles(): void {
	?>
	<style id="culturacsi-adminbar-logo-style">
		#wpadminbar #wp-admin-bar-culturacsi-logo > .ab-item {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding-right: 12px;
		}
		#wpadminbar #wp-admin-bar-culturacsi-logo > .ab-item .ab-icon {
			padding: 0;
			margin: 0;
			width: 22px;
			height: 22px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
		}
		#wpadminbar #wp-admin-bar-culturacsi-logo > .ab-item .ab-icon img {
			width: 22px;
			height: 22px;
			border-radius: 3px;
			object-fit: cover;
			display: block;
		}
		#wpadminbar #wp-admin-bar-culturacsi-logo > .ab-item .ab-label {
			font-weight: 600;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'culturacsi_admin_bar_logo_styles' );

/**
 * External URL support for News CPT (admin + frontend behavior).
 */
function culturacsi_news_external_url_meta_box(): void {
	add_meta_box(
		'culturacsi_news_external_url',
		'URL Notizia Originale',
		static function( WP_Post $post ): void {
			wp_nonce_field( 'culturacsi_news_external_url_save', 'culturacsi_news_external_url_nonce' );
			$url = (string) get_post_meta( $post->ID, '_hebeae_external_url', true );
			echo '<label for="culturacsi_news_external_url_field" style="display:block;font-weight:600;margin-bottom:6px">URL Esterno</label>';
			echo '<input type="url" id="culturacsi_news_external_url_field" name="culturacsi_news_external_url_field" value="' . esc_attr( $url ) . '" placeholder="https://..." style="width:100%;">';
			echo '<p class="description">Se impostato, i link frontend per questa notizia apriranno il sito originale.</p>';
		},
		'news',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_news', 'culturacsi_news_external_url_meta_box' );

function culturacsi_news_external_url_save( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['culturacsi_news_external_url_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_news_external_url_nonce'] ) ), 'culturacsi_news_external_url_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$url = isset( $_POST['culturacsi_news_external_url_field'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['culturacsi_news_external_url_field'] ) ) ) : '';
	if ( '' !== $url ) {
		update_post_meta( $post_id, '_hebeae_external_url', $url );
		update_post_meta( $post_id, '_hebeae_external_enabled', '1' );
	} else {
		delete_post_meta( $post_id, '_hebeae_external_url' );
		update_post_meta( $post_id, '_hebeae_external_enabled', '0' );
	}
}
add_action( 'save_post_news', 'culturacsi_news_external_url_save' );

/**
 * One-time repair: ensure existing news with external URL are marked enabled
 * so the existing Hebeae admin column/link behavior works.
 */
function culturacsi_news_backfill_external_enabled_once(): void {
	if ( get_option( 'culturacsi_news_external_backfill_done' ) ) {
		return;
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'news',
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_hebeae_external_url',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	if ( ! empty( $query->posts ) ) {
		foreach ( $query->posts as $news_id ) {
			$url = (string) get_post_meta( (int) $news_id, '_hebeae_external_url', true );
			if ( '' !== trim( $url ) ) {
				update_post_meta( (int) $news_id, '_hebeae_external_enabled', '1' );
			}
		}
	}
	update_option( 'culturacsi_news_external_backfill_done', 1, false );
}
add_action( 'init', 'culturacsi_news_backfill_external_enabled_once', 200 );


/**
 * Add custom avatar field to standard WordPress user profile page.
 */
function culturacsi_admin_user_avatar_field( $user ) {
    $avatar_id = (int) get_user_meta( $user->ID, 'assoc_user_avatar_id', true );
    ?>
    <div class="culturacsi-admin-avatar-section">
        <h3>CulturaCSI: Foto Profilo</h3>
        <table class="form-table">
            <tr>
                <th><label>Foto attuale</label></th>
                <td>
                    <?php if ( $avatar_id > 0 ) : ?>
                        <div class="culturacsi-admin-avatar-current">
                            <?php echo wp_get_attachment_image( $avatar_id, array( 96, 96 ) ); ?>
                        </div>
                        <label><input type="checkbox" name="remove_assoc_user_avatar" value="1"> Rimuovi foto attuale</label>
                    <?php else : ?>
                        <p>Nessuna foto caricata.</p>
                    <?php endif; ?>
                    <div class="culturacsi-admin-avatar-upload">
                        <input type="file" name="assoc_user_avatar_upload" accept="image/*">
                        <p class="description">Carica una nuova foto per questo utente. Verrà usata nell'Area Riservata.</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
add_action( 'show_user_profile', 'culturacsi_admin_user_avatar_field' );
add_action( 'edit_user_profile', 'culturacsi_admin_user_avatar_field' );

function culturacsi_admin_save_user_avatar_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['remove_assoc_user_avatar'] ) && '1' === $_POST['remove_assoc_user_avatar'] ) {
        delete_user_meta( $user_id, 'assoc_user_avatar_id' );
    }

    if ( ! empty( $_FILES['assoc_user_avatar_upload']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $attachment_id = media_handle_upload( 'assoc_user_avatar_upload', 0 );
        if ( ! is_wp_error( $attachment_id ) ) {
            update_user_meta( $user_id, 'assoc_user_avatar_id', (int) $attachment_id );
        }
    }
}
add_action( 'personal_options_update', 'culturacsi_admin_save_user_avatar_field' );
add_action( 'edit_user_profile_update', 'culturacsi_admin_save_user_avatar_field' );

/**
 * Remove the built-in Posts ("Articoli") menu from the admin sidebar.
 * The site uses custom CPTs (Associazioni, Eventi, Notizie) instead.
 */
function culturacsi_remove_unused_admin_menus(): void {
	remove_menu_page( 'edit.php' ); // Posts / Articoli
}
add_action( 'admin_menu', 'culturacsi_remove_unused_admin_menus', 999 );


/**
 * Render the lightweight submit-status modal shell.
 *
 * The modal content is intentionally empty here. JavaScript owns the status
 * states so PHP only needs to provide a stable DOM target in the footer.
 */
function culturacsi_portal_render_submit_modal_shell(): void {
	if ( is_admin() ) {
		return;
	}
	?>
	<div id="assoc-submit-modal" class="assoc-modal assoc-submit-modal" aria-hidden="true">
		<div class="assoc-modal-overlay"></div>
		<div class="assoc-modal-container" role="dialog" aria-modal="true" aria-labelledby="assoc-submit-modal-title">
			<div class="assoc-modal-content">
				<span id="assoc-submit-modal-title" class="screen-reader-text"><?php echo esc_html__( 'Stato invio modulo', 'culturacsi' ); ?></span>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'culturacsi_portal_render_submit_modal_shell' );
