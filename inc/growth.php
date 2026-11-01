<?php
/**
 * Phase 5 growth features: hubs, trend SVGs, public data endpoints, embed widget, submit form.
 *
 *   [am_hub type="calculator|meritlist"]  hub tables built from every data/universities/*.json
 *   [am_data_catalog]                      open-data table (+ DataCatalog JSON-LD)
 *   [am_embed_builder]                     copy-paste iframe code for /embed/<slug>/
 *   [am_submit]                            "send us an official merit list" form (mailto, no storage)
 *
 * Routes (not pages, so not in the sitemap):
 *   /data/<slug>.json, /data/index.json   public JSON (CORS *, CC BY 4.0 header fields)
 *   /embed/<slug>/, /embed/predictor/     bare calculator / predictor for iframes (noindex)
 */

defined( 'ABSPATH' ) || exit;

/** Field labels used by hubs and filters. */
const AM_FIELDS = array(
	'medical'     => 'Medical (MBBS / BDS)',
	'engineering' => 'Engineering',
	'computing'   => 'Computing and IT',
	'general'     => 'General universities',
);

/**
 * One-line formula summary: "50% MDCAT + 40% FSc + 10% Matric" (hints win for pooled parts).
 */
function am_formula_summary( array $f ): string {
	$bits = array();
	foreach ( (array) ( $f['parts'] ?? array() ) as $p ) {
		$bits[] = ! empty( $p['hint'] ) ? (string) $p['label'] . ' (' . $p['hint'] . ')' : rtrim( rtrim( number_format( (float) $p['weight'], 2 ), '0' ), '.' ) . '% ' . $p['label'];
	}
	return implode( ' + ', $bits );
}

/**
 * Link label of a pages.json path ("MDCAT aggregate calculator"), falling back to the path.
 */
function am_path_label( string $path ): string {
	$page = am_find_page( $path );
	return $page ? am_page_label( $page ) : $path;
}

/**
 * Records of the default view (list + category) of a university, numeric and sourced.
 */
function am_default_rows( array $uni, string $list = '', string $cat = '' ): array {
	$list = '' !== $list ? $list : (string) ( $uni['defaults']['list'] ?? 'Final' );
	$cat  = '' !== $cat ? $cat : (string) ( $uni['defaults']['category'] ?? 'Open merit' );
	return array_values(
		array_filter(
			(array) ( $uni['merit'] ?? array() ),
			fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) && ( $r['list'] ?? '' ) === $list && ( $r['category'] ?? '' ) === $cat
		)
	);
}

/**
 * Year of a university's default view: `defaults.year` when it has rows, else the newest year
 * up to site data_year, else the newest year at all. Universities move to a new year one by one,
 * when their official figures exist (Phase 7), so pages never show an empty year.
 */
function am_uni_year( array $uni, string $list = '', string $cat = '' ): int {
	$years = array_unique( array_map( fn( $r ) => (int) $r['year'], am_default_rows( $uni, $list, $cat ) ) );
	if ( ! $years ) {
		return 0;
	}
	$want = (int) ( $uni['defaults']['year'] ?? 0 );
	if ( $want && in_array( $want, $years, true ) ) {
		return $want;
	}
	$dy   = (int) am_site( 'data_year' );
	$upto = array_filter( $years, fn( $y ) => $y <= $dy );
	return $upto ? max( $upto ) : max( $years );
}

/**
 * "Season status" line from data/calendar.json, once per university per page (above its first table).
 */
function am_season_status( array $uni ): string {
	static $done = array();
	$slug = (string) ( $uni['slug'] ?? '' );
	$row  = (array) ( am_data( 'calendar' )['universities'][ $slug ] ?? array() );
	if ( isset( $done[ $slug ] ) || empty( $row['status'] ) || empty( $row['source_url'] ) ) {
		return '';
	}
	$done[ $slug ] = true;
	$when          = ! empty( $row['checked'] ) ? ' (checked ' . gmdate( 'j M Y', (int) strtotime( (string) $row['checked'] ) ) . ')' : '';
	return '<p class="am-muted am-status"><strong>Season status' . esc_html( $when ) . ':</strong> ' . esc_html( (string) $row['status'] )
		. ' <a href="' . esc_url( (string) $row['source_url'] ) . '" rel="nofollow noopener" target="_blank">Official page</a></p>';
}

// ------------------------------------------------------------------ [am_percent]
/**
 * Matric / FSc percentage calculator: marks ÷ total × 100 for each exam, live (assets/js/am.js).
 */
