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
	$html .= '<h2>Where ' . esc_html( $short ) . ' ranks</h2><p>In the final ' . esc_html( (string) $new ) . ' list, ' . esc_html( $short ) . ' had the ' . esc_html( 0 === (int) $pos ? '' : am_ordinal( (int) $pos + 1 ) . ' ' ) . 'highest closing merit of the ' . count( $names ) . ' Punjab public medical colleges (' . implode( '; ', $near ) . '). All ' . count( $names ) . ' are in the <a href="' . esc_url( home_url( (string) ( $uni['meritlist_path'] ?? '/mdcat-merit-list/' ) ) ) . '">MDCAT merit list</a>.</p>';

	// 3b. What the gaps mean in MDCAT marks (audit CQ-05: a college-specific paragraph from the data).
	// One MDCAT mark = 50 / 180 = 0.28 aggregate points with the PM&DC formula.
	$mark  = 50 / 180;
	$cur   = (float) $row['closing'];
	$close = array_filter( $names, fn( $c ) => $c !== $campus && abs( (float) $all[ $c ]['closing'] - $cur ) <= 0.25 );
	$gap   = function ( string $c ) use ( $all, $cur, $mark ): string {
		$m = abs( (float) $all[ $c ]['closing'] - $cur ) / $mark;
		return $m < 1 ? 'less than one MDCAT mark' : 'about ' . number_format( $m, 1 ) . ' MDCAT marks';
	};
	$bits = array();
	if ( $pos > 0 ) {
		$bits[] = esc_html( $gap( $names[ $pos - 1 ] ) ) . ' below ' . $link( $names[ $pos - 1 ] );
	}
	if ( $pos < count( $names ) - 1 ) {
		$bits[] = esc_html( $gap( $names[ $pos + 1 ] ) ) . ' above ' . $link( $names[ $pos + 1 ] );
	}
	$pref  = am_find_page( '/guides/uhs-college-preference-order/' );
	$html .= '<p><strong>What the gaps mean:</strong> one MDCAT mark is worth 0.28 aggregate points, so in ' . esc_html( (string) $new ) . ' ' . esc_html( $short ) . ' closed ' . implode( ' and ', $bits ) . '. '
		. ( $close ? count( $close ) . ' other ' . ( 1 === count( $close ) ? 'college' : 'colleges' ) . ' closed within 0.25 points of it, so small changes in the number of applicants can reorder them next year. ' : 'No other college closed within 0.25 points of it. ' )
		. ( $pref ? 'Rank the colleges you want by preference, not by last year\'s merit: <a href="' . esc_url( home_url( '/guides/uhs-college-preference-order/' ) ) . '">how to order UHS college preferences</a>.' : '' ) . '</p>';

	// 4. MDCAT needed.
	$rows = '';
	foreach ( array( 1000, 1050, 1080 ) as $m ) {
		$need  = am_college_mdcat_needed( $uni, (float) $row['closing'], (float) $m );
		$rows .= '<tr><th scope="row">' . esc_html( $m . ' / 1100' ) . '</th><td>' . esc_html( $need ? 'about ' . $need . ' / 180' : 'not reachable' ) . '</td></tr>';
	}
	$html .= '<h2>MDCAT marks needed for ' . esc_html( $short ) . '</h2><p>The MDCAT score that would have reached ' . esc_html( am_pct( $row['closing'] ) ) . ' (the ' . esc_html( (string) $new ) . ' final closing merit) with the PM&amp;DC formula (50% MDCAT, 40% FSc, 10% Matric), for the same marks in FSc and Matric:</p>'
		. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( 'MDCAT score needed for ' . $short . ' at its ' . $new . ' closing merit' ) . '</caption><thead><tr><th scope="col">FSc and Matric</th><th scope="col">MDCAT needed</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
		. '<p>Merit changes every year, so treat this as a guide. Work out your own aggregate with the <a href="' . esc_url( home_url( (string) ( $uni['calculator_path'] ?? '/mdcat-aggregate-calculator/' ) ) ) . '">MDCAT aggregate calculator</a>; you also need at least 55% in MDCAT and 60% in FSc to be eligible for MBBS.</p>';

	// 5. Seats and fee (audit G-05) from the UHS prospectus.
	$st = (array) ( $uni['seats']['mbbs'][ $campus ] ?? array() );
	if ( $st ) {
		$labels = array(
			'open'         => 'Open merit',
			'disabilities' => 'Students with disabilities',
			'cholistan'    => 'Cholistan',
			'udd'          => 'Under-developed districts',
			'overseas'     => 'Overseas Pakistanis (US$ fee)',
			'other'        => 'Other provinces, regions and HEC scholarship',
		);
		$rows = '';
		foreach ( $labels as $k => $l ) {
			if ( ! empty( $st[ $k ] ) ) {
				$rows .= '<tr><th scope="row">' . esc_html( $l ) . '</th><td>' . esc_html( (string) $st[ $k ] ) . '</td></tr>';
			}
		}
		$rows .= '<tr><th scope="row">Total MBBS seats</th><td><strong>' . esc_html( (string) $st['total'] ) . '</strong></td></tr>';
		$ss    = (string) ( $uni['seats']['session'] ?? '' );
		$html .= '<h2>' . esc_html( $short . ' MBBS seats and fee ' . $ss ) . '</h2>'
			. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $campus . ': MBBS seats by category, session ' . $ss ) . '</caption><thead><tr><th scope="col">Category</th><th scope="col">Seats</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
			. am_seats_fee_line( $uni ) . '<p class="am-muted">Source: ' . am_source_link( (string) $uni['seats']['source_url'] ) . ', seat allocation (p. 38) and fee (p. 61). The closing merits above are for open-merit seats only.</p>';
	}

	return $html;
}

