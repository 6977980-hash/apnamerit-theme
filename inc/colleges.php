<?php
/**
 * College pages (audit 2026-11-01 G-07): one URL per Punjab public MBBS college.
 * [am_college uni="uhs" college="kemu"]  closing merit by year, every 2025 selection list, rank, MDCAT needed.
 * [am_college_index uni="uhs"]           links to every college page (on the MDCAT merit list); table="1" = college-wise table.
 * [am_college_links uni="uhs"]           one line of links to every public college page.
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

	// Closing merit position: merit number of the closing candidate in the UHS Final Order of Merit.
	if ( is_numeric( $row['position'] ?? null ) ) {
		$first = null;
		foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
			if ( (int) $r['year'] === $new && '1st' === ( $r['list'] ?? '' ) && ( $r['program'] ?? '' ) === $row['program'] && ( $r['campus'] ?? '' ) === $campus && is_numeric( $r['position'] ?? null ) ) {
				$first = (int) $r['position'];
			}
		}
		$html .= '<p>Closing merit position ' . esc_html( (string) $new ) . ': the last open-merit seat at ' . esc_html( $short ) . ' went to merit number <strong>' . esc_html( number_format( (int) $row['position'] ) ) . '</strong> in the UHS Final Order of Merit'
			. ( null !== $first ? ' (' . esc_html( number_format( $first ) ) . ' in the 1st list)' : '' )
			. '. If your number in the next UHS merit list is smaller, you were ahead of that student. Seats and applicants change each year, so use it as a guide only. Source: ' . am_source_link( (string) ( $row['position_source_url'] ?? '' ) ) . '.</p>';
	}

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

	$html .= am_college_links_html( $uni, $campus );

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
	$atts   = shortcode_atts( array( 'uni' => '', 'table' => '' ), (array) $atts, 'am_college_index' );
	$uni    = am_uni( am_uni_attr( $atts ) );
	$finals = am_college_finals( $uni );
	if ( ! $finals || empty( $uni['colleges'] ) ) {
		return '';
	}
	$new = array_key_first( $finals );
	$all = $finals[ $new ];
	uasort( $all, fn( $a, $b ) => (float) $b['closing'] <=> (float) $a['closing'] );
	if ( '' !== (string) $atts['table'] ) {
		return am_college_index_table( $uni, $all, $finals, (int) $new );
	}
	$items = array();
	foreach ( $all as $campus => $r ) {
		$m = (array) ( $uni['colleges'][ $campus ] ?? array() );
		if ( empty( $m['path'] ) || ! am_find_page( (string) $m['path'] ) ) {
			continue;
		}
		$items[] = '<li><a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . esc_html( $campus ) . '</a>: ' . esc_html( am_pct( $r['closing'] ) ) . '</li>';
	}
	return $items ? '<ul>' . implode( '', $items ) . '</ul>' : '';
}

/**
 * GSC audit 2026-11-12: compact college-wise table for the MDCAT hub (final and 1st list of the
 * newest year, final of the year before, closing position), each college linked to its page.
 */
function am_college_index_table( array $uni, array $all, array $finals, int $new ): string {
	$first = array();
	foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
		if ( 'MBBS (public)' === ( $r['program'] ?? '' ) && '1st' === ( $r['list'] ?? '' ) && 'Open merit' === ( $r['category'] ?? '' ) && $new === (int) $r['year'] ) {
			$first[ (string) $r['campus'] ] = $r;
		}
	}
	$prev = $finals[ $new - 1 ] ?? array();
	$rows = '';
	foreach ( $all as $campus => $r ) {
		$m    = (array) ( $uni['colleges'][ $campus ] ?? array() );
		$name = esc_html( $campus );
		if ( ! empty( $m['path'] ) && am_find_page( (string) $m['path'] ) ) {
			$name = '<a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . $name . '</a>';
		}
		$rows .= '<tr><th scope="row">' . $name . '</th><td><strong>' . esc_html( am_pct( $r['closing'] ) ) . '</strong></td><td>'
			. esc_html( isset( $first[ $campus ] ) ? am_pct( $first[ $campus ]['closing'] ) : '–' ) . '</td><td>'
			. esc_html( isset( $prev[ $campus ] ) ? am_pct( $prev[ $campus ]['closing'] ) : '–' ) . '</td><td>'
			. esc_html( ! empty( $r['position'] ) ? number_format( (int) $r['position'] ) : '–' ) . '</td></tr>';
	}
	return '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( 'Punjab public MBBS colleges: open-merit closing merit ' . $new . ' (UHS)' ) . '</caption><thead><tr><th scope="col">College</th><th scope="col">' . esc_html( 'Final ' . $new ) . '</th><th scope="col">' . esc_html( '1st list ' . $new ) . '</th><th scope="col">' . esc_html( 'Final ' . ( $new - 1 ) ) . '</th><th scope="col">Closing position</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