function am_sc_percent(): string {
	$rows = array(
		array( 'matric', 'Matric (SSC)', 1100 ),
		array( 'fsc1', 'FSc / HSSC Part-I', 550 ),
		array( 'fsc', 'FSc / HSSC (Part-I + II)', 1100 ),
	);
	$html = '';
	foreach ( $rows as list( $k, $label, $total ) ) {
		$html .= '<div class="am-field am-part"><label for="am-pc-' . $k . '">' . esc_html( $label ) . ' marks</label><div class="am-pair">'
			. '<input id="am-pc-' . $k . '" name="o_' . $k . '" type="number" inputmode="decimal" min="0" step="any">'
			. '<span>out of</span>'
			. '<input aria-label="' . esc_attr( $label ) . ' total marks" name="t_' . $k . '" type="number" inputmode="decimal" min="1" step="any" value="' . $total . '">'
			. '</div><output class="am-pc-out" for="am-pc-' . $k . '" data-am-pc="' . $k . '"></output></div>';
	}
	am_registry(
		'webapp',
		array(
			'id'          => 'percentage',
			'name'        => 'Matric and FSc Percentage Calculator',
			'description' => 'Converts Matric, FSc Part-I and FSc marks into percentages.',
		)
	);
	return '<form class="am-calc" data-am-percent novalidate>' . $html
		. '<p class="am-muted">Percentage = marks obtained ÷ total marks × 100, rounded to 2 decimals. Change the total if your board uses 1200 (Matric) or 600 (Part-I).</p>'
		. '<noscript><p>The calculator needs JavaScript; use the formula above by hand.</p></noscript></form>';
}

// ------------------------------------------------------------------ [am_ibcc]
/**
 * O / A level grade → Pakistani marks (IBCC "Conversion for Equivalence"), averaged to a percentage.
 * Grade table and subject rules: data/site.json → ibcc (with source URLs).
 */
function am_sc_ibcc(): string {
	$cfg = (array) am_site( 'ibcc' );
	if ( empty( $cfg['grades'] ) ) {
		return am_stub( 'am_ibcc', array() );
	}
	$opts = '<option value="">Grade</option>';
	foreach ( (array) $cfg['grades'] as $g => $m ) {
		$opts .= '<option value="' . esc_attr( (string) $g ) . '">' . esc_html( (string) $g ) . '</option>';
	}
	$rows = '';
	foreach ( (array) $cfg['o_subjects'] as $i => $sub ) {
		$id    = 'am-ib-' . $i;
		$rows .= '<div class="am-field am-ib-row" data-o="1"><label for="' . $id . '">' . esc_html( (string) $sub ) . '</label><select id="' . $id . '" name="g' . $i . '">' . $opts . '</select></div>';
	}
	foreach ( (array) $cfg['a_subjects'] as $i => $sub ) {
		$id    = 'am-iba-' . $i;
		$rows .= '<div class="am-field am-ib-row" data-a="1" hidden><label for="' . $id . '">' . esc_html( (string) $sub ) . '</label><select id="' . $id . '" name="a' . $i . '">' . $opts . '</select></div>';
	}
	am_registry(
		'webapp',
		array(
			'id'          => 'ibcc',
			'name'        => 'IBCC Equivalence Calculator (O and A Level)',
			'description' => 'Converts O level and A level grades into Pakistani marks with the IBCC grade table and gives the average percentage.',
		)
	);
	return '<form class="am-calc" data-am-ibcc novalidate>'
		. '<div class="am-field"><label for="am-ib-level">Qualification</label><select id="am-ib-level" name="level"><option value="o">O level / IGCSE (Matric equivalence)</option><option value="a">A level (3 main subjects)</option></select></div>'
		. $rows
		. '<div class="am-field"><label for="am-ib-astar">Marks for A* / grade 9</label><input id="am-ib-astar" name="astar" type="number" min="90" max="100" step="any" value="90"><small>IBCC gives "above 90" and publishes exact subject-wise A* marks for each exam session; enter yours if you know it.</small></div>'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Convert grades</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. am_json_script( 'am-ibcc-data', array( 'grades' => $cfg['grades'] ) )
		. '<noscript><p>The calculator needs JavaScript; use the grade table below by hand.</p></noscript></form>';
}

// ------------------------------------------------------------------ [am_report]
/**
 * Universities, record count and newest data date behind the report frozen at $upto (audit F-05:
 * the intro and the table must give the same count). Same filter as am_sc_report().
 *
 * @return array{records: int, unis: string[], updated: string}
 */
function am_report_stats( int $upto ): array {
	static $memo = array();
	if ( isset( $memo[ $upto ] ) ) {
		return $memo[ $upto ];
	}
	$out  = array(
		'records' => 0,
		'unis'    => array(),
		'updated' => '',
	);
	$unis = am_unis();
	uasort( $unis, fn( $a, $b ) => strcasecmp( (string) ( $a['short'] ?? '' ), (string) ( $b['short'] ?? '' ) ) );
	foreach ( $unis as $uni ) {
		$uni['merit'] = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => (int) ( $r['year'] ?? 0 ) <= $upto ) );
		if ( ! am_default_rows( $uni ) || empty( $uni['meritlist_path'] ) ) {
			continue;
		}
		$out['records'] += count( array_filter( $uni['merit'], fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) ) );
		$out['unis'][]   = (string) ( $uni['short'] ?? '' );
		$out['updated']  = max( $out['updated'], (string) ( $uni['updated'] ?? '' ) );
	}
	return $memo[ $upto ] = $out;
}

/**
 * [am_report_stat year="2026" what="records|unis|updated"]: one figure from am_report_stats().
 */
function am_sc_report_stat( $atts ): string {
	$atts = shortcode_atts(
		array(
			'year' => (string) am_site( 'data_year' ),
			'what' => 'records',
		),
		(array) $atts,
		'am_report_stat'
	);
	$s    = am_report_stats( (int) $atts['year'] );
	if ( 'unis' === $atts['what'] ) {
		$u    = $s['unis'];
		$last = array_pop( $u );
		return esc_html( $u ? implode( ', ', $u ) . ' and ' . $last : (string) $last );
	}
	if ( 'updated' === $atts['what'] ) {
		$ts = strtotime( $s['updated'] );
		return $ts ? '<time datetime="' . esc_attr( $s['updated'] ) . '">' . esc_html( gmdate( 'j F Y', $ts ) ) . '</time>' : '';
	}
	return esc_html( number_format( $s['records'] ) );
}