/** 1 → 1st, 2 → 2nd, 13 → 13th. */
function am_ordinal( int $n ): string {
	$s = array( 'th', 'st', 'nd', 'rd' );
	$v = $n % 100;
	return $n . ( $s[ ( $v - 20 ) % 10 ] ?? $s[ $v ] ?? $s[0] );
}

/** "Fee: Rs 53,030 in the first year ..." for public MBBS/BDS (uhs.json `fee`). */
function am_seats_fee_line( array $uni ): string {
	$fee = (array) ( $uni['fee'] ?? array() );
	if ( empty( $fee['mbbs'] ) ) {
		return '';
	}
	$rs  = fn( $n ) => 'Rs ' . number_format( (float) $n );
	$tot = array_sum( (array) $fee['mbbs'] );
	return '<p><strong>Fee (session ' . esc_html( (string) $fee['session'] ) . '):</strong> ' . esc_html( $rs( $fee['mbbs'][0] ) ) . ' in the first year of MBBS (' . esc_html( $rs( $fee['tuition'] ) ) . ' tuition, plus admission fee and charges) and about ' . esc_html( $rs( $fee['mbbs'][1] ) ) . ' a year after that, ' . esc_html( $rs( $tot ) ) . ' for all five years. The same fee applies at every Punjab public medical college and on every category of seat, except the Overseas Pakistanis quota: US$ ' . esc_html( number_format( (float) $fee['overseas_usd'] ) ) . ' a year.</p>';
}

// ------------------------------------------------------------------ [am_seats]
/**
 * Seat table for every public MBBS and BDS college (audit G-05) with the newest final closing merit.
 */
