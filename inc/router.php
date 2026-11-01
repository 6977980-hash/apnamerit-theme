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
		$qs   = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// Any other host name (www.apnamerit.com) → 301 to the canonical host (audit CI-01: the
		// router used to answer www with 200 before WordPress' redirect_canonical could run).
		$want = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$host = strtolower( (string) preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $want && '' !== $host && $host !== $want && 'www.' . $want === $host ) {
			wp_redirect( home_url( $path ) . $qs, 301 ); // phpcs:ignore WordPress.Security.SafeRedirect -- same site, canonical host
			exit;
		}
		// Missing trailing slash on a repo page → 301 to the slashed URL.
		if ( '/' !== $path && ! str_ends_with( $path, '/' ) && am_find_page( $path . '/' ) ) {
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
		// Date the page's content last changed (audit CI-08); LiteSpeed's ETag still covers template changes.
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) strtotime( am_page_updated( $page ) . ' 00:00:00 +0500' ) ) . ' GMT' );
		include AM_DIR . '/templates/virtual.php';
		exit;
	},
	0
);

/**
 * "Updated" date for an entry: its "updated" field, else its "published" date, else pages.json
 * mtime. With "updated_from_data": true, the newest "updated" of the data it shows wins when it
 * is later (audit F-05): a college page reads only its own university file (the uni="..." in its
 * content), a data report reads every file. Deploys never move the date (audit CQ-04).
 */
function am_page_updated( array $page ): string {
	$ymd  = fn( $d ) => is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d );
	$date = $ymd( $page['updated'] ?? null ) ? (string) $page['updated'] : ( $ymd( $page['published'] ?? null ) ? (string) $page['published'] : '' );
	if ( ! empty( $page['updated_from_data'] ) && function_exists( 'am_unis' ) ) {
		$only = preg_match( '/uni="([a-z0-9-]+)"/', (string) ( $page['content'] ?? '' ), $m ) && ! empty( $page['college'] ) ? $m[1] : '';
		foreach ( am_unis() as $uni ) {
			if ( '' !== $only && ( $uni['slug'] ?? '' ) !== $only ) {
				continue;
			}
			$u = (string) ( $uni['updated'] ?? '' );
			$ymd( $u ) && $u > $date && ( $date = $u );
		}
	}
	return '' !== $date ? $date : gmdate( 'Y-m-d', (int) @filemtime( AM_DIR . '/data/pages.json' ) );
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