/**
 * Yearly data report (Phase 7, November): one summary row + key figures per university with
 * closing data, frozen at year="YYYY" (rows after that year are ignored, so the report keeps
 * its numbers when new seasons are added).
 */
function am_sc_report( $atts ): string {
	$atts = shortcode_atts( array( 'year' => (string) am_site( 'data_year' ) ), (array) $atts, 'am_report' );
	$upto = (int) $atts['year'];
	$unis = am_unis();
	uasort( $unis, fn( $a, $b ) => strcasecmp( (string) ( $a['short'] ?? '' ), (string) ( $b['short'] ?? '' ) ) );
	$rows  = '';
	$parts = '';
	$total = am_report_stats( $upto )['records'];
	foreach ( $unis as $uni ) {
		$uni['merit'] = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => (int) ( $r['year'] ?? 0 ) <= $upto ) );
		unset( $uni['defaults']['year'] );
		$def = am_default_rows( $uni );
		if ( ! $def || empty( $uni['meritlist_path'] ) ) {
			continue;
		}
		$year   = am_uni_year( $uni );
		$cur    = array_filter( $def, fn( $r ) => (int) $r['year'] === $year );
		$vals   = array_map( fn( $r ) => (float) $r['closing'], $cur );
		$prev   = array();
		foreach ( $def as $r ) {
			if ( (int) $r['year'] === $year - 1 ) {
				$prev[ $r['program'] . '|' . ( $r['campus'] ?? '' ) . '|' . ( $r['shift'] ?? '' ) ] = (float) $r['closing'];
			}
		}
		$moves = array();
		foreach ( $cur as $r ) {
			$k = $r['program'] . '|' . ( $r['campus'] ?? '' ) . '|' . ( $r['shift'] ?? '' );
			isset( $prev[ $k ] ) && ( $moves[] = (float) $r['closing'] - $prev[ $k ] );
		}
		$avg   = $moves ? array_sum( $moves ) / count( $moves ) : null;
		$short = (string) ( $uni['short'] ?? '' );
		$view  = am_list_label( (string) ( $uni['defaults']['list'] ?? 'Final' ) ) . ', ' . am_cat_label( (string) ( $uni['defaults']['category'] ?? 'Open merit' ) );
		$rows .= '<tr><th scope="row"><a href="' . esc_url( home_url( (string) $uni['meritlist_path'] ) ) . '">' . esc_html( $short ) . '</a><small>' . esc_html( $view ) . '</small></th>'
			. '<td>' . esc_html( (string) $year ) . '</td>'
			. '<td data-v="' . count( $cur ) . '">' . count( $cur ) . '</td>'
			. '<td data-v="' . esc_attr( (string) max( $vals ) ) . '">' . esc_html( am_pct( max( $vals ) ) ) . '</td>'
			. '<td data-v="' . esc_attr( (string) min( $vals ) ) . '">' . esc_html( am_pct( min( $vals ) ) ) . '</td>'
			. ( null === $avg ? '<td>— <small>one year only</small></td>' : '<td data-v="' . esc_attr( (string) round( $avg, 3 ) ) . '">' . esc_html( ( $avg > 0 ? '+' : '' ) . number_format( $avg, 2 ) ) . '<small>' . count( $moves ) . ' programs, vs ' . ( $year - 1 ) . '</small></td>' )
			. '</tr>';
		$srcs   = array_values( array_unique( array_map( fn( $r ) => (string) $r['source_url'] . '|' . (string) ( $r['archive_url'] ?? '' ), $cur ) ) );
		$links  = implode( ', ', array_map( fn( $k ) => am_source_link( ...explode( '|', $k, 2 ) ), $srcs ) );
		$parts .= '<h3>' . esc_html( (string) ( $uni['name'] ?? $short ) ) . '</h3><ul>';
		foreach ( am_quotables( $uni ) as $q ) {
			$parts .= '<li>' . esc_html( $q ) . '</li>';
		}
		$parts .= '</ul><p class="am-muted">Source: ' . $links . '. Full table: <a href="' . esc_url( home_url( (string) $uni['meritlist_path'] ) ) . '">' . esc_html( am_path_label( (string) $uni['meritlist_path'] ) ) . '</a>.</p>';
	}
	if ( ! $rows ) {
		return am_stub( 'am_report', $atts );
	}
	return '<div class="am-table-wrap"><table class="am-table"><caption>Newest official closing merits by university, up to ' . esc_html( (string) $upto ) . ' (' . esc_html( number_format( $total ) ) . ' records)</caption><thead><tr><th scope="col">University and view</th><th scope="col">Newest year</th><th scope="col">Programs</th><th scope="col">Highest closing</th><th scope="col">Lowest closing</th><th scope="col">Average change</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
		. '<h2>University by university</h2>' . $parts;
}