/**
 * GSC audit 2026-11-12: every college page links all the other public college pages, so each
 * one is reachable from the college pages Google already indexes.
 */
function am_college_links_html( array $uni, string $current, bool $heading = true ): string {
	$finals = am_college_finals( $uni );
	if ( ! $finals ) {
		return '';
	}
	$all = $finals[ array_key_first( $finals ) ];
	uasort( $all, fn( $a, $b ) => (float) $b['closing'] <=> (float) $a['closing'] );
	$links = array();
	foreach ( array_keys( $all ) as $campus ) {
		$m = (array) ( $uni['colleges'][ $campus ] ?? array() );
		if ( $campus === $current || empty( $m['path'] ) || ! am_find_page( (string) $m['path'] ) ) {
			continue;
		}
		$links[] = '<a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . esc_html( (string) ( $m['short'] ?? $campus ) ) . '</a>';
	}
	foreach ( ( $heading ? array( '/mdcat-merit-list/private/' => 'Private MBBS colleges', '/mdcat-merit-list/bds/' => 'BDS colleges' ) : array() ) as $path => $label ) {
		if ( am_find_page( $path ) ) {
			$links[] = '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
		}
	}
	if ( ! $links ) {
		return '';
	}
	return ( $heading ? '<h2>Other medical college merit pages</h2>' : '' ) . '<p class="am-links">' . implode( ' · ', $links ) . '</p>';
}

