<?php
/**
 * CLI test (run by scripts/check.py): for every university dataset, the predictor reference row
 * of each program must equal the merit table's default view for that program: newest year
 * (up to the university's year), default list, default category. The value and the printed
 * year must come from the same record (Fix 1, FIX-BRIEF 2026-11-01: KEMC printed 2024's 96.06
 * with a 2025 label).
 *
 * Usage: php scripts/tests/predict_rows.php   (exit 0 = pass, 1 = fail)
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'AM_DIR', dirname( __DIR__, 2 ) );

// Minimal WordPress stubs: the files only register hooks at load time.
foreach ( array( 'add_action', 'add_filter', 'add_shortcode', 'remove_action', 'remove_filter', 'register_activation_hook' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function $fn( ...\$a ) { return true; }" );
	}
}
function apply_filters( $tag, $value, ...$a ) { return $value; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_title( $s ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $s ), '-' ) ); }

require AM_DIR . '/inc/data.php';
require AM_DIR . '/inc/template-tags.php';
require AM_DIR . '/inc/shortcodes.php';
require AM_DIR . '/inc/growth.php';

$fail  = 0;
$check = 0;
foreach ( glob( AM_DIR . '/data/universities/*.json' ) as $file ) {
	$slug = basename( $file, '.json' );
	$uni  = am_data( 'universities/' . $slug );
	if ( empty( $uni['merit'] ) ) {
		continue;
	}
	$top  = am_uni_year( $uni );
	$want = array();
	foreach ( am_default_rows( $uni ) as $r ) {
		$y = (int) $r['year'];
		if ( false === ( $r['predict'] ?? true ) || $y > $top || $y < $top - 1 ) {
			continue;
		}
		$k = ( $r['program'] ?? '' ) . '|' . ( $r['campus'] ?? '' ) . '|' . ( $r['shift'] ?? '' );
		if ( ! isset( $want[ $k ] ) || $y > $want[ $k ]['year'] ) {
			$want[ $k ] = array( 'year' => $y, 'ref' => (float) $r['closing'] );
		}
	}
	$got = array();
	foreach ( am_predict_rows( $uni ) as $p ) {
		$got[ $p['program'] . '|' . $p['campus'] . '|' . $p['shift'] ] = $p;
	}
	foreach ( $want as $k => $w ) {
		++$check;
		$g = $got[ $k ] ?? null;
		if ( ! $g || (int) $g['year'] !== $w['year'] || abs( (float) $g['ref'] - $w['ref'] ) > 1e-9 ) {
			++$fail;
			printf( "FAIL %s %s: predictor %s (%s), table %s (%d)\n", $slug, $k, $g ? $g['ref'] : '-', $g ? $g['year'] : '-', $w['ref'], $w['year'] );
		}
	}
	foreach ( array_diff_key( $got, $want ) as $k => $g ) {
		++$fail;
		printf( "FAIL %s %s: predictor row with no table row\n", $slug, $k );
	}
}
printf( "predict_rows: %d programs checked, %d failed\n", $check, $fail );
exit( $fail ? 1 : 0 );