// ------------------------------------------------------------------ [am_hub]
function am_sc_hub( $atts ): string {
	$atts = shortcode_atts( array( 'type' => 'calculator' ), (array) $atts, 'am_hub' );
	if ( 'guides' === $atts['type'] ) {
		return am_hub_guides();
	}
	$unis = am_unis();
	uasort( $unis, fn( $a, $b ) => strcasecmp( (string) ( $a['short'] ?? '' ), (string) ( $b['short'] ?? '' ) ) );

	if ( 'meritlist' === $atts['type'] ) {
		$body = '';
		$none = array();
		foreach ( $unis as $uni ) {
			$all = array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) );
			if ( ! $all || empty( $uni['meritlist_path'] ) ) {
				if ( ! empty( $uni['calculator_path'] ) ) {
					$none[] = '<a href="' . esc_url( home_url( (string) $uni['calculator_path'] ) ) . '">' . esc_html( am_path_label( (string) $uni['calculator_path'] ) ) . '</a>';
				}
				continue;
			}
			$years = array_unique( array_map( fn( $r ) => (int) $r['year'], $all ) );
			sort( $years );
			$def   = am_default_rows( $uni );
			$ly    = am_uni_year( $uni );
			$vals  = array_map( fn( $r ) => (float) $r['closing'], array_filter( $def, fn( $r ) => (int) $r['year'] === $ly ) );
			$range = $vals ? am_pct( max( $vals ) ) . ' to ' . am_pct( min( $vals ) ) . ' <small>' . esc_html( $ly . ', ' . ( $uni['defaults']['list'] ?? 'Final' ) . ', ' . ( $uni['defaults']['category'] ?? 'Open merit' ) ) . '</small>' : '—';
			$body .= '<tr><th scope="row"><a href="' . esc_url( home_url( (string) $uni['meritlist_path'] ) ) . '">' . esc_html( am_path_label( (string) $uni['meritlist_path'] ) ) . '</a><small>' . esc_html( (string) $uni['name'] ) . '</small></th>'
				. '<td>' . esc_html( (string) ( $uni['province'] ?? '' ) ) . '</td>'
				. '<td>' . esc_html( implode( ', ', $years ) ) . '</td>'
				. '<td data-v="' . count( $all ) . '">' . count( $all ) . '</td>'
				. '<td>' . $range . '</td></tr>';
		}
		$out = '<div class="am-table-wrap"><table class="am-table"><caption>Closing merit lists by university</caption><thead><tr><th scope="col">University</th><th scope="col">Province</th><th scope="col">Years</th><th scope="col">Records</th><th scope="col">Highest to lowest closing</th></tr></thead><tbody>' . $body . '</tbody></table></div>';
		if ( $none ) {
			$out .= '<p>These universities have a calculator but no official program-wise closing merit online yet, so we show no merit list for them: ' . implode( ', ', $none ) . '.</p>';
		}
		return $out . am_hub_more();
	}

	$groups = array();
	foreach ( $unis as $uni ) {
		if ( empty( $uni['calculator_path'] ) || empty( $uni['formulas'] ) ) {
			continue;
		}
		$groups[ (string) ( $uni['field'] ?? 'general' ) ][] = $uni;
	}
	$out = '';
	foreach ( AM_FIELDS as $field => $label ) {
		if ( empty( $groups[ $field ] ) ) {
			continue;
		}
		$body = '';
		foreach ( $groups[ $field ] as $uni ) {
			$forms = '';
			foreach ( (array) $uni['formulas'] as $f ) {
				$forms .= '<li><strong>' . esc_html( (string) $f['label'] ) . ':</strong> ' . esc_html( am_formula_summary( $f ) ) . '</li>';
			}
			$ml    = ! empty( $uni['meritlist_path'] ) ? '<small><a href="' . esc_url( home_url( (string) $uni['meritlist_path'] ) ) . '">' . esc_html( am_path_label( (string) $uni['meritlist_path'] ) ) . '</a></small>' : '';
			$body .= '<tr><th scope="row"><a href="' . esc_url( home_url( (string) $uni['calculator_path'] ) ) . '">' . esc_html( am_path_label( (string) $uni['calculator_path'] ) ) . '</a>' . $ml . '</th><td><ul>' . $forms . '</ul></td></tr>';
		}
		$out .= '<h2 id="' . esc_attr( $field ) . '">' . esc_html( $label ) . ' aggregate calculators</h2><div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $label . ': official aggregate formulas' ) . '</caption><thead><tr><th scope="col">Calculator</th><th scope="col">Formula</th></tr></thead><tbody>' . $body . '</tbody></table></div>';
	}
	return $out . am_hub_more();
}

/**
 * Closing line of both hubs: open data and embed pages (audit F-18).
 */
/** [am_hub type="guides"]: every /guides/ page plus the season pages, as cards (newest first). */
function am_hub_guides(): string {
	$pages = array_filter(
		array_filter( (array) ( am_data( 'pages' )['pages'] ?? array() ), 'is_array' ),
		fn( $g ) => ( str_starts_with( (string) $g['path'], '/guides/' ) && '/guides/' !== $g['path'] ) || in_array( $g['path'], array( '/mdcat-passing-marks/', '/uhs-mbbs-admission/' ), true )
	);
	usort( $pages, fn( $a, $b ) => strcmp( (string) ( $b['published'] ?? '' ), (string) ( $a['published'] ?? '' ) ) );
	$out = '';
	foreach ( $pages as $g ) {
		$out .= '<a class="am-guide" href="' . esc_url( home_url( (string) $g['path'] ) ) . '"><strong>' . esc_html( am_tokens( (string) ( $g['h1'] ?? $g['title'] ) ) ) . '</strong><span>' . esc_html( am_tokens( (string) ( $g['subtitle'] ?? $g['description'] ?? '' ) ) ) . '</span></a>';
	}
	return $out ? '<div class="am-grid am-grid--2">' . $out . '</div>' : '';
}