function am_sc_seats( $atts ): string {
	$atts  = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_seats' );
	$uni   = am_uni( am_uni_attr( $atts ) );
	$seats = (array) ( $uni['seats'] ?? array() );
	if ( empty( $seats['mbbs'] ) ) {
		return '';
	}
	$close = array();
	$year  = 0;
	foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
		if ( 'Final' === ( $r['list'] ?? '' ) && 'Open merit' === ( $r['category'] ?? '' ) && str_contains( (string) $r['program'], 'public' ) && (int) $r['year'] >= $year ) {
			$year                                    = (int) $r['year'];
			$close[ $r['program'] . '|' . $r['campus'] ] = (float) $r['closing'];
		}
	}
	$out = '';
	foreach ( array( 'mbbs' => 'MBBS (public)', 'bds' => 'BDS (public)' ) as $k => $prog ) {
		$rows = '';
		$sum  = array( 0, 0 );
		foreach ( (array) ( $seats[ $k ] ?? array() ) as $campus => $st ) {
			$m     = (array) ( $uni['colleges'][ $campus ] ?? array() );
			$name  = ! empty( $m['path'] ) && am_find_page( (string) $m['path'] ) ? '<a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . esc_html( $campus ) . '</a>' : esc_html( $campus );
			$c     = $close[ $prog . '|' . $campus ] ?? null;
			$rows .= '<tr><th scope="row">' . $name . ( ! empty( $st['pending_pmdc'] ) ? ' *' : '' ) . '</th><td>' . esc_html( (string) $st['open'] ) . '</td><td>' . esc_html( (string) $st['total'] ) . '</td><td>' . esc_html( null === $c ? 'new' : am_pct( $c ) ) . '</td></tr>';
			$sum[0] += (int) $st['open'];
			$sum[1] += (int) $st['total'];
		}
		$rows .= '<tr><th scope="row">Total</th><td><strong>' . $sum[0] . '</strong></td><td><strong>' . $sum[1] . '</strong></td><td></td></tr>';
		$label = 'mbbs' === $k ? 'MBBS' : 'BDS';
		$out  .= '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $label . ' seats in Punjab public colleges, session ' . $seats['session'] . ', with the ' . $year . ' final closing merit (open merit)' ) . '</caption>'
			. '<thead><tr><th scope="col">College</th><th scope="col">Open merit seats</th><th scope="col">Total seats</th><th scope="col">Closing merit ' . esc_html( (string) $year ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
	}
	return $out . '<p class="am-muted">* Seats for 2026-27 subject to PM&amp;DC approval; any closing merit shown is from an earlier year. "new" = no closing merit yet. Total seats include reserved seats (students with disabilities, under-developed districts, Cholistan, Overseas Pakistanis, other provinces and regions, HEC scholarships). Source: ' . am_source_link( (string) $seats['source_url'] ) . ', seat allocation (pp. 38-39).</p>' . am_seats_fee_line( $uni );
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

// ------------------------------------------------------------------ [am_list_drop]
/**
 * How far each college's open-merit closing aggregate fell from the 1st to the last selection list
 * of the newest `list_trail` year (guide "not selected in the first merit list"). Computed from
 * uhs.json list_trail, so it follows the data; no hand-typed figures.
 */
function am_sc_list_drop( $atts ): string {
	$atts  = shortcode_atts( array( 'uni' => 'uhs' ), (array) $atts, 'am_list_drop' );
	$uni   = am_uni( am_uni_attr( $atts ) );
	$trail = array_filter( (array) ( $uni['list_trail'] ?? array() ), fn( $t ) => is_array( $t ) && is_numeric( $t['closing'] ?? null ) && ! empty( $t['source_url'] ) );
	if ( ! $trail ) {
		return am_stub( 'am_list_drop', $atts );
	}
	$year = max( array_map( fn( $t ) => (int) $t['year'], $trail ) );
	$by   = array();
	foreach ( $trail as $t ) {
		if ( (int) $t['year'] === $year ) {
			$by[ (string) $t['campus'] ][ (int) $t['list_no'] ] = $t;
		}
	}
	$rows  = array();
	$lists = 0;
	foreach ( $by as $campus => $ls ) {
		ksort( $ls );
		$first = reset( $ls );
		$last  = end( $ls );
		$lists = max( $lists, (int) key( $ls ) );
		$meta  = (array) ( $uni['colleges'][ $campus ] ?? array() );
		$name  = esc_html( (string) ( $meta['short'] ?? $campus ) );
		if ( ! empty( $meta['path'] ) && am_find_page( (string) $meta['path'] ) ) {
			$name = '<a href="' . esc_url( home_url( (string) $meta['path'] ) ) . '">' . $name . '</a>';
		}
		$a      = round( (float) $first['closing'], 2 );
		$b      = round( (float) $last['closing'], 2 );
		$rows[] = array( $b, '<tr><th scope="row">' . $name . '</th><td>' . esc_html( am_pct( $first['closing'] ) ) . '</td><td>' . esc_html( am_pct( $last['closing'] ) ) . '</td><td>' . esc_html( number_format( $a - $b, 2 ) ) . '</td><td>' . am_source_link( (string) $last['source_url'] ) . '</td></tr>' );
	}
	usort( $rows, fn( $x, $y ) => $y[0] <=> $x[0] );
	$drops = array_map( fn( $c ) => round( (float) reset( $c )['closing'], 2 ) - round( (float) end( $c )['closing'], 2 ), array_map( function ( $ls ) { ksort( $ls ); return $ls; }, $by ) );
	$prog  = (string) ( reset( $trail )['program'] ?? 'MBBS (public)' );
	return '<p>In ' . esc_html( (string) $year ) . ' the open-merit closing aggregate of Punjab\'s public medical colleges fell by <strong>' . esc_html( number_format( min( $drops ), 2 ) . ' to ' . number_format( max( $drops ), 2 ) ) . ' points</strong> between the 1st and the ' . esc_html( am_ordinal( $lists ) ) . ' (last) UHS selection list. If your aggregate is below a college\'s 1st-list closing merit by more than that, later lists of the same year are unlikely to reach you there; within it, wait for the upgradation lists.</p>'
		. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $prog . ' ' . $year . ': open-merit closing aggregate in the 1st and the ' . am_ordinal( $lists ) . ' UHS selection list' ) . '</caption>'
		. '<thead><tr><th scope="col">College</th><th scope="col">1st list</th><th scope="col">' . esc_html( am_ordinal( $lists ) ) . ' list</th><th scope="col">Drop</th><th scope="col">Source</th></tr></thead><tbody>' . implode( '', array_column( $rows, 1 ) ) . '</tbody></table></div>';
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_college', 'am_sc_college' );
		add_shortcode( 'am_college_index', 'am_sc_college_index' );
		add_shortcode( 'am_seats', 'am_sc_seats' );
		add_shortcode( 'am_list_drop', 'am_sc_list_drop' );
	}
);