// [am_college_links uni="uhs"]: one line of links to every public college page (private/BDS pages, UHS admission).
function am_sc_college_links( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_college_links' );
	return am_college_links_html( am_uni( am_uni_attr( $atts ) ), '', false );
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

// ------------------------------------------------------------------ [am_list_when]
/**
 * "In which list would my aggregate have got a seat?" (idea 2026-11-02 #1). For every college of
 * the newest `list_trail` year: the first selection list whose open-merit closing aggregate was at
 * or below the student's aggregate. Server renders an example; am.js redraws it (AM.listWhen).
 * Returns array( year, n lists, colleges [ [name, path, [closing per list]] ], sources per list ).
 */
function am_list_when_data( array $uni ): array {
	$trail = array_filter( (array) ( $uni['list_trail'] ?? array() ), fn( $t ) => is_array( $t ) && is_numeric( $t['closing'] ?? null ) && ! empty( $t['source_url'] ) && (int) ( $t['list_no'] ?? 0 ) > 0 );
	if ( ! $trail ) {
		return array();
	}
	$year = max( array_map( fn( $t ) => (int) $t['year'], $trail ) );
	$by   = array();
	$src  = array();
	$n    = 0;
	foreach ( $trail as $t ) {
		if ( (int) $t['year'] !== $year ) {
			continue;
		}
		$k                                = (int) $t['list_no'];
		$by[ (string) $t['campus'] ][ $k ] = round( (float) $t['closing'], 4 );
		$src[ $k ]                        = (string) $t['source_url'];
		$n                                = max( $n, $k );
	}
	$cols = array();
	foreach ( $by as $campus => $ls ) {
		$meta = (array) ( $uni['colleges'][ $campus ] ?? array() );
		$path = ! empty( $meta['path'] ) && am_find_page( (string) $meta['path'] ) ? (string) $meta['path'] : '';
		$c    = array();
		for ( $i = 1; $i <= $n; $i++ ) {
			$c[] = $ls[ $i ] ?? null;
		}
		$cols[] = array( (string) ( $meta['short'] ?? $campus ), $path, $c );
	}
	$s = array();
	for ( $i = 1; $i <= $n; $i++ ) {
		$s[] = $src[ $i ] ?? '';
	}
	return array( 'y' => $year, 'n' => $n, 'c' => $cols, 's' => $s, 'p' => (string) ( reset( $trail )['program'] ?? 'MBBS (public)' ) );
}

/** Rows for one aggregate: same rule as AM.listWhen in am.js (keep both in step). */
function am_list_when_rows( array $d, float $agg ): array {
	$rows = array();
	foreach ( $d['c'] as $col ) {
		$hit = 0;
		foreach ( $col[2] as $i => $v ) {
			if ( null !== $v && $agg >= $v ) {
				$hit = $i + 1;
				break;
			}
		}
		$last   = null;
		foreach ( array_reverse( $col[2] ) as $v ) {
			if ( null !== $v ) {
				$last = $v;
				break;
			}
		}
		$rows[] = array( 'name' => $col[0], 'path' => $col[1], 'list' => $hit, 'closing' => $hit ? $col[2][ $hit - 1 ] : $last, 'short' => $hit ? 0 : round( (float) $last - $agg, 4 ) );
	}
	usort( $rows, fn( $a, $b ) => ( $a['list'] ? 0 : 1 ) <=> ( $b['list'] ? 0 : 1 ) ?: ( $a['list'] ? $a['list'] <=> $b['list'] : $a['short'] <=> $b['short'] ) ?: $b['closing'] <=> $a['closing'] );
	return $rows;
}

function am_list_when_table( array $d, float $agg ): string {
	$rows = am_list_when_rows( $d, $agg );
	$got  = count( array_filter( $rows, fn( $r ) => $r['list'] ) );
	$tr   = '';
	foreach ( $rows as $r ) {
		$name = esc_html( $r['name'] );
		if ( '' !== $r['path'] ) {
			$name = '<a href="' . esc_url( home_url( $r['path'] ) ) . '">' . $name . '</a>';
		}
		$tr .= '<tr><th scope="row">' . $name . '</th>' . ( $r['list'] ? '<td><strong>' . esc_html( am_ordinal( $r['list'] ) ) . ' list</strong></td><td>' . esc_html( am_pct( $r['closing'] ) ) . ' · ' . am_source_link( (string) $d['s'][ $r['list'] - 1 ] ) . '</td>' : '<td>Not reached</td><td>' . esc_html( number_format( $r['short'], 2 ) ) . ' short of the last list (' . esc_html( am_pct( $r['closing'] ) ) . ')</td>' ) . '</tr>';
	}
	return '<p class="am-result-main">With <strong>' . esc_html( am_pct( $agg ) ) . '</strong> you would have reached <strong>' . esc_html( $got . ' of ' . count( $rows ) ) . '</strong> public colleges in the ' . esc_html( $d['y'] . '-' . substr( (string) ( $d['y'] + 1 ), 2 ) ) . ' lists.</p>'
		. '<div class="am-table-wrap am-fit"><table class="am-table"><caption>' . esc_html( $d['p'] . ' ' . $d['y'] . ', open merit: first of ' . $d['n'] . ' UHS selection lists that reached ' . am_pct( $agg ) ) . '</caption><thead><tr><th scope="col">College</th><th scope="col">First list that reached you</th><th scope="col">Closing in that list</th></tr></thead><tbody>' . $tr . '</tbody></table></div>';
}

function am_sc_list_when( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => 'uhs', 'example' => '93.7' ), (array) $atts, 'am_list_when' );
	$d    = am_list_when_data( am_uni( am_uni_attr( $atts ) ) );
	if ( ! $d ) {
		return am_stub( 'am_list_when', $atts );
	}
	$ex = (float) $atts['example'];
	return '<form class="am-calc am-listwhen" data-am-listwhen novalidate>'
		. '<div class="am-field"><label for="am-lw-agg">Your MDCAT aggregate (%)</label><input id="am-lw-agg" name="agg" type="number" inputmode="decimal" min="0" max="100" step="any" placeholder="' . esc_attr( (string) $ex ) . '" required><small>From the <a href="/mdcat-aggregate-calculator/">MDCAT aggregate calculator</a>. Punjab open merit, public MBBS.</small></div>'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Show my list</button></div>'
		. '<div class="am-result" aria-live="polite"><p class="am-muted">Example for ' . esc_html( am_pct( $ex ) ) . ':</p>' . am_list_when_table( $d, $ex ) . '</div>'
		. am_json_script( 'am-lw-data', $d )
		. '</form>'
		. '<p class="am-muted">Each college is read as if it were your first preference. In the real lists you get the highest college on your preference list that your merit reaches, and the ' . esc_html( (string) ( $d['y'] + 1 ) ) . ' figures will differ with seats, applicants and the test.</p>';
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_college', 'am_sc_college' );
		add_shortcode( 'am_college_index', 'am_sc_college_index' );
		add_shortcode( 'am_college_links', 'am_sc_college_links' );
		add_shortcode( 'am_seats', 'am_sc_seats' );
		add_shortcode( 'am_list_drop', 'am_sc_list_drop' );
		add_shortcode( 'am_list_when', 'am_sc_list_when' );
	}
);
