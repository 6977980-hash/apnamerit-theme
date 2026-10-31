<?php
/**
 * Repo-defined pages. The owner decided (2026-10-31) that pages are NOT created in wp-admin:
 * every URL is an entry in data/pages.json and the theme serves it directly. Push to git →
 * Hostinger deploys → the page exists. No WP-CLI, no database rows.
 *
 * Entry fields: see the _note in data/pages.json. Rendering: am_render_page() in inc/template-tags.php.
 */

defined( 'ABSPATH' ) || exit;

/** Allowed values for an entry's "template" field. */
const AM_PAGE_TEMPLATES = array( 'default', 'calculator', 'meritlist', 'home' );

/**
 * Normalised path of the current request ("/", "/uet-merit-list/"), query string stripped.
 */
function am_request_path(): string {
	$uri  = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( $home && '/' !== $home && str_starts_with( $uri, $home ) ) {
		$uri = '/' . substr( $uri, strlen( $home ) );
	}
	return '/' . ltrim( rawurldecode( $uri ), '/' );
}

/**
 * Find a pages.json entry by exact path.
 */
function am_find_page( string $path ): array {
	foreach ( (array) ( am_data( 'pages' )['pages'] ?? array() ) as $page ) {
		if ( is_array( $page ) && ( $page['path'] ?? null ) === $path ) {
			return $page;
		}
	}
	return array();
}

/**
 * The repo page being served on this request (not the homepage), or an empty array.
 */
function am_virtual_page(): array {
	static $page = null;
	if ( null === $page ) {
		$path = am_request_path();
		$page = ( '/' === $path || is_admin() ) ? array() : am_find_page( $path );
	}
	return $page;
}

// Don't let WordPress mark a repo page as 404.
add_filter( 'pre_handle_404', fn( $handled ) => am_virtual_page() ? true : $handled );

add_action(
	'template_redirect',
	function () {
		$path = am_request_path();
		// Missing trailing slash on a repo page → 301 to the slashed URL.
		if ( '/' !== $path && ! str_ends_with( $path, '/' ) && am_find_page( $path . '/' ) ) {
			$qs = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
			wp_safe_redirect( home_url( $path . '/' ) . $qs, 301 );
			exit;
		}
		$page = am_virtual_page();
		if ( ! $page ) {
			return;
		}
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );
		include AM_DIR . '/templates/virtual.php';
		exit;
	},
	0
);

/**
 * "Updated" date for an entry: its "updated" field, else pages.json mtime.
 */
function am_page_updated( array $page ): string {
	if ( ! empty( $page['updated'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $page['updated'] ) ) {
		return $page['updated'];
	}
	return gmdate( 'Y-m-d', (int) @filemtime( AM_DIR . '/data/pages.json' ) );
}

// Sitemap: list repo pages (except noindex) in /wp-sitemap.xml.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once AM_DIR . '/inc/class-am-sitemap-provider.php';
		wp_register_sitemap_provider( 'ampages', new AM_Sitemap_Provider() );
	}
);
