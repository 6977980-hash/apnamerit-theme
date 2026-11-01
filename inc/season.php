<?php
/**
 * Season pages (audit 2026-11-01 G-01, G-02):
 * [am_elig_check uni=""]  minimum marks table + live "am I eligible?" check from the university's
 *                         `eligibility` floors (same data the calculators use).
 * [am_season uni=""]      the current session's official schedule from data/calendar.json seasons.<slug>.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Smallest whole number of marks out of $total that reaches $pct percent.
 */
function am_min_marks( float $pct, float $total ): int {
	return (int) ceil( $pct * $total / 100 - 1e-9 );
}

// ------------------------------------------------------------------ [am_elig_check]
function am_sc_elig_check( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_elig_check' );
	$uni  = am_uni( am_uni_attr( $atts ) );
	$f    = (array) ( $uni['formulas'][0] ?? array() );
	if ( empty( $uni['eligibility'] ) || ! $f ) {
		return am_stub( 'am_elig_check', $atts );
	}
	$id     = am_slug( (string) ( $uni['slug'] ?? '' ) );
	$short  = (string) ( $uni['short'] ?? $uni['name'] ?? '' );
	$floors = am_formula_floors( $uni, $f );
	$parts  = array();
	foreach ( (array) $f['parts'] as $p ) {
		foreach ( $floors as $fl ) {
			if ( $fl['part'] === $p['key'] ) {
				$parts[ $p['key'] ] = array(
					'key'   => (string) $p['key'],
					'label' => (string) $p['label'],
					'total' => (float) $p['total'],
				);
			}
		}
	}
	// Programs = applies_to of floors limited to programs (MBBS, BDS); a floor without `programs` covers all.
	$programs = array();
	foreach ( $floors as $fl ) {
		if ( '' !== $fl['programs'] ) {
			$programs[] = (string) $fl['applies_to'];
		}
	}
	$programs = array_values( array_unique( $programs ) ) ?: array( $short );

	// Table: program x part = minimum % and marks (no JavaScript needed).
	$head = '<th scope="col">Program</th>';
	foreach ( $parts as $p ) {
		$head .= '<th scope="col">' . esc_html( $p['label'] ) . ' minimum</th>';
	}
	$rows = '';
	foreach ( $programs as $prog ) {
		$rows .= '<tr><th scope="row">' . esc_html( $prog ) . '</th>';
		foreach ( $parts as $p ) {
			$need = 0.0;
			foreach ( $floors as $fl ) {
				if ( $fl['part'] === $p['key'] && am_floor_covers( $fl, $prog ) ) {
					$need = max( $need, $fl['min_pct'] );
				}
			}
			$rows .= '<td>' . ( $need > 0 ? esc_html( am_num( $need ) . '% (' . am_min_marks( $need, $p['total'] ) . ' of ' . am_num( $p['total'] ) . ')' ) : '—' ) . '</td>';
		}
		$rows .= '</tr>';
	}
	$srcs = array_unique( array_filter( array_column( $floors, 'source_url' ) ) );
	$html = '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $short ) . ' minimum marks to be eligible</caption><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
		. '<p class="am-muted">Marks are the lowest whole number that reaches the percentage on the usual totals; with another total, the percentage counts. Source: ' . implode( ', ', array_map( fn( $u ) => am_source_link( (string) $u ), $srcs ) ) . '.</p>';

	$fields = '';
	foreach ( $parts as $p ) {
		$k       = esc_attr( $id . '-el-' . $p['key'] );
		$fields .= '<div class="am-field am-part"><label for="am-o-' . $k . '">' . esc_html( am_marks_label( $p['label'] ) ) . '</label><div class="am-pair">'
			. '<input id="am-o-' . $k . '" name="o_' . esc_attr( $p['key'] ) . '" type="number" inputmode="decimal" min="0" step="any" required>'
			. '<span>out of</span>'
			. '<input aria-label="' . esc_attr( $p['label'] ) . ' total marks" name="t_' . esc_attr( $p['key'] ) . '" type="number" inputmode="decimal" min="1" step="any" value="' . esc_attr( am_num( $p['total'] ) ) . '" required>'
			. '</div></div>';
	}
	$config = array(
		'uni'      => $short,
		'calc'     => $id,
		'floors'   => $floors,
		'programs' => $programs,
		'calcUrl'  => ! empty( $uni['calculator_path'] ) ? home_url( (string) $uni['calculator_path'] ) : '',
	);
	return $html
		. '<form class="am-calc" data-am-elig="' . esc_attr( $id ) . '" novalidate><fieldset class="am-formula"><legend>Check your eligibility</legend>' . $fields . '</fieldset>'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Check eligibility</button> <button type="reset" class="am-btn">Clear</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<noscript><p>The check needs JavaScript. Compare your marks with the table above by hand.</p></noscript>'
		. am_json_script( 'am-elig-data', $config )
		. '</form>';
}

