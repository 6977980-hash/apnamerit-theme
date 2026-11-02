<?php
/**
 * Compare tool (Tier 2 idea 6): two programs or colleges side by side, closing merit by year.
 * [am_compare a="uhs:…" b="uhs:…"] renders the default pair as a table (works without JS) and the
 * picker; am.js redraws the table for any pair and writes ?a=&b= into the address bar
 * (canonical stays /compare/). Data = each university's default category, one figure per year:
 * the default list where that year has it, else the last list we hold for that year (named).
 * Links use a row's archive_url when it has one (the old UET portal addresses are 404).
 */

defined( 'ABSPATH' ) || exit;

/** id => [uni, program, campus, shift, url, years => [year => [closing, list, source_url]]]. */
function am_compare_data(): array {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( am_unis() as $slug => $uni ) {
		if ( empty( $uni['meritlist_path'] ) ) {
			continue;
		}
		$cat  = (string) ( $uni['defaults']['category'] ?? 'Open merit' );
		$list = (string) ( $uni['defaults']['list'] ?? 'Final' );
		foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
			if ( ! is_numeric( $r['closing'] ?? null ) || empty( $r['source_url'] ) || ( $r['category'] ?? '' ) !== $cat || false === ( $r['predict'] ?? true ) ) {
				continue;
			}
			$id = $slug . ':' . am_slug( (string) $r['program'] . ' ' . ( $r['campus'] ?? '' ) . ' ' . ( $r['shift'] ?? '' ) );
			$y  = (int) $r['year'];
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array(
					'uni'     => (string) ( $uni['short'] ?? $slug ),
					'program' => (string) $r['program'],
					'campus'  => (string) ( $r['campus'] ?? '' ),
					'shift'   => (string) ( $r['shift'] ?? '' ),
					'url'     => home_url( (string) $uni['meritlist_path'] ),
					'years'   => array(),
				);
			}
			$has = $out[ $id ]['years'][ $y ] ?? null;
			// The default list wins; otherwise keep the lowest figure of that year (the latest list closes lowest).
			if ( ! $has || ( ( $r['list'] ?? '' ) === $list && $has[1] !== $list ) || ( $has[1] !== $list && ( $r['list'] ?? '' ) !== $list && (float) $r['closing'] < $has[0] ) ) {
				$out[ $id ]['years'][ $y ] = array( round( (float) $r['closing'], 2 ), (string) ( $r['list'] ?? '' ), (string) ( $r['archive_url'] ?? '' ? $r['archive_url'] : $r['source_url'] ) );
			}
		}
	}
	// A year published without a shift (UET 2024 portal) joins the program's only shift entry, so
	// its history stays in one row; with two shifts it stays separate (we cannot tell which).
	foreach ( $out as $id => $o ) {
		if ( '' !== $o['shift'] ) {
			continue;
		}
		$sib = array_keys( array_filter( $out, fn( $x, $k ) => $k !== $id && '' !== $x['shift'] && $x['uni'] === $o['uni'] && $x['program'] === $o['program'] && $x['campus'] === $o['campus'], ARRAY_FILTER_USE_BOTH ) );
		if ( 1 === count( $sib ) && ! array_intersect_key( $o['years'], $out[ $sib[0] ]['years'] ) ) {
			$out[ $sib[0] ]['years'] += $o['years'];
			unset( $out[ $id ] );
		}
	}
	foreach ( $out as &$o ) {
		krsort( $o['years'] );
	}
	unset( $o );
	return $out;
}

function am_compare_label( array $o ): string {
	return $o['uni'] . ' · ' . $o['program'] . ( '' !== $o['campus'] ? ' · ' . $o['campus'] : '' ) . ( '' !== $o['shift'] ? ', ' . $o['shift'] : '' );
}

