<?php
/**
 * IndexNow (owner approved indexing on 2026-10-31).
 *
 *   /<key>.txt        key file (text/plain), key from data/site.json → indexnow.key
 *   after a deploy    pages whose pages.json entry, content file or university data changed are
 *                     submitted to api.indexnow.org in a WP-Cron event (never on a visitor's request).
 *                     Result of the last submission: option am_indexnow_last.
 */

defined( 'ABSPATH' ) || exit;

/** IndexNow key, or '' when disabled. */
function am_indexnow_key(): string {
	$key = (string) am_site( 'indexnow.key' );
	return am_site( 'indexnow.enabled' ) && preg_match( '/^[a-f0-9]{32}$/', $key ) ? $key : '';
}

/**
 * Content hash of every indexable repo page: entry + content file + data of the university it shows.
 *
 * @return array<string, string> path => md5
 */
function am_indexnow_hashes(): array {
	$by_path = array();
	foreach ( am_unis() as $slug => $uni ) {
		foreach ( array( 'calculator_path', 'meritlist_path' ) as $k ) {
			if ( ! empty( $uni[ $k ] ) ) {
				$by_path[ (string) $uni[ $k ] ][] = (string) @file_get_contents( AM_DIR . '/data/universities/' . $slug . '.json' );
			}
		}
	}
	$out = array();
	foreach ( (array) ( am_data( 'pages' )['pages'] ?? array() ) as $p ) {
		$path = (string) ( $p['path'] ?? '' );
		if ( '' === $path || ! empty( $p['noindex'] ) ) {
			continue;
		}
		$file         = ! empty( $p['content_file'] ) ? AM_DIR . '/content/' . basename( (string) $p['content_file'] ) : '';
		$out[ $path ] = md5( wp_json_encode( $p ) . ( $file ? (string) @file_get_contents( $file ) : '' ) . implode( '', $by_path[ $path ] ?? array() ) . am_site( 'season_year' ) . am_site( 'data_year' ) );
	}
	return $out;
}

/** After a deploy: queue the changed URLs. */
function am_indexnow_queue(): void {
	if ( ! am_indexnow_key() ) {
		return;
	}
	$now  = am_indexnow_hashes();
	$old  = (array) get_option( 'am_indexnow_hashes', array() );
	$urls = array();
	foreach ( $now as $path => $hash ) {
		if ( ( $old[ $path ] ?? '' ) !== $hash ) {
			$urls[] = untrailingslashit( am_site_url() ) . $path;
		}
	}
	update_option( 'am_indexnow_hashes', $now, false );
	if ( $urls ) {
		wp_schedule_single_event( time(), 'am_indexnow_send', array( array_slice( $urls, 0, 10000 ) ) );
	}
}
add_action( 'am_deployed', 'am_indexnow_queue' );

/** WP-Cron: POST the URL list to IndexNow. */
function am_indexnow_send( array $urls ): void {
	$key = am_indexnow_key();
	if ( ! $key || ! $urls ) {
		return;
	}
	$base = untrailingslashit( am_site_url() );
	$res  = wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode(
				array(
					'host'        => (string) wp_parse_url( $base, PHP_URL_HOST ),
					'key'         => $key,
					'keyLocation' => $base . '/' . $key . '.txt',
					'urlList'     => array_values( $urls ),
				)
			),
		)
	);
	update_option(
		'am_indexnow_last',
		array(
			'time' => gmdate( 'c' ),
			'urls' => count( $urls ),
			'code' => is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ),
		),
		false
	);
}
add_action( 'am_indexnow_send', 'am_indexnow_send' );

// Key file.
add_action(
	'template_redirect',
	function () {
		$key = am_indexnow_key();
		if ( ! $key || '/' . $key . '.txt' !== am_request_path() ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: no-store' );
		$last = (array) get_option( 'am_indexnow_last', array() );
		$last && header( 'X-AM-IndexNow-Last: ' . preg_replace( '/[^\w :.,+-]/', '', implode( ', ', array_map( 'strval', $last ) ) ) );
		echo esc_html( $key );
		exit;
	},
	-2
);
