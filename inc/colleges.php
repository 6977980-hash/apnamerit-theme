<?php
/**
 * College pages (audit 2026-11-01 G-07): one URL per Punjab public MBBS college.
 * [am_college uni="uhs" college="kemu"]  closing merit by year, every 2025 selection list, rank, MDCAT needed.
 * [am_college_index uni="uhs"]           links to every college page (on the MDCAT merit list).
 * Data: uhs.json `colleges` (campus => slug, short, path), `merit` (Final / 1st rows) and `list_trail`
 * (lowest open-merit aggregate per college in every list). Page text in data/pages.json is written by
 * scripts/college_pages.py from the same data; check.py fails when they drift apart.
 */

defined( 'ABSPATH' ) || exit;

/** Campus name and meta for a college slug, or [ '', [] ]. */
function am_college_find( array $uni, string $slug ): array {
	foreach ( (array) ( $uni['colleges'] ?? array() ) as $campus => $c ) {
		if ( ( $c['slug'] ?? '' ) === $slug ) {
			return array( (string) $campus, (array) $c );
		}
	}
	return array( '', array() );
}

/** Final-list open-merit rows for public MBBS, newest year first: [ year => [ campus => row ] ]. */
function am_college_finals( array $uni ): array {
	$out = array();
	foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
		if ( 'MBBS (public)' === ( $r['program'] ?? '' ) && 'Final' === ( $r['list'] ?? '' ) && 'Open merit' === ( $r['category'] ?? '' ) ) {
			$out[ (int) $r['year'] ][ (string) $r['campus'] ] = $r;
		}
	}
	krsort( $out );
	return $out;
}

/** Smallest MDCAT score that reaches $target with FSc and Matric both at $marks (UHS formula), or 0 if out of reach. */
function am_college_mdcat_needed( array $uni, float $target, float $marks ): int {
	$f     = (array) ( $uni['formulas'][0] ?? array() );
	$fixed = 0.0;
	$mdcat = null;
	foreach ( (array) ( $f['parts'] ?? array() ) as $p ) {
		if ( 'mdcat' === $p['key'] ) {
			$mdcat = $p;
		} else {
			$fixed += $marks / (float) $p['total'] * (float) $p['weight'];
		}
	}
	if ( ! $mdcat ) {
		return 0;
	}
	$need = (int) ceil( ( $target - $fixed ) / (float) $mdcat['weight'] * (float) $mdcat['total'] - 1e-9 );
	return $need > (int) $mdcat['total'] ? 0 : max( 0, $need );
}

