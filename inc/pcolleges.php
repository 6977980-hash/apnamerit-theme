<?php
/**
 * Private college pages (GSC audit 2026-11-12, item 6): one URL per private MBBS college that
 * people search for and that has official closing merits (UHS private lists, NUMS lists).
 * [am_pcollege uni="uhs" college="sahara"]  closing merit in every list (UHS) or by year (NUMS), BDS, rank, MDCAT needed.
 * [am_pcollege_links uni="uhs"]             one line of links to the private college pages of a university.
 * Data: `private_colleges` (campus => slug, short, path, bds_campus) in the university JSON; UHS
 * `private_trail` (lowest open-merit aggregate per college in every private list). Page text in
 * data/pages.json is written by scripts/college_pages.py from the same data.
 */

defined( 'ABSPATH' ) || exit;

/** Campus and meta for a private college slug, or [ '', [] ]. */
function am_pcollege_find( array $uni, string $slug ): array {
	foreach ( (array) ( $uni['private_colleges'] ?? array() ) as $campus => $c ) {
		if ( ( $c['slug'] ?? '' ) === $slug ) {
			return array( (string) $campus, (array) $c );
		}
	}
	return array( '', array() );
}

/** Newest open-merit row per campus of a program: the default list of the university (UHS Final/3rd, NUMS Latest published). */
function am_pcollege_newest( array $uni, string $program ): array {
	$out = array();
	foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
		if ( ( $r['program'] ?? '' ) !== $program || 'Open merit' !== ( $r['category'] ?? '' ) || '1st' === ( $r['list'] ?? '' ) || '1st list' === ( $r['list'] ?? '' ) ) {
			continue;
		}
		$y = (int) $r['year'];
		$c = (string) $r['campus'];
		if ( ! isset( $out[ $c ] ) || $y > (int) $out[ $c ]['year'] ) {
			$out[ $c ] = $r;
		}
	}
	$top = $out ? max( array_map( fn( $r ) => (int) $r['year'], $out ) ) : 0;
	$out = array_filter( $out, fn( $r ) => (int) $r['year'] === $top );
	uasort( $out, fn( $a, $b ) => (float) $b['closing'] <=> (float) $a['closing'] );
	return $out;
}

/** Table of one campus: UHS = every list of the newest trail year, NUMS = by year. */
function am_pcollege_table( array $uni, string $campus, string $program, string $label ): string {
	$trail = array_values( array_filter( (array) ( $uni['private_trail'] ?? array() ), fn( $t ) => ( $t['campus'] ?? '' ) === $campus && ( $t['program'] ?? '' ) === $program ) );
	$rows  = '';
	$last  = null;
	if ( $trail ) {
		usort( $trail, fn( $a, $b ) => (int) $a['list_no'] <=> (int) $b['list_no'] );
		$y = (int) $trail[0]['year'];
		foreach ( $trail as $t ) {
			$cur   = round( (float) $t['closing'], 2 );
			$chg   = null === $last ? '' : number_format( $cur - $last, 2 );
			$rows .= '<tr><th scope="row">' . esc_html( am_ordinal( (int) $t['list_no'] ) . ( end( $trail ) === $t ? ' (last)' : '' ) ) . '</th><td>' . esc_html( am_pct( $t['closing'] ) ) . '</td><td>' . esc_html( '-0.00' === $chg ? '0.00' : $chg ) . '</td><td>' . am_source_link( (string) $t['source_url'] ) . '</td></tr>';
			$last  = $cur;
		}
		return '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $label . ': open-merit closing aggregate in each ' . $y . ' UHS ' . $program . ' selection list' ) . '</caption>'
			. '<thead><tr><th scope="col">List</th><th scope="col">Closing merit</th><th scope="col">Change</th><th scope="col">Source</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
	}
	$byyear = array();
	foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
		if ( ( $r['campus'] ?? '' ) === $campus && ( $r['program'] ?? '' ) === $program && 'Open merit' === ( $r['category'] ?? '' ) ) {
			$byyear[ (int) $r['year'] ] = $r;
		}
	}
	ksort( $byyear );
	foreach ( $byyear as $y => $r ) {
		$cur   = round( (float) $r['closing'], 2 );
		$chg   = null === $last ? '' : ( ( $cur - $last >= 0 ? '+' : '' ) . number_format( $cur - $last, 2 ) );
		$rows  = '<tr><th scope="row">' . esc_html( $y . '-' . substr( (string) ( $y + 1 ), 2 ) ) . '</th><td>' . esc_html( am_pct( $r['closing'] ) ) . '</td><td>' . esc_html( $chg ) . '</td><td>' . esc_html( ucfirst( (string) ( $r['note'] ?? $r['list'] ) ) ) . '</td><td>' . am_source_link( (string) $r['source_url'], (string) ( $r['archive_url'] ?? '' ) ) . '</td></tr>' . $rows;
		$last  = $cur;
	}
	return $rows ? '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $label . ': ' . $program . ' open-merit closing aggregate by session (' . ( $uni['short'] ?? '' ) . ')' ) . '</caption>'
		. '<thead><tr><th scope="col">Session</th><th scope="col">Closing merit</th><th scope="col">Change</th><th scope="col">List</th><th scope="col">Source</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' : '';
}