function am_hub_more(): string {
	$links = array();
	if ( am_find_page( '/data/' ) ) {
		$links[] = 'download every table as JSON from our <a href="' . esc_url( home_url( '/data/' ) ) . '">open merit data</a>';
	}
	if ( am_find_page( '/embed/' ) ) {
		$links[] = 'put a calculator on your own site with our <a href="' . esc_url( home_url( '/embed/' ) ) . '">free embed code</a>';
	}
	return $links ? '<p>Teachers, academies and bloggers: ' . implode( ', or ', $links ) . '.</p>' : '';
}

// ------------------------------------------------------------------ trend SVG (master plan 4.2 item 6)
/**
 * Dot-plot ("dumbbell") of the top 8 programs that have at least two years in the default view.
 * Server-side SVG, no library; the table above it is the accessible data view.
 */
function am_trend_svg( array $uni, string $list, string $cat ): string {
	$series = array();
	foreach ( am_default_rows( $uni, $list, $cat ) as $r ) {
		$key = implode( '|', array( $r['program'], $r['campus'] ?? '', $r['shift'] ?? '', $r['category'] ?? '' ) );
		$series[ $key ]['r']                        = $r;
		$series[ $key ]['y'][ (int) $r['year'] ]    = (float) $r['closing'];
	}
	$series = array_filter( $series, fn( $s ) => count( $s['y'] ) >= 2 );
	if ( ! $series ) {
		return '';
	}
	foreach ( $series as &$s ) {
		ksort( $s['y'] );
		$s['last'] = end( $s['y'] );
	}
	unset( $s );
	uasort( $series, fn( $a, $b ) => $b['last'] <=> $a['last'] );
	$series = array_slice( $series, 0, 8, true );
	$years  = array();
	$vals   = array();
	foreach ( $series as $s ) {
		$years = array_merge( $years, array_keys( $s['y'] ) );
		$vals  = array_merge( $vals, array_values( $s['y'] ) );
	}
	$years = array_values( array_unique( $years ) );
	sort( $years );
	$lo = floor( min( $vals ) - 0.5 );
	$hi = ceil( max( $vals ) + 0.5 );
	if ( $hi - $lo < 2 ) {
		$hi = $lo + 2;
	}
	$w    = 360;
	$x0   = 8;
	$x1   = $w - 100;
	$row  = 40;
	$top  = 30;
	$h    = $top + $row * count( $series ) + 24;
	$x    = fn( $v ) => round( $x0 + ( $v - $lo ) / ( $hi - $lo ) * ( $x1 - $x0 ), 1 );
	$last = end( $years );
	$short = (string) ( $uni['short'] ?? '' );

	$svg  = '<svg class="am-trend" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="am-tr-' . esc_attr( am_slug( $short . ' ' . $list . ' ' . $cat ) ) . '">';
	$svg .= '<title id="am-tr-' . esc_attr( am_slug( $short . ' ' . $list . ' ' . $cat ) ) . '">' . esc_html( $short . ' closing merit trend, top ' . count( $series ) . ' programs, ' . $years[0] . ' to ' . $last ) . '</title>';
	// Legend.
	$lx = $x0;
	foreach ( $years as $yr ) {
		$cls  = $yr === $last ? 'am-tr-new' : 'am-tr-old';
		$svg .= '<circle class="' . $cls . '" cx="' . ( $lx + 5 ) . '" cy="12" r="5"/><text x="' . ( $lx + 14 ) . '" y="16">' . esc_html( (string) $yr ) . '</text>';
		$lx  += 56;
	}
	$i = 0;
	foreach ( $series as $s ) {
		$r     = $s['r'];
		$y     = $top + $row * $i;
		$cy    = $y + 26;
		$name  = trim( $r['program'] . ( ! empty( $r['campus'] ) ? ', ' . $r['campus'] : '' ) . ( ! empty( $r['shift'] ) ? ', ' . $r['shift'] : '' ) );
		$name  = mb_strlen( $name ) > 58 ? mb_substr( $name, 0, 56 ) . '…' : $name;
		$ys    = array_keys( $s['y'] );
		$d     = $s['last'] - $s['y'][ $ys[ count( $ys ) - 2 ] ];
		$tip   = $name . ': ' . implode( ', ', array_map( fn( $k, $v ) => $k . ' ' . am_pct( $v ), $ys, $s['y'] ) );
		$svg  .= '<g><title>' . esc_html( $tip ) . '</title>';
		$svg  .= '<text x="' . $x0 . '" y="' . ( $y + 12 ) . '">' . esc_html( $name ) . '</text>';
		$svg  .= '<text class="am-tr-v" x="' . ( $w - 4 ) . '" y="' . ( $cy + 4 ) . '" text-anchor="end">' . esc_html( am_pct( $s['last'] ) . ' (' . ( $d > 0 ? '+' : '' ) . number_format( $d, 2 ) . ')' ) . '</text>';
		$svg  .= '<line class="am-tr-track" x1="' . $x0 . '" x2="' . $x1 . '" y1="' . $cy . '" y2="' . $cy . '"/>';
		$svg  .= '<line class="am-tr-link" x1="' . $x( min( $s['y'] ) ) . '" x2="' . $x( max( $s['y'] ) ) . '" y1="' . $cy . '" y2="' . $cy . '"/>';
		foreach ( $s['y'] as $yr => $v ) {
			$svg .= '<circle class="' . ( $yr === $last ? 'am-tr-new' : 'am-tr-old' ) . '" cx="' . $x( $v ) . '" cy="' . $cy . '" r="5"/>';
		}
		$svg .= '</g>';
		++$i;
	}
	$ay   = $h - 8;
	$mid  = round( ( $lo + $hi ) / 2, 1 );
	$svg .= '<text class="am-tr-ax" x="' . $x0 . '" y="' . $ay . '">' . esc_html( $lo . '%' ) . '</text>';
	$svg .= '<text class="am-tr-ax" x="' . $x( $mid ) . '" y="' . $ay . '" text-anchor="middle">' . esc_html( $mid . '%' ) . '</text>';
	$svg .= '<text class="am-tr-ax" x="' . $x1 . '" y="' . $ay . '" text-anchor="end">' . esc_html( $hi . '%' ) . '</text>';
	$svg .= '</svg>';

	return '<figure class="am-figure">' . $svg . '<figcaption>' . esc_html( $short . ' closing merit trend: the ' . count( $series ) . ' highest programs with at least two years of data (' . am_list_label( $list ) . ', ' . am_cat_label( $cat ) . '). Grey = earlier year, green = ' . $last . '; the number in brackets is the change from the year before. Exact figures are in the table above.' ) . '</figcaption></figure>';
}

