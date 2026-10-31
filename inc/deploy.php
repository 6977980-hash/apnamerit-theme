<?php
/**
 * Auto cache purge after a Hostinger Git deploy.
 *
 * Pages and data come from files, so LiteSpeed would keep serving old HTML after a push.
 * On the first request after the theme files change, purge LiteSpeed once.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fingerprint of the deployed theme: mtimes + sizes of data, assets and PHP.
 */
function am_deploy_fingerprint(): string {
	$parts = array( AM_VERSION );
	foreach ( array( 'data/*.json', 'data/*/*.json', 'content/*.html', 'assets/css/*.css', 'assets/js/*.js', '*.php', 'inc/*.php', 'templates/*.php' ) as $glob ) {
		foreach ( (array) glob( AM_DIR . '/' . $glob ) as $file ) {
			$parts[] = $file . ':' . (int) @filemtime( $file ) . ':' . (int) @filesize( $file );
		}
	}
	return md5( implode( '|', $parts ) );
}

// wp_loaded: the LiteSpeed Cache plugin has registered its listeners by then.
add_action(
	'wp_loaded',
	function () {
		$now = am_deploy_fingerprint();
		if ( get_option( 'am_deploy_fingerprint' ) === $now ) {
			return;
		}
		update_option( 'am_deploy_fingerprint', $now, true );
		// The server itself honours this header, with or without the plugin.
		if ( ! headers_sent() ) {
			header( 'X-LiteSpeed-Purge: *' );
		}
		do_action( 'litespeed_purge_all' );
		// The Hostinger plugin clears its CDN (hCDN) when LiteSpeed reports a full purge; LiteSpeed
		// only queues ours, so announce it ourselves. Without the plugin nothing listens.
		did_action( 'litespeed_purged_all' ) || do_action( 'litespeed_purged_all' );
		wp_cache_flush();
		do_action( 'am_deployed' );
	}
);

/**
 * HTML cache lifetime for shared caches (Fix 6, 2026-11-01). Without a Cache-Control header,
 * Hostinger's CDN (hCDN) kept serving pages from before a deploy for 10+ minutes after
 * LiteSpeed had been purged. s-maxage=300 caps that at five minutes; LiteSpeed's own page cache
 * still answers the CDN's revalidation, so speed does not change. Browsers always revalidate.
 */
add_action(
	'send_headers',
	function () {
		if ( is_admin() || is_user_logged_in() || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}
		header( 'Cache-Control: public, max-age=0, s-maxage=300' );
	}
);