/** Short name with a link when the campus has a page. */
function am_pcollege_name( array $uni, string $campus ): string {
	$m = (array) ( $uni['private_colleges'][ $campus ] ?? array() );
	$t = esc_html( (string) ( $m['short'] ?? $campus ) );
	return ! empty( $m['path'] ) && am_find_page( (string) $m['path'] ) ? '<a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . $t . '</a>' : $t;
}

function am_sc_pcollege( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '', 'college' => '' ), (array) $atts, 'am_pcollege' );
	$uni  = am_uni( am_uni_attr( $atts ) );
	list( $campus, $meta ) = am_pcollege_find( $uni, sanitize_key( (string) $atts['college'] ) );
	$prog = 'MBBS (private)';
	$all  = am_pcollege_newest( $uni, $prog );
	if ( '' === $campus || empty( $all[ $campus ] ) ) {
		return am_stub( 'am_pcollege', $atts );
	}
	$short = (string) ( $meta['short'] ?? $campus );
	$row   = $all[ $campus ];
	$year  = (int) $row['year'];
	$us    = (string) ( $uni['short'] ?? '' );
	$html  = '';

	// 1. Closing merit in every list (UHS) or by session (NUMS).
	$html .= '<h2>' . esc_html( $short . ' MBBS closing merit' ) . '</h2>' . am_pcollege_table( $uni, $campus, $prog, $short );
	if ( ! empty( $uni['private_trail'] ) ) {
		$html .= '<p>Each figure is the lowest open-merit aggregate placed at ' . esc_html( $short ) . ' in that ' . esc_html( $us ) . ' private-sector selection list. Merit falls from list to list as students take seats elsewhere, so the last list is the one to compare with.</p>';
	} else {
		$html .= '<p>Each figure is the lowest aggregate in the newest ' . esc_html( $us ) . ' selection list for ' . esc_html( $short ) . '. ' . esc_html( $us ) . ' does not say a list is the last one, so a later list could have closed a little lower.</p>';
	}

	// 2. Closing position (UHS Final Order of Merit, private sector).
	if ( is_numeric( $row['position'] ?? null ) ) {
		$html .= '<p>Closing merit position ' . esc_html( (string) $year ) . ': the last open-merit seat at ' . esc_html( $short ) . ' went to merit number <strong>' . esc_html( number_format( (int) $row['position'] ) ) . '</strong> in the UHS private-sector Final Order of Merit. If your number in the next private merit list is smaller, you were ahead of that student. Source: ' . am_source_link( (string) ( $row['position_source_url'] ?? '' ) ) . '.</p>';
	}

	// 3. BDS at the same college.
	if ( ! empty( $meta['bds_campus'] ) ) {
		$t = am_pcollege_table( $uni, (string) $meta['bds_campus'], 'BDS (private)', (string) $meta['bds_campus'] );
		if ( $t ) {
			$html .= '<h2>' . esc_html( $short . ' BDS closing merit' ) . '</h2><p>The dental section admits on its own merit list (' . esc_html( (string) $meta['bds_campus'] ) . ').</p>' . $t;
		}
	}

	// 4. Rank among the university's private colleges.
	$names = array_keys( $all );
	$pos   = (int) array_search( $campus, $names, true );
	$n     = count( $names );
	$near  = array();
	if ( $pos > 0 ) {
		$near[] = am_pcollege_name( $uni, $names[ $pos - 1 ] ) . ' (' . esc_html( am_pct( $all[ $names[ $pos - 1 ] ]['closing'] ) ) . ') above it';
	}
	if ( $pos < $n - 1 ) {
		$near[] = am_pcollege_name( $uni, $names[ $pos + 1 ] ) . ' (' . esc_html( am_pct( $all[ $names[ $pos + 1 ] ]['closing'] ) ) . ') below it';
	}
	$list  = ! empty( $uni['private_trail'] ) ? 'final' : 'newest';
	$html .= '<h2>Where ' . esc_html( $short ) . ' ranks</h2><p>In the ' . esc_html( $list . ' ' . $year ) . ' list, ' . esc_html( $short ) . ' had the ' . esc_html( 0 === $pos ? 'highest' : ( $n - 1 === $pos ? 'lowest' : am_ordinal( $pos + 1 ) . ' highest' ) ) . ' open-merit closing merit of the ' . $n . ' private MBBS colleges admitting through ' . esc_html( $us ) . ( $near ? ', with ' . implode( ' and ', $near ) : '' ) . '.</p>';
	$pub = am_college_finals( $uni );
	if ( $pub ) {
		$py  = array_key_first( $pub );
		$low = min( array_map( fn( $r ) => (float) $r['closing'], $pub[ $py ] ) );
		$html .= '<p>For comparison, the lowest Punjab public MBBS college closed at ' . esc_html( am_pct( $low ) ) . ' in the final ' . esc_html( (string) $py ) . ' list, ' . esc_html( number_format( round( $low, 2 ) - round( (float) $row['closing'], 2 ), 2 ) ) . ' points above ' . esc_html( $short ) . '. All colleges are on the <a href="' . esc_url( home_url( '/mdcat-merit-list/' ) ) . '">MDCAT merit list</a>.</p>';
	}

	// 5. MDCAT needed (PM&DC formula, from the UHS data; NUMS uses the same weights).
	$f     = ! empty( $uni['formulas'] ) ? $uni : am_uni( 'uhs' );
	$floor = (int) ceil( 0.55 * 180 - 1e-9 ); // MBBS pass mark 55% (Punjab admission policy 2026-27, para 3; PM&DC)
	$rows  = '';
	$low   = false;
	foreach ( array( 900, 1000, 1050 ) as $m ) {
		$need  = am_college_mdcat_needed( $f, (float) $row['closing'], (float) $m );
		$txt   = $need ? 'about ' . $need . ' / 180' : 'not reachable';
		if ( $need && $need < $floor ) {
			$txt = $floor . ' / 180 (pass mark; ' . $need . ' reaches the aggregate)';
			$low = true;
		}
		$rows .= '<tr><th scope="row">' . esc_html( $m . ' / 1100' ) . '</th><td>' . esc_html( $txt ) . '</td></tr>';
	}
	$html .= '<h2>MDCAT marks needed for ' . esc_html( $short ) . '</h2><p>The MDCAT score that would have reached ' . esc_html( am_pct( $row['closing'] ) ) . ' (the ' . esc_html( $list . ' ' . $year ) . ' closing merit) with the PM&amp;DC formula (50% MDCAT, 40% FSc, 10% Matric), for the same marks in FSc and Matric:</p>'
		. '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( 'MDCAT score needed for ' . $short . ' at its ' . $year . ' closing merit' ) . '</caption><thead><tr><th scope="col">FSc and Matric</th><th scope="col">MDCAT needed</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
		. ( $low ? '<p>Below the closing merit, the real limit is the MDCAT pass mark: at least 55% (' . $floor . '/180) for MBBS, whatever the aggregate (<a href="' . esc_url( home_url( '/mdcat-passing-marks/' ) ) . '">MDCAT passing marks</a>).</p>' : '' )
		. '<p>Merit changes every year, so treat this as a guide. Work out your own aggregate with the <a href="' . esc_url( home_url( '/mdcat-aggregate-calculator/' ) ) . '">MDCAT aggregate calculator</a>. Private colleges set their own fee; check the college and the ' . esc_html( $us ) . ' admission notice for the current session.</p>';

	// 6. Other private college pages.
	$links = am_pcollege_links_html( $uni, $campus );
	if ( $links ) {
		$html .= '<h2>Other private college merit pages</h2>' . $links;
	}
	return $html;
}

