<?php
/**
 * Homepage layout ("/" entry with "template": "home"): a tool-first page instead of a link list.
 *
 *   hero          H1, lead, live counts, the MDCAT calculator with university pills
 *   chances       "What can I get with my aggregate?" → /predictor/?agg=&uni=
 *   top merits    highest official closing merit per university (newest year)
 *   universities  one card per university: formula + calculator / merit-list buttons
 *   steps, guides
 *
 * Every number is computed from data/*.json (no typed figures), so it stays true as data grows.
 */

defined( 'ABSPATH' ) || exit;

/** Two-letter badge text for a university card. */
function am_home_badge( array $uni ): string {
	if ( 'uhs' === ( $uni['slug'] ?? '' ) ) {
		return 'MD';
	}
	$short = preg_replace( '/[^A-Za-z ]/', '', (string) ( $uni['short'] ?? '' ) );
	$words = array_values( array_filter( explode( ' ', $short ) ) );
	return strtoupper( count( $words ) > 1 ? $words[0][0] . $words[1][0] : substr( $words[0] ?? '?', 0, 2 ) );
}

/** Live counts for the hero: calculators, programs in the predictor, closing-merit records. */
function am_home_counts(): array {
	$calc = 0;
	$rec  = 0;
	$prog = 0;
	foreach ( am_unis() as $uni ) {
		$calc += ! empty( $uni['calculator_path'] ) && ! empty( $uni['formulas'] ) ? 1 : 0;
		$rec  += count( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) ) );
		$prog += count( am_predict_rows( $uni ) );
	}
	return array(
		'calc' => $calc,
		'prog' => $prog,
		'rec'  => $rec,
	);
}

/** Hero: H1, lead, counts, calculator card. */
function am_home_hero( array $page ): string {
	$h1     = am_tokens( (string) ( $page['h1'] ?? $page['title'] ?? '' ) );
	$n      = am_home_counts();
	$pills  = '';
	$order  = array( 'uhs', 'pu', 'nust', 'comsats', 'fast', 'uet-taxila', 'giki', 'ned' );
	$unis   = am_unis();
	foreach ( $order as $slug ) {
		if ( empty( $unis[ $slug ]['calculator_path'] ) ) {
			continue;
		}
		$label  = 'uhs' === $slug ? 'MDCAT' : (string) $unis[ $slug ]['short'];
		$pills .= 'uhs' === $slug
			? '<span class="am-pill am-pill--on" aria-current="true">' . esc_html( $label ) . '</span>'
			: '<a class="am-pill" href="' . esc_url( home_url( (string) $unis[ $slug ]['calculator_path'] ) ) . '">' . esc_html( $label ) . '</a>';
	}
	$pills .= '<a class="am-pill" href="' . esc_url( home_url( '/aggregate-calculator/' ) ) . '">All ' . (int) $n['calc'] . ' →</a>';

	$calc = function_exists( 'am_sc_calculator' ) ? am_sc_calculator( array( 'uni' => 'uhs', 'compact' => '1' ) ) : '';

	return '<section class="am-hero">'
		. '<div class="am-hero-text">'
		. '<p class="am-eyebrow">Admission season ' . esc_html( (string) am_site( 'season_year' ) ) . '</p>'
		. '<h1>' . esc_html( $h1 ) . '</h1>'
		. '<p class="am-hero-lead">Get your aggregate in seconds and, for universities that publish closing merits, see where you could get in.</p>'
		. '</div>'
		. '<div class="am-hero-tool"><p class="am-tool-title">Calculate your aggregate</p><nav class="am-pills" aria-label="Choose a university calculator">' . $pills . '</nav>' . $calc . '</div>'
		. '<div class="am-hero-meta"><ul class="am-stats">'
		. '<li><strong>' . (int) $n['calc'] . '</strong><span>calculators</span></li>'
		. '<li><strong>' . (int) $n['prog'] . '</strong><span>programs to compare</span></li>'
		. '<li><strong>' . (int) $n['rec'] . '</strong><span>official closing merits</span></li>'
		. '</ul>'
		. '<p class="am-hero-trust">Free · no sign-up · every formula and closing merit links to the official university notice</p>'
		. '</div></section>';
}

/** "What can I get with my aggregate?" box for all universities. */
function am_home_chances(): string {
	if ( ! am_find_page( '/predictor/' ) ) {
		return '';
	}
	$opts = '<option value="">All universities</option>';
	foreach ( am_unis() as $uni ) {
		if ( am_predict_rows( $uni ) ) {
			$opts .= '<option>' . esc_html( (string) $uni['short'] ) . '</option>';
		}
	}
	return '<section class="am-band am-chances"><div><h2>Already know your aggregate?</h2><p>See every program it would have reached last year, marked Strong, Borderline or Unlikely.</p></div>'
		. '<form action="' . esc_url( home_url( '/predictor/' ) ) . '" method="get" class="am-chances-form">'
		. '<div class="am-field"><label for="am-home-agg">Your aggregate (%)</label><input id="am-home-agg" name="agg" type="number" inputmode="decimal" min="1" max="100" step="any" required placeholder="e.g. 88.5"></div>'
		. '<div class="am-field"><label for="am-home-uni">University</label><select id="am-home-uni" name="uni">' . $opts . '</select></div>'
		. '<button type="submit" class="am-btn am-btn--primary">Check my chances</button></form></section>';
}

