<?php
/**
 * [am_all_aggregates]: one profile, every university (idea 2026-11-02 #3). The student types
 * Matric, FSc and the test scores they have once; am.js runs every university's own formula
 * (AM.aggregate, AM.profileValues) and links each result to that university's chances.
 */

defined( 'ABSPATH' ) || exit;

/** Profile inputs: key => [label, usual total, hint]. Tests are optional. */
const AM_PROFILE_FIELDS = array(
	'matric' => array( 'Matric / SSC marks', 1100, '' ),
	'fsc'    => array( 'FSc / HSSC marks (Part I + II)', 1100, 'Leave empty if you only have Part-I.' ),
	'fsc1'   => array( 'FSc Part-I marks', 550, 'UET, NUST, NED, UAF and UET Taxila/Peshawar use Part-I.' ),
	'mdcat'  => array( 'MDCAT marks', 180, 'For MBBS / BDS.' ),
	'ecat'   => array( 'ECAT marks', 400, 'For UET Lahore and UET Taxila.' ),
	'net'    => array( 'NUST NET score (%)', 100, '' ),
	'nat'    => array( 'NTS NAT score (%)', 100, 'COMSATS; FAST accepts it too.' ),
	'fast'   => array( 'FAST admission test (%)', 100, 'Empty: your NAT score is used.' ),
	'giki'   => array( 'GIKI admission test (%)', 100, '' ),
	'ned'    => array( 'NED entry test marks', 100, '' ),
);

/**
 * Which profile field feeds a formula part, or '' when only the university's own calculator
 * can take it (its own test). Weight-0 parts get "zero".
 */
function am_profile_source( string $slug, array $p ): string {
	$key = (string) $p['key'];
	if ( 0.0 === (float) $p['weight'] ) {
		return 'zero';
	}
	if ( 'matric' === $key ) {
		return 'matric';
	}
	if ( 'fsc' === $key || 'inter' === $key ) {
		return (float) $p['total'] <= 550 ? 'fsc1' : 'fsc';
	}
	if ( 'mdcat' === $key ) {
		return 'mdcat';
	}
	if ( 'test' === $key ) {
		$map = array( 'uet' => 'ecat', 'uet-taxila' => 'ecat', 'nust' => 'net', 'comsats' => 'nat', 'fast' => 'fast|nat', 'giki' => 'giki', 'ned' => 'ned' );
		return $map[ $slug ] ?? '';
	}
	return '';
}

function am_sc_all_aggregates(): string {
	$list = array();
	foreach ( am_unis() as $uni ) {
		$slug = (string) ( $uni['slug'] ?? '' );
		if ( empty( $uni['formulas'] ) || empty( $uni['calculator_path'] ) ) {
			continue;
		}
		$pred = am_predict_rows( $uni ) && am_find_page( '/predictor/' );
		foreach ( (array) $uni['formulas'] as $f ) {
			$map  = array();
			$own  = '';
			$skip = false;
			foreach ( (array) $f['parts'] as $p ) {
				$src = am_profile_source( $slug, $p );
				if ( 'olevel' === $p['key'] ) {
					$skip = true;
				}
				'' === $src ? ( $own = (string) $p['label'] ) : ( $map[ (string) $p['key'] ] = $src );
			}
			if ( $skip ) {
				continue;
			}
			$list[] = array(
				'u'   => (string) ( $uni['short'] ?? $slug ),
				'url' => home_url( (string) $uni['calculator_path'] ),
				'pr'  => $pred,
				'f'   => am_formula_cfg( $uni, $f ),
				'map' => $map,
				'own' => $own,
			);
		}
	}
	usort( $list, fn( $a, $b ) => strcmp( $a['u'], $b['u'] ) );
	$fields = '';
	foreach ( AM_PROFILE_FIELDS as $k => $d ) {
		$fields .= '<div class="am-field am-part"><label for="am-pf-' . $k . '">' . esc_html( $d[0] ) . '</label><div class="am-pair">'
			. '<input id="am-pf-' . $k . '" name="o_' . $k . '" type="number" inputmode="decimal" min="0" step="any"' . ( 'matric' === $k ? ' required' : '' ) . '><span>out of</span>'
			. '<input aria-label="' . esc_attr( $d[0] ) . ' total" name="t_' . $k . '" type="number" inputmode="decimal" min="1" step="any" value="' . esc_attr( (string) $d[1] ) . '"></div>'
			. ( '' !== $d[2] ? '<small>' . esc_html( $d[2] ) . '</small>' : '' ) . '</div>';
	}
	return '<form class="am-calc am-profile" data-am-profile novalidate>'
		. '<fieldset><legend>Your marks (fill what you have; tests are optional)</legend>' . $fields . '</fieldset>'
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Calculate for all universities</button> <button type="reset" class="am-btn">Clear</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<noscript><p>This needs JavaScript; each university calculator below also shows its formula table.</p></noscript>'
		. am_json_script( 'am-pf-data', array( 'list' => $list, 'pred' => home_url( '/predictor/' ) ) )
		. '</form>';
}

add_action( 'init', fn() => add_shortcode( 'am_all_aggregates', 'am_sc_all_aggregates' ) );