/** One line of links to the private college pages of a university (current campus left out). */
function am_pcollege_links_html( array $uni, string $current = '' ): string {
	$links = array();
	foreach ( array_keys( am_pcollege_newest( $uni, 'MBBS (private)' ) ) as $campus ) {
		$m = (array) ( $uni['private_colleges'][ $campus ] ?? array() );
		if ( $campus !== $current && ! empty( $m['path'] ) && am_find_page( (string) $m['path'] ) ) {
			$links[] = '<a href="' . esc_url( home_url( (string) $m['path'] ) ) . '">' . esc_html( (string) ( $m['short'] ?? $campus ) ) . '</a>';
		}
	}
	$hub = (string) ( $uni['meritlist_path'] ?? '' );
	if ( 'uhs' === ( $uni['slug'] ?? '' ) ) {
		$hub = '/mdcat-merit-list/private/';
	}
	if ( $links && '' !== $current && $hub && am_find_page( $hub ) ) {
		$links[] = '<a href="' . esc_url( home_url( $hub ) ) . '">' . esc_html( 'All ' . ( $uni['short'] ?? '' ) . ' private colleges' ) . '</a>';
	}
	return $links ? '<p class="am-links">' . implode( ' · ', $links ) . '</p>' : '';
}

function am_sc_pcollege_links( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_pcollege_links' );
	return am_pcollege_links_html( am_uni( am_uni_attr( $atts ) ) );
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_pcollege', 'am_sc_pcollege' );
		add_shortcode( 'am_pcollege_links', 'am_sc_pcollege_links' );
	}
);