// ------------------------------------------------------------------ [am_season]
/**
 * "Fri 13 Nov 2026" or "3 to 5 Nov 2026" from "2026-11-13" or "2026-11-03/2026-11-05".
 */
function am_season_date( string $d ): string {
	$p = explode( '/', $d );
	$a = (int) strtotime( $p[0] . ' 12:00 UTC' );
	if ( count( $p ) < 2 ) {
		return gmdate( 'D j M Y', $a );
	}
	$b = (int) strtotime( $p[1] . ' 12:00 UTC' );
	return ( gmdate( 'M Y', $a ) === gmdate( 'M Y', $b ) ? gmdate( 'j', $a ) : gmdate( 'j M', $a ) ) . ' to ' . gmdate( 'j M Y', $b );
}

function am_sc_season( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_season' );
	$slug = am_uni_attr( $atts );
	$s    = (array) ( am_data( 'calendar' )['seasons'][ $slug ] ?? array() );
	if ( empty( $s['steps'] ) || empty( $s['source_url'] ) ) {
		return am_stub( 'am_season', $atts );
	}
	$cols = (array) ( $s['columns'] ?? array() );
	$head = '<th scope="col">Step</th>';
	foreach ( $cols as $c ) {
		$head .= '<th scope="col">' . esc_html( (string) $c ) . '</th>';
	}
	$rows = '';
	foreach ( (array) $s['steps'] as $st ) {
		$rows .= '<tr><th scope="row">' . esc_html( (string) $st['step'] ) . '</th>';
		foreach ( $cols as $c ) {
			$d     = (string) ( $st[ $c ] ?? '' );
			$end   = substr( $d, -10 );
			$guide = ! empty( $st['guide'] ) && am_find_page( (string) $st['guide'] ) ? ' data-am-guide="' . esc_attr( home_url( (string) $st['guide'] ) ) . '" data-am-guide-label="' . esc_attr( am_page_label( am_find_page( (string) $st['guide'] ) ) ) . '"' : '';
			$rows .= '' === $d ? '<td>—</td>' : '<td data-am-date="' . esc_attr( $end ) . '" data-am-start="' . esc_attr( substr( $d, 0, 10 ) ) . '" data-am-step="' . esc_attr( $c . ': ' . lcfirst( (string) $st['step'] ) ) . '"' . $guide . '>' . esc_html( am_season_date( $d ) ) . '</td>';
		}
		$rows .= '</tr>';
	}
	$when = ! empty( $s['checked'] ) ? ' Checked ' . gmdate( 'j M Y', (int) strtotime( (string) $s['checked'] ) ) . '.' : '';
	return '<div class="am-table-wrap" data-am-season><table class="am-table"><caption>' . esc_html( (string) $s['label'] . ', session ' . (string) $s['session'] . ': official schedule' ) . '</caption><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
		. '<p class="am-muted" data-am-next hidden></p>'
		. '<p class="am-muted">Source: <a href="' . esc_url( (string) $s['source_url'] ) . '" rel="noopener" target="_blank">' . esc_html( (string) ( $s['source_label'] ?? am_source_label( (string) $s['source_url'] ) ) ) . '</a>.' . esc_html( $when ) . ' ' . esc_html( (string) ( $s['note'] ?? '' ) ) . '</p>'
		. ( function_exists( 'am_alerts_html' ) ? am_alerts_html( $slug ) : '' );
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_elig_check', 'am_sc_elig_check' );
		add_shortcode( 'am_season', 'am_sc_season' );
	}
);