/** Side-by-side table for two ids (server copy of the am.js table). */
function am_compare_table( array $a, array $b ): string {
	$years = array_unique( array_merge( array_keys( $a['years'] ), array_keys( $b['years'] ) ) );
	rsort( $years );
	$rows = '';
	foreach ( $years as $y ) {
		$va    = $a['years'][ $y ] ?? null;
		$vb    = $b['years'][ $y ] ?? null;
		$cell  = fn( $v ) => $v ? '<strong>' . esc_html( am_pct( $v[0] ) ) . '</strong><small>' . esc_html( am_list_label( $v[1] ) ) . ' · ' . am_source_link( $v[2] ) . '</small>' : '—';
		$diff  = $va && $vb ? number_format( $va[0] - $vb[0], 2 ) : '—';
		$rows .= '<tr><th scope="row">' . (int) $y . '</th><td>' . $cell( $va ) . '</td><td>' . $cell( $vb ) . '</td><td>' . esc_html( ( $va && $vb && $va[0] > $vb[0] ? '+' : '' ) . $diff ) . '</td></tr>';
	}
	return '<div class="am-table-wrap am-fit"><table class="am-table"><caption>Closing merit by year: ' . esc_html( am_compare_label( $a ) ) . ' vs ' . esc_html( am_compare_label( $b ) ) . '</caption><thead><tr><th scope="col">Year</th><th scope="col">' . esc_html( $a['program'] . ( $a['campus'] ? ' (' . $a['campus'] . ')' : '' ) ) . '</th><th scope="col">' . esc_html( $b['program'] . ( $b['campus'] ? ' (' . $b['campus'] . ')' : '' ) ) . '</th><th scope="col">Difference</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function am_sc_compare( $atts ): string {
	$atts = shortcode_atts(
		array(
			'a' => '',
			'b' => '',
		),
		(array) $atts,
		'am_compare'
	);
	$data = am_compare_data();
	if ( count( $data ) < 2 || empty( $data[ $atts['a'] ] ) || empty( $data[ $atts['b'] ] ) ) {
		return am_stub( 'am_compare', $atts );
	}
	am_registry(
		'webapp',
		array(
			'id'          => 'compare',
			'name'        => 'Merit Comparison Tool',
			'description' => 'Compares the official closing merit of two programs or medical colleges year by year.',
		)
	);
	// Compact JSON (lookup tables for repeated strings); am.js builds the datalist from it.
	$tables = array( 'u' => array(), 'l' => array(), 's' => array(), 'm' => array() );
	$ix     = function ( string $t, string $v ) use ( &$tables ): int {
		$k = array_search( $v, $tables[ $t ], true );
		if ( false === $k ) {
			$tables[ $t ][] = $v;
			$k              = count( $tables[ $t ] ) - 1;
		}
		return (int) $k;
	};
	$rows = array();
	foreach ( $data as $id => $o ) {
		$ys = array();
		foreach ( $o['years'] as $y => $v ) {
			$ys[ $y ] = array( $v[0], $ix( 'l', $v[1] ), $ix( 's', $v[2] ) );
		}
		$rows[ $id ] = array( $ix( 'u', $o['uni'] ), $o['program'], $o['campus'], $o['shift'], $ix( 'm', $o['url'] ), $ys );
	}
	$field = fn( $k, $label, $id ) => '<div class="am-field"><label for="am-cmp-' . $k . '">' . esc_html( $label ) . '</label><input id="am-cmp-' . $k . '" name="' . $k . '" type="search" list="am-cmp-list" autocomplete="off" value="' . esc_attr( am_compare_label( $data[ $id ] ) ) . '" placeholder="Type a college or program"></div>';
	return '<form class="am-calc am-compare" data-am-compare novalidate>'
		. $field( 'a', 'First program or college', $atts['a'] )
		. $field( 'b', 'Second program or college', $atts['b'] )
		. '<datalist id="am-cmp-list"></datalist>'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Compare</button></div>'
		. '<div class="am-result" aria-live="polite">' . am_compare_table( $data[ $atts['a'] ], $data[ $atts['b'] ] ) . '</div>'
		. am_json_script( 'am-cmp-data', $tables + array( 'r' => $rows ) )
		. '</form>';
}

add_action( 'init', fn() => add_shortcode( 'am_compare', 'am_sc_compare' ) );