// ------------------------------------------------------------------ [am_college]
function am_sc_college( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '', 'college' => '' ), (array) $atts, 'am_college' );
	$uni  = am_uni( am_uni_attr( $atts ) );
	list( $campus, $meta ) = am_college_find( $uni, sanitize_key( (string) $atts['college'] ) );
	$finals = am_college_finals( $uni );
	if ( '' === $campus || ! $finals ) {
		return am_stub( 'am_college', $atts );
	}
	$short = (string) ( $meta['short'] ?? $campus );
	$years = array_keys( $finals );
	$new   = $years[0];
	$row   = $finals[ $new ][ $campus ] ?? null;
	if ( ! $row ) {
		return am_stub( 'am_college', $atts );
	}
	$html = '';

	// 1. Final-list closing merit by year.
	$body = '';
	$prev = null;
	foreach ( array_reverse( $years ) as $y ) {
		$r = $finals[ $y ][ $campus ] ?? null;
		if ( ! $r ) {
			continue;
		}
		$cur   = round( (float) $r['closing'], 2 ); // printed values, so the changes add up
		$chg   = null === $prev ? '' : ( ( $cur - $prev >= 0 ? '+' : '' ) . number_format( $cur - $prev, 2 ) );
		$body  = '<tr><th scope="row">' . esc_html( (string) $y ) . '</th><td>' . esc_html( am_pct( $r['closing'] ) ) . '</td><td>' . esc_html( $chg ) . '</td><td>' . esc_html( ucfirst( (string) ( $r['note'] ?? 'Final list' ) ) ) . '</td><td>' . am_source_link( (string) $r['source_url'] ) . '</td></tr>' . $body;
		$prev  = $cur;
	}
	$html .= '<h2>' . esc_html( $short ) . ' closing merit by year</h2>'
		. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $campus . ': MBBS open-merit closing aggregate in the last UHS selection list of each year' ) . '</caption>'
		. '<thead><tr><th scope="col">Year</th><th scope="col">Closing merit</th><th scope="col">Change</th><th scope="col">List</th><th scope="col">Source</th></tr></thead><tbody>' . $body . '</tbody></table></div>';

	// 2. Every selection list of the newest year.
	$trail = array_values( array_filter( (array) ( $uni['list_trail'] ?? array() ), fn( $t ) => (int) ( $t['year'] ?? 0 ) === $new && ( $t['campus'] ?? '' ) === $campus ) );
	usort( $trail, fn( $a, $b ) => (int) $a['list_no'] <=> (int) $b['list_no'] );
	if ( count( $trail ) > 1 ) {
		$rows = '';
		$last = null;
		foreach ( $trail as $t ) {
			$cur   = round( (float) $t['closing'], 2 );
			$chg   = null === $last ? '' : number_format( $cur - $last, 2 );
			$rows .= '<tr><th scope="row">' . esc_html( am_ordinal( (int) $t['list_no'] ) ) . '</th><td>' . esc_html( am_pct( $t['closing'] ) ) . '</td><td>' . esc_html( '-0.00' === $chg ? '0.00' : $chg ) . '</td><td>' . am_source_link( (string) $t['source_url'] ) . '</td></tr>';
			$last  = $cur;
		}
		$first = round( (float) $trail[0]['closing'], 2 );
		$end   = round( (float) end( $trail )['closing'], 2 );
		$html .= '<h2>' . esc_html( $short . ' merit in every ' . $new . ' selection list' ) . '</h2>'
			. '<p>UHS issued ' . count( $trail ) . ' selection lists for public MBBS in ' . esc_html( (string) $new ) . '. ' . esc_html( $short ) . ' moved from ' . esc_html( am_pct( $first ) ) . ' in the 1st list to ' . esc_html( am_pct( $end ) ) . ' in the last one, a drop of ' . esc_html( number_format( $first - $end, 2 ) ) . ' points. Each figure is the lowest open-merit aggregate placed at the college in that list.</p>'
			. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $short . ': open-merit closing aggregate in each ' . $new . ' UHS public MBBS selection list' ) . '</caption>'
			. '<thead><tr><th scope="col">List</th><th scope="col">Closing merit</th><th scope="col">Change</th><th scope="col">Source</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
	}

	// 3. Rank and neighbours in the newest final list.
	$all = $finals[ $new ];
	uasort( $all, fn( $a, $b ) => (float) $b['closing'] <=> (float) $a['closing'] );
	$names = array_keys( $all );
	$pos   = array_search( $campus, $names, true );
	$link  = function ( string $c ) use ( $uni, $all ): string {
		$m = (array) ( $uni['colleges'][ $c ] ?? array() );
		$t = esc_html( (string) ( $m['short'] ?? $c ) ) . ' (' . esc_html( am_pct( $all[ $c ]['closing'] ) ) . ')';
		return ! empty( $m['path'] ) && am_find_page( (string) $m['path'] ) ? '<a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . $t . '</a>' : $t;
	};
	$near = array();
	if ( $pos > 0 ) {
		$near[] = 'just above it: ' . $link( $names[ $pos - 1 ] );
	}
	if ( $pos < count( $names ) - 1 ) {
		$near[] = 'just below it: ' . $link( $names[ $pos + 1 ] );
	}
	$html .= '<h2>Where ' . esc_html( $short ) . ' ranks</h2><p>In the final ' . esc_html( (string) $new ) . ' list, ' . esc_html( $short ) . ' had the ' . esc_html( am_ordinal( (int) $pos + 1 ) ) . ' highest closing merit of the ' . count( $names ) . ' Punjab public medical colleges (' . implode( '; ', $near ) . '). All ' . count( $names ) . ' are in the <a href="' . esc_url( home_url( (string) ( $uni['meritlist_path'] ?? '/mdcat-merit-list/' ) ) ) . '">MDCAT merit list</a>.</p>';

	// 4. MDCAT needed.
	$rows = '';
	foreach ( array( 1000, 1050, 1080 ) as $m ) {
		$need  = am_college_mdcat_needed( $uni, (float) $row['closing'], (float) $m );
		$rows .= '<tr><th scope="row">' . esc_html( $m . ' / 1100' ) . '</th><td>' . esc_html( $need ? 'about ' . $need . ' / 180' : 'not reachable' ) . '</td></tr>';
	}
	$html .= '<h2>MDCAT marks needed for ' . esc_html( $short ) . '</h2><p>The MDCAT score that would have reached ' . esc_html( am_pct( $row['closing'] ) ) . ' (the ' . esc_html( (string) $new ) . ' final closing merit) with the PM&amp;DC formula (50% MDCAT, 40% FSc, 10% Matric), for the same marks in FSc and Matric:</p>'
		. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( 'MDCAT score needed for ' . $short . ' at its ' . $new . ' closing merit' ) . '</caption><thead><tr><th scope="col">FSc and Matric</th><th scope="col">MDCAT needed</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
		. '<p>Merit changes every year, so treat this as a guide. Work out your own aggregate with the <a href="' . esc_url( home_url( (string) ( $uni['calculator_path'] ?? '/mdcat-aggregate-calculator/' ) ) ) . '">MDCAT aggregate calculator</a>; you also need at least 55% in MDCAT and 60% in FSc to be eligible for MBBS.</p>';

	return $html;
}

/** 1 → 1st, 2 → 2nd, 13 → 13th. */
function am_ordinal( int $n ): string {
	$s = array( 'th', 'st', 'nd', 'rd' );
	$v = $n % 100;
	return $n . ( $s[ ( $v - 20 ) % 10 ] ?? $s[ $v ] ?? $s[0] );
}

// ------------------------------------------------------------------ [am_college_index]
function am_sc_college_index( $atts ): string {
	$atts   = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_college_index' );
	$uni    = am_uni( am_uni_attr( $atts ) );
	$finals = am_college_finals( $uni );
	if ( ! $finals || empty( $uni['colleges'] ) ) {
		return '';
	}
	$new   = array_key_first( $finals );
	$items = array();
	$all   = $finals[ $new ];
	uasort( $all, fn( $a, $b ) => (float) $b['closing'] <=> (float) $a['closing'] );
	foreach ( $all as $campus => $r ) {
		$m = (array) ( $uni['colleges'][ $campus ] ?? array() );
		if ( empty( $m['path'] ) || ! am_find_page( (string) $m['path'] ) ) {
			continue;
		}
		$items[] = '<li><a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . esc_html( $campus ) . '</a>: ' . esc_html( am_pct( $r['closing'] ) ) . '</li>';
	}
	return $items ? '<ul>' . implode( '', $items ) . '</ul>' : '';
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_college', 'am_sc_college' );
		add_shortcode( 'am_college_index', 'am_sc_college_index' );
	}
);
