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
	foreach ( array( 'data/*.json', 'data/*/*.json', 'assets/css/*.css', 'assets/js/*.js', '*.php', 'inc/*.php', 'templates/*.php' ) as $glob ) {
		foreach ( (array) glob( AM_DIR . '/' . $glob ) as $file ) {
			$parts[] = $file . ':' . (int) @filemtime( $file ) . ':' . (int) @filesize( $file );
		}
	}
	return md5( implode( '|', $parts ) );
}

add_action(
	'init',
	function () {
		$now = am_deploy_fingerprint();
		if ( get_option( 'am_deploy_fingerprint' ) === $now ) {
			return;
		}
		update_option( 'am_deploy_fingerprint', $now, true );
		do_action( 'litespeed_purge_all' ); // No-op if LiteSpeed Cache is not active.
		wp_cache_flush();
	},
	1
);