/**
 * "What can I get with X%?" box → /predictor/?agg=X&uni=… (works without JS). Once per university per page.
 */
function am_check_box( array $uni ): string {
	static $done = array();
	$short = (string) ( $uni['short'] ?? '' );
	if ( isset( $done[ $short ] ) || ! am_find_page( '/predictor/' ) ) {
		return '';
	}
	$done[ $short ] = true;
	$id   = 'am-chk-' . am_slug( $short );
	$calc = ! empty( $uni['calculator_path'] ) ? ' Not sure of your aggregate? Use the <a href="' . esc_url( home_url( (string) $uni['calculator_path'] ) ) . '">' . esc_html( $short ) . ' aggregate calculator</a>.' : '';
	return '<form class="am-check-box" action="' . esc_url( home_url( '/predictor/' ) ) . '" method="get"><h2>What can I get with my aggregate?</h2>'
		. '<div class="am-field"><label for="' . esc_attr( $id ) . '">Your ' . esc_html( $short ) . ' aggregate (%)</label><input id="' . esc_attr( $id ) . '" name="agg" type="number" inputmode="decimal" min="1" max="100" step="any" required></div>'
		. '<input type="hidden" name="uni" value="' . esc_attr( $short ) . '">'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Show programs in my range</button></div>'
		. '<p class="am-muted">Opens the merit predictor with ' . esc_html( $short ) . ' selected.' . $calc . '</p></form>';
}

// ------------------------------------------------------------------ public data
/** Public URL of a university's JSON. */
function am_data_url( string $slug ): string {
	return home_url( '/data/' . rawurlencode( $slug ) . '.json' );
}

/** Catalogue of public datasets (also /data/index.json). */
function am_data_index(): array {
	$items = array();
	foreach ( am_unis() as $slug => $uni ) {
		$merit = array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) );
		$years = array_values( array_unique( array_map( fn( $r ) => (int) $r['year'], $merit ) ) );
		sort( $years );
		$path    = (string) ( $uni['meritlist_path'] ?? $uni['calculator_path'] ?? '' );
		$items[] = array(
			'slug'       => $slug,
			'university' => (string) ( $uni['name'] ?? '' ),
			'short'      => (string) ( $uni['short'] ?? '' ),
			'records'    => count( $merit ),
			'years'      => $years,
			'formulas'   => count( (array) ( $uni['formulas'] ?? array() ) ),
			'updated'    => (string) ( $uni['updated'] ?? '' ),
			'json'       => am_data_url( $slug ),
			'path'       => $path,
			'page'       => '' !== $path ? home_url( $path ) : '',
		);
	}
	return $items;
}

/** Licence block added at the top of every public JSON response. */
function am_data_meta(): array {
	return array(
		'license'     => (string) am_site( 'data_license.url' ),
		'attribution' => (string) am_site( 'data_license.attribution' ),
		'publisher'   => am_site_url(),
		'methodology' => home_url( '/methodology/' ),
	);
}

function am_send_json_file( array $payload ): void {
	status_header( 200 );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );
	header( 'Link: <' . esc_url_raw( (string) am_site( 'data_license.url' ) ) . '>; rel="license"' );
	echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

