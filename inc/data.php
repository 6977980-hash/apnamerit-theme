<?php
/**
 * JSON data loader. All heavy content comes from data/*.json so it can be updated through git.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load and cache a JSON file from data/. $name may include a sub-path, e.g. "universities/uet".
 *
 * @return array Decoded data, or an empty array if the file is missing/invalid.
 */
function am_data( string $name ): array {
	static $cache = array();
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}
	$name = preg_replace( '/[^a-z0-9_\/-]/i', '', $name );
	$file = AM_DIR . '/data/' . $name . '.json';
	$data = array();
	if ( is_readable( $file ) ) {
		$decoded = json_decode( (string) file_get_contents( $file ), true );
		if ( is_array( $decoded ) ) {
			$data = $decoded;
		}
	}
	$cache[ $name ] = $data;
	return $data;
}

/**
 * Read a value from data/site.json using dot notation, e.g. am_site( 'author.name' ).
 */
function am_site( string $key, $fallback = '' ) {
	$value = am_data( 'site' );
	foreach ( explode( '.', $key ) as $part ) {
		if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
			return $fallback;
		}
		$value = $value[ $part ];
	}
	return $value;
}