// ------------------------------------------------------------------ [am_mdcat_needed]
/**
 * GSC audit 2026-11-12 guide "MDCAT marks needed": MDCAT score that reached the highest and the
 * lowest closing merit of each UHS sector (newest last list), for FSc and Matric at 900/1000/1050,
 * with the MDCAT pass mark (55% MBBS, 50% BDS) as the floor.
 */
function am_sc_mdcat_needed( $atts ): string {
	$atts  = shortcode_atts( array( 'uni' => 'uhs' ), (array) $atts, 'am_mdcat_needed' );
	$uni   = am_uni( am_uni_attr( $atts ) );
	$marks = array( 900, 1000, 1050 );
	$rows  = '';
	$year  = 0;
	foreach ( array( 'MBBS (public)' => 55, 'BDS (public)' => 50, 'MBBS (private)' => 55, 'BDS (private)' => 50 ) as $prog => $min ) {
		$all = am_pcollege_newest( $uni, $prog );
		if ( ! $all ) {
			continue;
		}
		$floor = (int) ceil( $min / 100 * 180 - 1e-9 );
		$ends  = array( 'Highest' => array_key_first( $all ), 'Lowest' => array_key_last( $all ) );
		foreach ( $ends as $which => $campus ) {
			$r     = $all[ $campus ];
			$year  = max( $year, (int) $r['year'] );
			$cells = '';
			foreach ( $marks as $m ) {
				$need   = am_college_mdcat_needed( $uni, (float) $r['closing'], (float) $m );
				$cells .= '<td>' . esc_html( ! $need ? 'not reachable' : ( $need < $floor ? $floor . ' (pass mark)' : (string) $need ) ) . '</td>';
			}
			$rows .= '<tr><th scope="row">' . esc_html( $prog . ', ' . strtolower( $which ) ) . '<small>' . esc_html( $campus . ', ' . am_pct( $r['closing'] ) ) . '</small></th>' . $cells . '</tr>';
		}
	}
	if ( '' === $rows ) {
		return am_stub( 'am_mdcat_needed', $atts );
	}
	$head = '';
	foreach ( $marks as $m ) {
		$head .= '<th scope="col">' . esc_html( 'FSc and Matric ' . $m . '/1100' ) . '</th>';
	}
	return '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( 'MDCAT score out of 180 that reached the ' . $year . ' closing merit (UHS Punjab, open merit, last list of each sector)' ) . '</caption><thead><tr><th scope="col">Sector and college</th>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
}
add_action( 'init', fn() => add_shortcode( 'am_mdcat_needed', 'am_sc_mdcat_needed' ) );