// ------------------------------------------------------------------ [am_data_catalog]
function am_sc_data_catalog(): string {
	$body = '';
	$ds   = array();
	foreach ( am_data_index() as $d ) {
		$what  = $d['records'] ? $d['records'] . ' closing merits (' . implode( ', ', $d['years'] ) . ')' : 'Formula only';
		$page  = $d['path'] ? '<small><a href="' . esc_url( $d['page'] ) . '">' . esc_html( am_path_label( $d['path'] ) ) . '</a></small>' : '';
		$body .= '<tr><th scope="row">' . esc_html( $d['short'] ) . $page . '</th><td data-v="' . (int) $d['records'] . '">' . esc_html( $what ) . '</td><td><a href="' . esc_url( $d['json'] ) . '">' . esc_html( '/data/' . $d['slug'] . '.json' ) . '</a></td></tr>';
		if ( $d['records'] && $d['page'] ) {
			$ds[] = array( '@id' => $d['page'] . '#dataset-' . am_slug( $d['slug'] ) );
		}
	}
	am_registry(
		'catalog',
		array(
			'name'     => (string) am_site( 'brand' ) . ' open data: Pakistani university aggregate formulas and closing merits',
			'datasets' => $ds,
			'index'    => home_url( '/data/index.json' ),
		)
	);
	return '<div class="am-table-wrap"><table class="am-table"><caption>Public JSON files (CC BY 4.0)</caption><thead><tr><th scope="col">University</th><th scope="col">What is inside</th><th scope="col">JSON</th></tr></thead><tbody>' . $body . '</tbody></table></div>'
		. '<p>Machine-readable list of all files: <a href="' . esc_url( home_url( '/data/index.json' ) ) . '">/data/index.json</a>.</p>';
}

// ------------------------------------------------------------------ embed
/** Embeddable widgets: calculators (by slug) + the predictor. */
function am_embed_targets(): array {
	$out = array();
	foreach ( am_unis() as $slug => $uni ) {
		if ( ! empty( $uni['formulas'] ) && ! empty( $uni['calculator_path'] ) ) {
			$out[ $slug ] = array(
				'label' => am_path_label( (string) $uni['calculator_path'] ),
				'page'  => home_url( (string) $uni['calculator_path'] ),
			);
		}
	}
	uasort( $out, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );
	if ( am_find_page( '/predictor/' ) ) {
		$out = array(
			'predictor' => array(
				'label' => 'Merit predictor (all universities)',
				'page'  => home_url( '/predictor/' ),
			),
		) + $out;
	}
	return $out;
}

function am_sc_embed_builder(): string {
	$targets = am_embed_targets();
	if ( ! $targets ) {
		return '';
	}
	$opts = '';
	$cfg  = array();
	foreach ( $targets as $slug => $t ) {
		$opts        .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $t['label'] ) . '</option>';
		$cfg[ $slug ] = array(
			'label' => $t['label'],
			'page'  => $t['page'],
			'src'   => home_url( '/embed/' . $slug . '/' ),
		);
	}
	$first = reset( $cfg );
	$key   = array_key_first( $cfg );
	$code  = am_embed_code( $key, $first );
	return '<div class="am-embed-builder" data-am-embed>'
		. '<div class="am-field"><label for="am-emb-pick">Widget</label><select id="am-emb-pick">' . $opts . '</select></div>'
		. '<div class="am-field"><label for="am-emb-code">Code to paste into your page (HTML)</label><textarea id="am-emb-code" rows="7" readonly>' . esc_textarea( $code ) . '</textarea></div>'
		. '<div class="am-actions"><button type="button" class="am-btn am-btn--primary am-js-only" data-am-copy="' . esc_attr( $code ) . '">Copy code</button> <button type="button" class="am-btn am-js-only" data-am-preview>Preview</button></div>'
		. '<div class="am-embed-preview" hidden></div>'
		. am_json_script( 'am-embed-data', $cfg )
		. '</div>';
}

/** The copy-paste snippet: iframe + a plain link (the link is what search engines credit) + auto-height listener. */
function am_embed_code( string $slug, array $t ): string {
	$id = 'am-embed-' . $slug;
	return '<iframe id="' . $id . '" src="' . $t['src'] . '" title="' . $t['label'] . '" style="width:100%;max-width:640px;height:720px;border:1px solid #dfe3e1;border-radius:8px" loading="lazy"></iframe>' . "\n"
		. '<p style="font-size:14px"><a href="' . $t['page'] . '">' . $t['label'] . '</a> by Apna Merit</p>' . "\n"
		. '<script>addEventListener("message",function(e){if(e.origin==="' . untrailingslashit( am_site_url() ) . '"&&e.data&&e.data.amEmbed==="' . $slug . '"){document.getElementById("' . $id . '").style.height=e.data.h+"px"}})</script>';
}