/** Highest official closing merit per university, newest year. */
function am_home_top(): string {
	$cards = '';
	foreach ( am_unis() as $uni ) {
		$year = am_uni_year( $uni );
		if ( ! $year || empty( $uni['meritlist_path'] ) ) {
			continue;
		}
		$cur = array_values( array_filter( am_default_rows( $uni ), fn( $r ) => (int) $r['year'] === $year ) );
		usort( $cur, fn( $a, $b ) => (float) $b['closing'] <=> (float) $a['closing'] );
		$top    = $cur[0];
		$cards .= '<a class="am-stat-card" href="' . esc_url( home_url( (string) $uni['meritlist_path'] ) . '#' . 'p-' . am_slug( $top['program'] . ' ' . ( $top['campus'] ?? '' ) . ' ' . ( $top['shift'] ?? '' ) ) ) . '">'
			. '<span class="am-stat-uni">' . esc_html( (string) $uni['short'] ) . ' · ' . esc_html( (string) $year ) . '</span>'
			. '<strong>' . esc_html( am_pct( $top['closing'] ) ) . '</strong>'
			. '<span>' . esc_html( (string) $top['program'] ) . '</span>'
			. '<small>' . esc_html( (string) ( $top['campus'] ?? '' ) ) . '</small>'
			. '<span class="am-stat-more">' . count( $cur ) . ' programs →</span></a>';
	}
	return $cards ? '<section><h2>Highest closing merits, latest official lists</h2><div class="am-grid am-grid--4">' . $cards . '</div><p class="am-muted">Highest open-merit closing aggregate in each university\'s newest official list. Tap a card for the full table. <a href="' . esc_url( home_url( '/merit-list/' ) ) . '">All merit lists</a>.</p></section>' : '';
}

/** One card per university with a calculator. */
function am_home_unis(): string {
	$unis = am_unis();
	uasort( $unis, fn( $a, $b ) => array( array_search( $a['field'] ?? '', array_keys( AM_FIELDS ), true ), $a['short'] ?? '' ) <=> array( array_search( $b['field'] ?? '', array_keys( AM_FIELDS ), true ), $b['short'] ?? '' ) );
	$cards = '';
	foreach ( $unis as $uni ) {
		if ( empty( $uni['calculator_path'] ) || empty( $uni['formulas'] ) ) {
			continue;
		}
		$field  = (string) ( $uni['field'] ?? 'general' );
		$merit  = ! empty( $uni['meritlist_path'] ) && am_uni_year( $uni ) ? '<a class="am-card-link" href="' . esc_url( home_url( (string) $uni['meritlist_path'] ) ) . '">Merit list</a>' : '';
		$cards .= '<div class="am-card">'
			. '<div class="am-card-head"><span class="am-badge am-badge--' . esc_attr( $field ) . '" aria-hidden="true">' . esc_html( am_home_badge( $uni ) ) . '</span>'
			. '<div><h3>' . esc_html( 'uhs' === ( $uni['slug'] ?? '' ) ? 'MDCAT (UHS Punjab)' : (string) $uni['short'] ) . '</h3><small>' . esc_html( (string) ( AM_FIELDS[ $field ] ?? '' ) ) . '</small></div></div>'
			. '<p>' . esc_html( am_formula_summary( (array) $uni['formulas'][0] ) ) . '</p>'
			. '<div class="am-card-actions"><a class="am-btn am-btn--primary" href="' . esc_url( home_url( (string) $uni['calculator_path'] ) ) . '">Calculator</a>' . $merit . '</div>'
			. '</div>';
	}
	return '<section><h2>Pick your university</h2><div class="am-grid am-grid--3">' . $cards . '</div></section>';
}

/** How it works + guides. */
function am_home_steps(): string {
	$steps = array(
		array( 'Enter your marks', 'Matric, FSc (or Part-I) and your entry test. Not sure of a percentage? Use the <a href="' . esc_url( home_url( '/percentage-calculator/' ) ) . '">percentage calculator</a>.' ),
		array( 'Get your aggregate', 'Worked out with the university\'s own published weights, with the formula and its source shown.' ),
		array( 'Compare with closing merit', 'See where last year\'s official lists say you would have got in, then open the <a href="' . esc_url( home_url( '/predictor/' ) ) . '">merit predictor</a>.' ),
	);
	$li = '';
	foreach ( $steps as $i => $s ) {
		$li .= '<li><span class="am-step-n" aria-hidden="true">' . ( $i + 1 ) . '</span><div><h3>' . esc_html( $s[0] ) . '</h3><p>' . $s[1] . '</p></div></li>';
	}
	$guides = '';
	foreach ( array( '/guides/mdcat-aggregate-formula/', '/guides/what-is-closing-merit/', '/guides/1st-2nd-final-merit-list/', '/merit-data-report-2026/' ) as $p ) {
		$g = am_find_page( $p );
		if ( $g ) {
			$guides .= '<a class="am-guide" href="' . esc_url( home_url( $p ) ) . '"><strong>' . esc_html( am_tokens( (string) ( $g['h1'] ?? $g['title'] ) ) ) . '</strong><span>' . esc_html( am_tokens( (string) ( $g['subtitle'] ?? $g['description'] ?? '' ) ) ) . '</span></a>';
		}
	}
	return '<section><h2>How it works</h2><ol class="am-steps">' . $li . '</ol></section>'
		. ( $guides ? '<section><h2>Guides</h2><div class="am-grid am-grid--2">' . $guides . '</div></section>' : '' );
}

/** Whole homepage main column. */
function am_home_html( array $page ): string {
	return am_home_hero( $page )
		. am_home_chances()
		. am_home_top()
		. am_home_unis()
		. am_home_steps();
}