/** Bare widget page for an iframe. */
function am_render_embed( string $slug ): void {
	$targets = am_embed_targets();
	if ( ! isset( $targets[ $slug ] ) ) {
		return;
	}
	$t = $targets[ $slug ];
	add_filter( 'am_calc_url', fn() => $t['page'] );
	$body = 'predictor' === $slug ? am_sc_predictor( array() ) : am_sc_calculator( array( 'uni' => $slug ) );
	status_header( 200 );
	header( 'X-Robots-Tag: noindex, follow' );
	header( 'Content-Type: text/html; charset=utf-8' );
	$css = AM_DIR . '/assets/css/am.css';
	$js  = AM_DIR . '/assets/js/am.js';
	?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, follow">
<title><?php echo esc_html( $t['label'] . ' | ' . am_site( 'brand' ) ); ?></title>
<link rel="canonical" href="<?php echo esc_url( $t['page'] ); ?>">
<base target="_blank">
<script>document.documentElement.className+=' am-js'</script>
<style><?php echo is_readable( $css ) ? file_get_contents( $css ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></style>
</head>
<body class="am-embed" data-am-embed-id="<?php echo esc_attr( $slug ); ?>">
<main class="am-embed-main">
<p class="am-embed-title"><strong><?php echo esc_html( $t['label'] ); ?></strong></p>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the shortcode ?>
<p class="am-muted am-powered">Powered by <a href="<?php echo esc_url( $t['page'] ); ?>" rel="noopener"><?php echo esc_html( (string) am_site( 'brand' ) ); ?></a>. Figures from official university sources.</p>
</main>
<script src="<?php echo esc_url( AM_URI . '/assets/js/am.js?ver=' . (int) @filemtime( $js ) ); ?>" defer></script>
</body>
</html>
	<?php
	exit;
}

// ------------------------------------------------------------------ [am_submit]
function am_sc_submit(): string {
	$email = sanitize_email( (string) am_site( 'contact.email' ) );
	if ( ! $email ) {
		return '';
	}
	$unis = '';
	foreach ( am_unis() as $uni ) {
		$unis .= '<option>' . esc_html( (string) $uni['short'] ) . '</option>';
	}
	return '<form class="am-calc am-submit" data-am-submit="' . esc_attr( $email ) . '" novalidate>'
		. '<div class="am-field"><label for="am-sub-uni">University</label><input id="am-sub-uni" name="uni" list="am-sub-unis" required autocomplete="off"><datalist id="am-sub-unis">' . $unis . '</datalist></div>'
		. '<div class="am-field"><label for="am-sub-list">Which list?</label><select id="am-sub-list" name="list"><option>1st merit list</option><option>2nd merit list</option><option>3rd or later list</option><option>Final / closing merit</option><option>Prospectus or merit formula</option></select></div>'
		. '<div class="am-field"><label for="am-sub-year">Admission year</label><input id="am-sub-year" name="year" type="number" inputmode="numeric" min="2015" max="2035" value="' . esc_attr( (string) am_site( 'season_year' ) ) . '"></div>'
		. '<div class="am-field"><label for="am-sub-url">Official link (university website or portal)</label><input id="am-sub-url" name="url" type="url" inputmode="url" placeholder="https://" required></div>'
		. '<div class="am-field"><label for="am-sub-note">Anything else? (optional)</label><textarea id="am-sub-note" name="note" rows="3"></textarea></div>'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Open email with these details</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<p class="am-muted">Nothing is saved on this website: the button opens your own email app with a message to <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>. You can also attach the PDF there.</p>'
		. '</form>';
}

// ------------------------------------------------------------------ routes
add_action(
	'template_redirect',
	function () {
		$path = am_request_path();
		if ( '/data/index.json' === $path ) {
			am_send_json_file( am_data_meta() + array( 'datasets' => am_data_index() ) );
		}
		if ( preg_match( '#^/data/([a-z0-9-]+)\.json$#', $path, $m ) ) {
			$uni = am_unis()[ $m[1] ] ?? null;
			if ( $uni ) {
				am_send_json_file( am_data_meta() + $uni );
			}
		}
		if ( preg_match( '#^/embed/([a-z0-9-]+)/?$#', $path, $m ) ) {
			if ( ! str_ends_with( $path, '/' ) && isset( am_embed_targets()[ $m[1] ] ) ) {
				wp_safe_redirect( home_url( $path . '/' ), 301 );
				exit;
			}
			am_render_embed( $m[1] );
		}
	},
	-1
);

// DataCatalog node for /data/.
add_filter(
	'am_jsonld_graph',
	function ( array $graph ) {
		$cat = am_registry( 'catalog' );
		if ( ! $cat ) {
			return $graph;
		}
		$c                = $cat[0];
		$graph['catalog'] = array(
			'@type'       => 'DataCatalog',
			'@id'         => home_url( '/data/#catalog' ),
			'name'        => $c['name'],
			'url'         => home_url( '/data/' ),
			'license'     => am_site( 'data_license.url' ),
			'creditText'  => am_site( 'data_license.attribution' ),
			'creator'     => array( '@id' => am_site_url() . '#organization' ),
			'publisher'   => array( '@id' => am_site_url() . '#organization' ),
			'dataset'     => $c['datasets'],
		);
		return $graph;
	},
	20
);

add_action(
	'init',
	function () {
		add_shortcode( 'am_hub', 'am_sc_hub' );
		add_shortcode( 'am_data_catalog', 'am_sc_data_catalog' );
		add_shortcode( 'am_embed_builder', 'am_sc_embed_builder' );
		add_shortcode( 'am_submit', 'am_sc_submit' );
		add_shortcode( 'am_report', 'am_sc_report' );
		add_shortcode( 'am_report_stat', 'am_sc_report_stat' );
		add_shortcode( 'am_percent', 'am_sc_percent' );
		add_shortcode( 'am_ibcc', 'am_sc_ibcc' );
	}
);
