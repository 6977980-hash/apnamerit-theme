<?php
/**
 * Shortcodes. All numbers come from data/universities/<slug>.json (every record has source_url).
 *
 *   [am_calculator uni="uet"]   calculator + "where can I get in" result for one university
 *   [am_merit_table uni="uet"]  closing-merit table with filters and sort
 *   [am_predictor]              cross-university predictor
 *   [am_faq uni="uet"]          FAQ block (+ FAQPage schema)
 *   [am_answer uni="uet" type="calculator"]  40-60 word answer block
 *   [am_sources_table]          official sources per university (data/sources.json)
 *   [am_contact]                contact details from data/site.json
 *
 * University file shape (data/universities/<slug>.json):
 * {
 *   "slug": "uet", "name": "University of Engineering and Technology, Lahore", "short": "UET Lahore",
 *   "field": "engineering", "province": "Punjab", "website": "https://uet.edu.pk/",
 *   "calculator_path": "/uet-aggregate-calculator/", "meritlist_path": "/uet-merit-list/",
 *   "defaults": { "list": "Final", "category": "Open merit" },
 *   "formulas": [ { "id": "ecat", "label": "BSc Engineering (ECAT)", "source_url": "https://…",
 *                   "parts": [ { "key": "matric", "label": "Matric", "total": 1100, "weight": 17 }, … ],
 *                   "bonus": [ { "key": "hafiz", "label": "Hafiz-e-Quran", "part": "fsc", "marks": 20 } ] } ],
 *   "answers": { "calculator": "…", "meritlist": "…" },
 *   "faq": [ { "q": "…", "a": "…", "lang": "ur-Latn" } ],
 *   "merit": [ { "year": 2025, "list": "Final", "category": "Open merit", "campus": "Lahore", "shift": "Morning",
 *                "program": "BSc Computer Science", "closing": 81.83, "source_url": "https://…", "field": "computing" } ]
 * }
 * Weights are percentages and must add up to 100 (scripts/check.py checks it).
 */

defined( 'ABSPATH' ) || exit;

/** Borderline band (± percentage points) around the reference closing merit. */
const AM_BAND = 1.5;

/**
 * Normalise a university slug attribute.
 */
function am_uni_attr( array $atts ): string {
	return sanitize_key( $atts['uni'] ?? '' );
}

/**
 * University data, or an empty array.
 */
function am_uni( string $slug ): array {
	return '' === $slug ? array() : am_data( 'universities/' . $slug );
}

/**
 * Every university file in data/universities/.
 *
 * @return array<string, array> slug => data
 */
/**
 * A university's provinces as a list: "province" may be a string or, for universities with
 * campuses or colleges in several provinces (NUMS, COMSATS), an array (audit FE: NUMS was "Punjab").
 */
function am_uni_provinces( array $uni ): array {
	return array_values( array_filter( array_map( 'strval', (array) ( $uni['province'] ?? array() ) ) ) );
}

function am_unis(): array {
	static $out = null; // one glob per request (audit TS-15)
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( (array) glob( AM_DIR . '/data/universities/*.json' ) as $file ) {
		$slug = basename( (string) $file, '.json' );
		if ( '_' === $slug[0] ) {
			continue;
		}
		$data = am_uni( $slug );
		if ( $data ) {
			$out[ $slug ] = $data;
		}
	}
	return $out;
}

/**
 * Placeholder output used when data is missing. Visible only to editors.
 */
function am_stub( string $tag, array $atts, string $why = 'no data yet' ): string {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return sprintf(
		'<div class="am-stub" data-shortcode="%s">[%s %s] — %s</div>',
		esc_attr( $tag ),
		esc_html( $tag ),
		esc_html( http_build_query( $atts, '', ' ' ) ),
		esc_html( $why )
	);
}

/**
 * JSON for <script type="application/json"> (safe inside HTML).
 */
function am_json_script( string $class, array $data ): string {
	return '<script type="application/json" class="' . esc_attr( $class ) . '">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}

/**
 * Format a percentage with 2 decimals.
 */
function am_pct( $value ): string {
	return number_format( (float) $value, 2 ) . '%';
}

/**
 * "4th list", "Last list", "Final list": appends " list" only when the name does not already end with it.
 */
/** Sort key of a list name: "1st" 1, "2nd merit list" 2, …, "Final" last; others (dated portal lists) in between. */
function am_list_rank( string $list ): int {
	if ( preg_match( '/^\s*(\d+)(st|nd|rd|th)\b/i', $list, $m ) ) {
		return (int) $m[1];
	}
	return preg_match( '/final/i', $list ) ? 1000 : 500;
}

function am_list_label( string $list ): string {
	return preg_match( '/\blist$/i', $list ) ? $list : $list . ' list';
}

/**
 * Category for running text: "Open merit" → "open merit", but codes such as "A1 open merit"
 * or "K (Karachi)" keep their case (audit F-15: "a1 open merit").
 */
function am_cat_label( string $cat ): string {
	$first = (string) strtok( $cat, ' ' );
	return preg_match( '/^[A-Z][a-z]+$/', $first ) ? lcfirst( $cat ) : $cat;
}

/**
 * Source link HTML for a record's document: the official URL, or, when the record carries an
 * archive_url (origin down, data read from a Wayback copy), the archived copy first and the
 * origin second (audit F-12).
 */
function am_source_link( string $url, string $archive = '' ): string {
	$a = fn( $href, $text ) => '<a href="' . esc_url( $href ) . '" rel="noopener" target="_blank">' . esc_html( $text ) . '</a>';
	if ( '' === $archive ) {
		return $a( $url, am_source_label( $url ) );
	}
	return esc_html( am_source_label( $url ) ) . ': ' . $a( $archive, 'official (archived copy)' ) . ' · ' . $a( $url, 'original address' );
}

/**
 * Readable name of an official document URL: its label in data/sources.json, else host + file name.
 */
function am_source_label( string $url ): string {
	static $labels = null;
	if ( null === $labels ) {
		$labels = array();
		foreach ( (array) ( am_data( 'sources' )['universities'] ?? array() ) as $u ) {
			foreach ( (array) ( $u['documents'] ?? array() ) as $d ) {
				if ( ! empty( $d['url'] ) && ! empty( $d['label'] ) ) {
					$labels[ (string) $d['url'] ] = (string) $d['label'];
				}
			}
		}
	}
	if ( isset( $labels[ $url ] ) ) {
		return $labels[ $url ];
	}
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$file = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	return preg_replace( '/^www\./', '', $host ) . ( $file ? ': ' . rawurldecode( $file ) : '' );
}

/**
 * Reference closing merit per program for the predictor (master plan 4.3; method on /methodology/):
 * default list + category, the newest year of each program (am_uni_year, or the year before
 * when a program has no row that year). Reference = that one record's closing, labelled with its own year.
 *
 * @return array<int, array> rows: program, campus, shift, ref, year, field, anchor
 */
function am_predict_rows( array $uni ): array {
	$dy   = am_uni_year( $uni );
	$list = (string) ( $uni['defaults']['list'] ?? 'Final' );
	$cat  = (string) ( $uni['defaults']['category'] ?? 'Open merit' );
	$rows = array();
	foreach ( (array) ( $uni['merit'] ?? array() ) as $r ) {
		$year = (int) ( $r['year'] ?? 0 );
		if ( false === ( $r['predict'] ?? true ) || $year > $dy || $year < $dy - 1 || ( $r['list'] ?? '' ) !== $list || ( $r['category'] ?? '' ) !== $cat || ! is_numeric( $r['closing'] ?? null ) ) {
			continue;
		}
		$key = ( $r['program'] ?? '' ) . '|' . ( $r['campus'] ?? '' ) . '|' . ( $r['shift'] ?? '' );
		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array(
				'program' => (string) ( $r['program'] ?? '' ),
				'campus'  => (string) ( $r['campus'] ?? '' ),
				'shift'   => (string) ( $r['shift'] ?? '' ),
				'ref'     => (float) $r['closing'],
				'year'    => $year,
				'field'   => (string) ( $r['field'] ?? $uni['field'] ?? '' ),
				'anchor'  => 'p-' . am_slug( (string) ( $r['program'] ?? '' ) . ' ' . ( $r['campus'] ?? '' ) . ' ' . ( $r['shift'] ?? '' ) ),
			);
			continue;
		}
		// Newest year wins; value and printed year always come from the same record (Fix 1, 2026-11-01).
		if ( $year > $rows[ $key ]['year'] ) {
			$rows[ $key ]['ref']  = (float) $r['closing'];
			$rows[ $key ]['year'] = $year;
		}
	}
	return array_values( $rows );
}

/**
 * Line under a calculator result when the university has no official closing merit in our data
 * (Fix 3A, FIX-BRIEF 2026-11-01): names the nearest merit list (same field, else PU) and the hub.
 */
function am_no_merit_html( array $uni ): string {
	$short = (string) ( $uni['short'] ?? $uni['name'] ?? '' );
	$near  = null;
	$best  = -1;
	foreach ( am_unis() as $slug => $u ) {
		if ( empty( $u['meritlist_path'] ) || ! am_predict_rows( $u ) || ! am_find_page( (string) $u['meritlist_path'] ) ) {
			continue;
		}
		// Same field counts most, then same province; PU breaks ties (largest general list).
		$score = 4 * (int) ( ( $u['field'] ?? '' ) === ( $uni['field'] ?? '-' ) ) + 2 * (int) (bool) array_intersect( am_uni_provinces( $u ), am_uni_provinces( $uni ) ) + (int) ( 'pu' === $slug );
		if ( $score > $best ) {
			$best = $score;
			$near = $u;
		}
	}
	$html  = 'We do not have an official closing merit (aggregate) for ' . esc_html( $short ) . ' yet, so there is no "Where can you get in?" list here. ';
	$guide = am_find_page( (string) ( $uni['merit_guide'] ?? '' ) );
	if ( $guide ) {
		// NUST/FAST: their own guide beats another university's list (audit FE: NUST linked UET Taxila).
		$html .= 'Read <a href="' . esc_url( home_url( (string) $guide['path'] ) ) . '">' . esc_html( am_tokens( (string) ( $guide['h1'] ?? $guide['title'] ?? '' ) ) ) . '</a> or see <a href="' . esc_url( home_url( '/merit-list/' ) ) . '">all merit lists</a>.';
	} elseif ( $near ) {
		$html .= 'Compare with the <a href="' . esc_url( home_url( (string) $near['meritlist_path'] ) ) . '">' . esc_html( (string) $near['short'] ) . ' merit list</a> or see <a href="' . esc_url( home_url( '/merit-list/' ) ) . '">all merit lists</a>.';
	} else {
		$html .= 'See <a href="' . esc_url( home_url( '/merit-list/' ) ) . '">all merit lists</a>.';
	}
	return '<p class="am-muted am-nodata">' . $html . '</p>';
}

/**
 * Eligibility floors that apply to one formula (audit F-01): entries of the university's
 * `eligibility` array whose part is in the formula and whose optional `formulas` list names it.
 *
 * @return array<int, array> part, min_pct, label, applies_to, source_url, programs
 */
function am_formula_floors( array $uni, array $f ): array {
	$keys = array_map( fn( $p ) => (string) ( $p['key'] ?? '' ), (array) ( $f['parts'] ?? array() ) );
	$out  = array();
	foreach ( (array) ( $uni['eligibility'] ?? array() ) as $e ) {
		if ( ! in_array( (string) ( $e['part'] ?? '' ), $keys, true ) ) {
			continue;
		}
		if ( ! empty( $e['formulas'] ) && ! in_array( (string) ( $f['id'] ?? '' ), (array) $e['formulas'], true ) ) {
			continue;
		}
		$out[] = array(
			'part'       => (string) $e['part'],
			'min_pct'    => (float) $e['min_pct'],
			'label'      => (string) ( $e['label'] ?? '' ),
			'applies_to' => (string) ( $e['applies_to'] ?? '' ),
			'source_url' => (string) ( $e['source_url'] ?? '' ),
			'programs'   => (string) ( $e['programs'] ?? '' ),
		);
	}
	return $out;
}

/** Whether a floor covers a program (no `programs` pattern = every program). */
function am_floor_covers( array $floor, string $program ): bool {
	return '' === $floor['programs'] || 1 === preg_match( '~' . str_replace( '~', '\~', $floor['programs'] ) . '~', $program );
}

/**
 * Lowest aggregate a student can have and still meet every floor for a program (predictor):
 * sum of weight x floor over the floored parts, other parts at zero; the smallest value over the
 * formulas that have a floor for that program. Returns [min, readable floors] or [0, ''].
 */
function am_floor_min( array $uni, string $program ): array {
	$best = null;
	$note = '';
	foreach ( (array) ( $uni['formulas'] ?? array() ) as $f ) {
		$floors = array_filter( am_formula_floors( $uni, $f ), fn( $fl ) => am_floor_covers( $fl, $program ) );
		if ( ! $floors ) {
			continue;
		}
		$need = array();
		foreach ( $floors as $fl ) {
			$need[ $fl['part'] ] = max( $need[ $fl['part'] ] ?? 0, $fl['min_pct'] );
		}
		$min   = 0.0;
		$words = array();
		foreach ( (array) $f['parts'] as $p ) {
			if ( isset( $need[ $p['key'] ] ) ) {
				$min    += (float) $p['weight'] * $need[ $p['key'] ] / 100;
				$words[] = $p['label'] . ' ' . am_num( $need[ $p['key'] ] ) . '%';
			}
		}
		if ( null === $best || $min < $best ) {
			$best = $min;
			$note = implode( ', ', $words );
		}
	}
	return array( round( (float) $best, 2 ), $note );
}

/** 55.0 → "55", 57.5 → "57.5". */
function am_num( $n ): string {
	return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
}

/**
 * "MDCAT pass mark for MBBS: 55%; ..." for a list of floors (their official labels), with source links.
 */
function am_floors_text( array $floors ): string {
	$items = array();
	$srcs  = array();
	foreach ( $floors as $fl ) {
		$items[] = esc_html( ucfirst( (string) $fl['label'] ) . ': ' . am_num( $fl['min_pct'] ) . '%' );
		if ( $fl['source_url'] ) {
			$srcs[ $fl['source_url'] ] = true;
		}
	}
	$links = array_map( fn( $u ) => am_source_link( (string) $u ), array_keys( $srcs ) );
	return implode( '; ', $items ) . ( $links ? ' (source: ' . implode( ', ', $links ) . ')' : '' );
}

// ------------------------------------------------------------------ [am_eligibility]
/**
 * One "Eligibility:" sentence for a calculator page, from the same data the calculator checks.
 */
function am_sc_eligibility( $atts ): string {
	$atts  = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_eligibility' );
	$uni   = am_uni( am_uni_attr( $atts ) );
	$short = (string) ( $uni['short'] ?? $uni['name'] ?? '' );
	if ( empty( $uni['formulas'] ) ) {
		return am_stub( 'am_eligibility', $atts );
	}
	$floors = array();
	foreach ( (array) ( $uni['eligibility'] ?? array() ) as $e ) {
		$floors[] = array(
			'min_pct'    => (float) $e['min_pct'],
			'label'      => (string) ( $e['label'] ?? '' ),
			'source_url' => (string) ( $e['source_url'] ?? '' ),
		);
	}
	if ( ! $floors ) {
		$src = (string) ( $uni['formulas'][0]['source_url'] ?? '' );
		return '<p><strong>Eligibility:</strong> ' . esc_html( $short ) . ' sets its own minimum marks for each program' . ( $src ? ' (see the ' . am_source_link( $src ) . ')' : '' ) . '. This calculator does not check them yet, so confirm you meet them before you apply.</p>';
	}
	return '<p><strong>Eligibility:</strong> ' . am_floors_text( $floors ) . '. Below a minimum, the calculator still shows your aggregate but lists no programs that need it.</p>';
}

// ------------------------------------------------------------------ [am_calculator]
function am_sc_calculator( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '', 'compact' => '' ), (array) $atts, 'am_calculator' );
	$uni  = am_uni( am_uni_attr( $atts ) );
	if ( empty( $uni['formulas'] ) ) {
		return am_stub( 'am_calculator', $atts );
	}
	$short = (string) ( $uni['short'] ?? $uni['name'] ?? '' );
	$id    = am_slug( (string) ( $uni['slug'] ?? $atts['uni'] ) );
	am_registry(
		'webapp',
		array(
			'id'          => $id,
			'name'        => $short . ' Aggregate Calculator',
			'description' => 'Calculates the ' . $short . ' admission aggregate from the official formula and compares it with last year\'s closing merits.',
		)
	);

	$formulas = array();
	$html     = '';
	$multi    = count( $uni['formulas'] ) > 1;
	if ( $multi ) {
		$html .= '<div class="am-field"><label for="am-f-' . esc_attr( $id ) . '">Programme / formula</label><select id="am-f-' . esc_attr( $id ) . '" data-am-formula>';
		foreach ( $uni['formulas'] as $f ) {
			$html .= '<option value="' . esc_attr( (string) $f['id'] ) . '">' . esc_html( (string) $f['label'] ) . '</option>';
		}
		$html .= '</select></div>';
	}
	foreach ( $uni['formulas'] as $n => $f ) {
		$fid        = (string) $f['id'];
		$formulas[] = array(
			'id'    => $fid,
			'label' => (string) ( $f['label'] ?? '' ),
			'parts' => array_map(
				fn( $p ) => array(
					'key'    => (string) $p['key'],
					'label'  => (string) $p['label'],
					'total'  => (float) $p['total'],
					'weight' => (float) $p['weight'],
					'pool'   => (string) ( $p['pool'] ?? '' ),
					'scale'  => (float) ( $p['scale'] ?? 1 ),
				),
				(array) $f['parts']
			),
			'bonus' => (array) ( $f['bonus'] ?? array() ),
			'elig'  => am_formula_floors( $uni, $f ),
		);
		$html .= '<fieldset class="am-formula" data-am-fid="' . esc_attr( $fid ) . '"' . ( $n > 0 ? ' hidden' : '' ) . '><legend>' . esc_html( (string) $f['label'] ) . '</legend>';
		foreach ( (array) $f['parts'] as $p ) {
			$k     = esc_attr( $fid . '-' . $p['key'] );
			$html .= '<div class="am-field am-part"><label for="am-o-' . $k . '">' . esc_html( am_marks_label( (string) $p['label'] ) ) . '</label><div class="am-pair">'
				. '<input id="am-o-' . $k . '" name="o_' . esc_attr( (string) $p['key'] ) . '" type="number" inputmode="decimal" min="0" step="any" required>'
				. '<span>out of</span>'
				. '<input aria-label="' . esc_attr( (string) $p['label'] ) . ' total marks" name="t_' . esc_attr( (string) $p['key'] ) . '" type="number" inputmode="decimal" min="1" step="any" value="' . esc_attr( (string) $p['total'] ) . '" required>'
				. '</div><small>' . esc_html( ! empty( $p['hint'] ) ? (string) $p['hint'] : 'Weight ' . $p['weight'] . '%' ) . '</small></div>';
		}
		foreach ( (array) ( $f['bonus'] ?? array() ) as $b ) {
			$html .= '<label class="am-check"><input type="checkbox" name="b_' . esc_attr( (string) $b['key'] ) . '"> ' . esc_html( (string) $b['label'] ) . '</label>';
		}
		if ( ! empty( $f['source_url'] ) ) {
			$html .= '<p class="am-muted">Formula source: <a href="' . esc_url( (string) $f['source_url'] ) . '" rel="noopener" target="_blank">official notice</a></p>';
		}
		$html .= '</fieldset>';
	}

	$prows  = am_predict_rows( $uni );
	$config = array(
		'uni'      => $short,
		'formulas' => $formulas,
		'rows'     => $prows,
		'noRows'   => $prows ? '' : am_no_merit_html( $uni ),
		'band'     => AM_BAND,
		'meritUrl' => ! empty( $uni['meritlist_path'] ) ? home_url( (string) $uni['meritlist_path'] ) : '',
		'url'      => (string) apply_filters( 'am_calc_url', home_url( am_request_path() ), $uni ),
		'share'    => (string) am_site( 'share.whatsapp' ),
		'predUrl'  => am_find_page( '/predictor/' ) ? home_url( '/predictor/' ) : '',
	);

	return '<form class="am-calc" data-am-calc="' . esc_attr( $id ) . '" novalidate>'
		. $html
		. ( '' === $atts['compact'] ? am_calc_help() : '' )
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Calculate aggregate</button> <button type="reset" class="am-btn">Clear</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<noscript><p>The calculator needs JavaScript. You can still use the formula table below by hand.</p></noscript>'
		. am_json_script( 'am-calc-data', $config )
		. '</form>'
		. ( '' === $atts['compact'] ? am_formula_table( $uni ) : '' );
}

/**
 * Formula weights table (one per formula).
 */
function am_formula_table( array $uni ): string {
	$out   = '';
	$short = (string) ( $uni['short'] ?? '' );
	foreach ( (array) ( $uni['formulas'] ?? array() ) as $f ) {
		$rows = '';
		foreach ( (array) $f['parts'] as $p ) {
			$rows .= '<tr><td>' . esc_html( (string) $p['label'] ) . '</td><td>' . esc_html( ! empty( $p['hint'] ) ? (string) $p['hint'] : $p['weight'] . '%' ) . '</td><td>' . esc_html( (string) $p['total'] ) . '</td></tr>';
		}
		$out .= '<div class="am-table-wrap"><table class="am-table"><caption>' . esc_html( $short . ' aggregate formula: ' . $f['label'] ) . '</caption><thead><tr><th scope="col">Part</th><th scope="col">Weight</th><th scope="col">Usual total marks</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
		$floors = am_formula_floors( $uni, $f );
		if ( $floors ) {
			$out .= '<p class="am-muted">Minimum to be eligible. ' . am_floors_text( $floors ) . '.</p>';
		}
	}
	return $out;
}

/**
 * Field label for a marks input: "Matric" → "Matric marks"; "ECAT marks" and "NET score in %" stay (audit F-24).
 */
function am_marks_label( string $label ): string {
	return preg_match( '/marks\s*$|score|%/i', $label ) ? $label : $label . ' marks';
}

/**
 * Help line under every full calculator: O/A level → IBCC, Matric/FSc → percentage (audit F-18).
 */
function am_calc_help(): string {
	$parts = array();
	if ( am_find_page( '/ibcc-equivalence-calculator/' ) ) {
		$parts[] = 'O-level or A-level student? Convert your grades first with the <a href="' . esc_url( home_url( '/ibcc-equivalence-calculator/' ) ) . '">IBCC equivalence calculator</a>.';
	}
	if ( am_find_page( '/percentage-calculator/' ) ) {
		$parts[] = 'Need your Matric or FSc percentage? Use the <a href="' . esc_url( home_url( '/percentage-calculator/' ) ) . '">percentage calculator</a>.';
	}
	return $parts ? '<p class="am-muted">' . implode( ' ', $parts ) . '</p>' : '';
}

// ------------------------------------------------------------------ [am_merit_table]
function am_sc_merit_table( $atts ): string {
	// Optional list="…" shows only that merit list (several tables per page); category="…" overrides the default filter;
	// campus="…" keeps one campus (campus pages, audit G-11).
	// program="…" keeps rows whose program contains that text (e.g. "MBBS (private)", "BDS").
	$atts = shortcode_atts( array( 'uni' => '', 'list' => '', 'category' => '', 'campus' => '', 'program' => '' ), (array) $atts, 'am_merit_table' );
	$uni  = am_uni( am_uni_attr( $atts ) );
	$only = (string) $atts['list'];
	$camp = (string) $atts['campus'];
	$prog = (string) $atts['program'];
	if ( '' !== $prog ) {
		$uni['merit'] = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => str_contains( (string) ( $r['program'] ?? '' ), $prog ) ) );
		$uni['short'] = (string) ( $uni['short'] ?? '' ) . ' ' . $prog;
	}
	if ( '' !== $camp ) {
		// Key figures, trend and caption then describe this campus only.
		$uni['merit'] = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => ( $r['campus'] ?? '' ) === $camp ) );
		$uni['short'] = (string) ( $uni['short'] ?? '' ) . ' ' . $camp;
	}
	$rows = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) && ( '' === $only || ( $r['list'] ?? '' ) === $only ) && ( '' === $camp || ( $r['campus'] ?? '' ) === $camp ) ) );
	if ( ! $rows ) {
		return am_stub( 'am_merit_table', $atts );
	}
	$short = (string) ( $uni['short'] ?? $uni['name'] ?? '' );
	$base  = am_slug( (string) ( $uni['slug'] ?? $atts['uni'] ) );
	$id    = ( '' === $only ? $base : $base . '-' . am_slug( $only ) ) . ( '' === $camp ? '' : '-' . am_slug( $camp ) ) . ( '' === $prog ? '' : '-' . am_slug( $prog ) );
	$def   = array(
		'year'     => (string) am_uni_year( $uni, '' !== $only ? $only : '', (string) $atts['category'] ),
		'list'     => '' !== $only ? $only : (string) ( $uni['defaults']['list'] ?? 'Final' ),
		'category' => '' !== (string) $atts['category'] ? (string) $atts['category'] : (string) ( $uni['defaults']['category'] ?? 'Open merit' ),
	);

	// Previous-year value for the Δ column.
	$index = array();
	foreach ( $rows as $r ) {
		$index[ implode( '|', array( $r['year'], $r['program'], $r['campus'] ?? '', $r['category'] ?? '', $r['list'] ?? '', $r['shift'] ?? '' ) ) ] = (float) $r['closing'];
	}
	usort( $rows, fn( $a, $b ) => array( $b['year'], $b['closing'] ) <=> array( $a['year'], $a['closing'] ) );

	$facets = array(
		'year'     => 'Year',
		'list'     => 'Merit list',
		'category' => 'Category',
		'campus'   => 'Campus',
		'shift'    => 'Shift',
	);
	$values = array();
	foreach ( $facets as $key => $label ) {
		$values[ $key ] = array_values( array_unique( array_filter( array_map( fn( $r ) => (string) ( $r[ $key ] ?? '' ), $rows ) ) ) );
		// Filter options in reading order (audit FE: "1st, Final, 3rd"; KU categories unsorted):
		// lists by number with Final last, years newest first, the rest A-Z with the default first.
		if ( 'list' === $key ) {
			usort( $values[ $key ], fn( $a, $b ) => am_list_rank( $a ) <=> am_list_rank( $b ) ?: strnatcasecmp( $a, $b ) );
		} elseif ( 'year' !== $key ) {
			usort( $values[ $key ], fn( $a, $b ) => (int) ( ( $def[ $key ] ?? null ) !== $a ) <=> (int) ( ( $def[ $key ] ?? null ) !== $b ) ?: strnatcasecmp( $a, $b ) );
		}
	}

	$filters = '';
	foreach ( $facets as $key => $label ) {
		if ( count( $values[ $key ] ) < 2 ) {
			continue;
		}
		$filters .= '<div class="am-field"><label for="am-mf-' . esc_attr( $id . '-' . $key ) . '">' . esc_html( $label ) . '</label><select id="am-mf-' . esc_attr( $id . '-' . $key ) . '" data-am-filter="' . esc_attr( $key ) . '"><option value="">All</option>';
		foreach ( $values[ $key ] as $v ) {
			$sel      = ( $def[ $key ] ?? null ) === $v ? ' selected' : '';
			$filters .= '<option' . $sel . '>' . esc_html( $v ) . '</option>';
		}
		$filters .= '</select></div>';
	}

	// Columns that would repeat one value on every row (PU: one year, one list, one category)
	// move into the caption; data-* filter attributes only for facets that have a filter.
	$multi = array_map( fn( $v ) => count( $v ) > 1, $values );
	$fixed = array();
	foreach ( array( 'category', 'list', 'year' ) as $key ) {
		if ( ! $multi[ $key ] && $values[ $key ] ) {
			$fixed[] = 'list' === $key ? am_list_label( $values[ $key ][0] ) : $values[ $key ][0];
		}
	}
	$show_campus = $multi['campus'] || $multi['shift'];
	if ( ! $show_campus && $values['campus'] ) {
		$fixed[] = $values['campus'][0] . ( $values['shift'] ? ' (' . $values['shift'][0] . ')' : '' );
	}
	$src_key   = fn( $r ) => (string) $r['source_url'] . '|' . (string) ( $r['archive_url'] ?? '' );
	$srcs      = array_values( array_unique( array_map( $src_key, $rows ) ) );
	$src_no    = array_flip( $srcs );
	$multi_src = count( $srcs ) > 1;

	// Change column only when enough rows of the default view have a previous-year value
	// (audit F-11: UHS 1st-list rows had no 2024 match, so the column was mostly "—").
	$show_delta = false;
	if ( $multi['year'] ) {
		$n_def  = 0;
		$n_prev = 0;
		foreach ( $rows as $r ) {
			$on = true;
			foreach ( $def as $key => $v ) {
				if ( $multi[ $key ] && (string) ( $r[ $key ] ?? '' ) !== $v ) {
					$on = false;
				}
			}
			if ( ! $on ) {
				continue;
			}
			++$n_def;
			$n_prev += (int) isset( $index[ implode( '|', array( $r['year'] - 1, $r['program'], $r['campus'] ?? '', $r['category'] ?? '', $r['list'] ?? '', $r['shift'] ?? '' ) ) ] );
		}
		$show_delta = $n_def > 0 && $n_prev / $n_def >= 0.2;
	}

	$many    = count( $rows ) > 150; // may need the <template> below: rows carry their order (data-i)
	$all_tr  = array(); // every row, in table order
	$def_tr  = '';      // rows of the default view
	$more_tr = '';      // the other rows
	$n_more  = 0;       // rows outside it
	$anchors = array();
	foreach ( $rows as $r ) {
		$prevkey = implode( '|', array( $r['year'] - 1, $r['program'], $r['campus'] ?? '', $r['category'] ?? '', $r['list'] ?? '', $r['shift'] ?? '' ) );
		$delta   = isset( $index[ $prevkey ] ) ? (float) $r['closing'] - $index[ $prevkey ] : null;
		$is_def  = true;
		$attrs   = '';
		foreach ( $facets as $key => $label ) {
			$v = (string) ( $r[ $key ] ?? '' );
			if ( $multi[ $key ] ) {
				$attrs .= ' data-' . $key . '="' . esc_attr( $v ) . '"';
				if ( isset( $def[ $key ] ) && $def[ $key ] !== $v ) {
					$is_def = false;
				}
			}
		}
		$anchor = 'p-' . am_slug( ( '' !== $only && $only !== (string) ( $uni['defaults']['list'] ?? 'Final' ) ? $only . ' ' : '' ) . $r['program'] . ' ' . ( $r['campus'] ?? '' ) . ' ' . ( $r['shift'] ?? '' ) );
		$idattr = '';
		if ( $is_def && ! isset( $anchors[ $anchor ] ) ) {
			$anchors[ $anchor ] = true;
			$idattr             = ' id="' . esc_attr( $anchor ) . '"';
		}
		$tr = '<tr' . $idattr . $attrs . ( $is_def ? '' : ' class="am-off"' ) . ( $many ? ' data-i="' . count( $all_tr ) . '"' : '' ) . '>'
			// On phones the campus moves under the program (am-mcamp) and its column hides (audit FE:
			// the sticky Program column showed "MBBS (private)" ×30 with the college cut off).
			. '<th scope="row">' . esc_html( (string) $r['program'] ) . ( $show_campus && ! empty( $r['campus'] ) ? '<small class="am-mcamp">' . esc_html( (string) $r['campus'] . ( ! empty( $r['shift'] ) ? ', ' . $r['shift'] : '' ) ) . '</small>' : '' ) . ( ! empty( $r['note'] ) ? '<small>' . esc_html( (string) $r['note'] ) . '</small>' : '' ) . '</th>'
			// Closing % second, so it stays on screen at 375 px (Fix 5, 2026-11-01).
			. '<td data-v="' . esc_attr( (string) (float) $r['closing'] ) . '"><strong>' . esc_html( am_pct( $r['closing'] ) ) . '</strong></td>'
			. ( $show_campus ? '<td class="am-camp">' . esc_html( (string) ( $r['campus'] ?? '' ) ) . ( ! empty( $r['shift'] ) ? '<small>' . esc_html( (string) $r['shift'] ) . '</small>' : '' ) . '</td>' : '' )
			. ( $multi['category'] ? '<td>' . esc_html( (string) ( $r['category'] ?? '' ) ) . '</td>' : '' )
			. ( $multi['list'] ? '<td>' . esc_html( (string) ( $r['list'] ?? '' ) ) . '</td>' : '' )
			. ( $multi['year'] ? '<td>' . esc_html( (string) $r['year'] ) . '</td>' : '' )
			. ( $show_delta ? ( null === $delta ? '<td>—</td>' : '<td data-v="' . esc_attr( (string) round( $delta, 3 ) ) . '">' . esc_html( ( $delta > 0 ? '+' : '' ) . number_format( $delta, 2 ) ) . '</td>' ) : '' )
			. ( $multi_src ? '<td><a href="#am-src-' . esc_attr( $id . '-' . ( $src_no[ $src_key( $r ) ] + 1 ) ) . '">[' . ( $src_no[ $src_key( $r ) ] + 1 ) . ']</a></td>' : '' )
			. '</tr>';
		$all_tr[] = $tr;
		if ( $is_def ) {
			$def_tr .= $tr;
		} else {
			$more_tr .= $tr;
			++$n_more;
		}
	}

	$cols = array( array( 'Program', false ), array( 'Closing aggregate', true ) );
	$show_campus && ( $cols[] = array( 'Campus', false ) );
	$multi['category'] && ( $cols[] = array( 'Category', false ) );
	$multi['list'] && ( $cols[] = array( 'List', false ) );
	$multi['year'] && ( $cols[] = array( 'Year', false ) );
	$show_delta && ( $cols[] = array( 'Change vs previous year', true ) );
	$head = '';
	foreach ( $cols as $i => $c ) {
		$head .= '<th scope="col"' . ( 'Campus' === $c[0] ? ' class="am-camp"' : '' ) . '><button type="button" data-am-sort="' . $i . '"' . ( $c[1] ? ' data-num' : '' ) . '>' . esc_html( $c[0] ) . '</button></th>';
	}
	$multi_src && ( $head .= '<th scope="col">Source</th>' );

	// One source → one line under the table; several → numbered list the [n] cells point to.
	if ( $multi_src ) {
		$src_html = '<ol class="am-muted am-srcs">';
		foreach ( $srcs as $n => $k ) {
			list( $u, $arch ) = explode( '|', $k, 2 );
			$src_html        .= '<li id="am-src-' . esc_attr( $id . '-' . ( $n + 1 ) ) . '">' . am_source_link( $u, $arch ) . '</li>';
		}
		$src_html .= '</ol>';
	} else {
		list( $u, $arch ) = explode( '|', $srcs[0], 2 );
		$src_html         = '<p class="am-muted am-srcs">Source of every row: ' . am_source_link( $u, $arch ) . '</p>';
	}
	if ( ! empty( $uni['table_note'] ) ) {
		$src_html .= '<p class="am-muted am-tnote">' . esc_html( (string) $uni['table_note'] ) . '</p>';
	}
	$caption = $short . ' merit list: closing aggregates' . ( '' !== $only ? ' (' . $only . ')' : '' ) . ( $fixed ? ', ' . implode( ', ', $fixed ) : '' );

	$all     = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) ) );
	$years   = array_map( fn( $r ) => (int) $r['year'], $all );
	$sources = array_values( array_unique( array_map( fn( $r ) => (string) $r['source_url'], $all ) ) );
	$known   = array_column( am_registry( 'dataset' ), 'id' );
	in_array( $base, $known, true ) || am_registry(
		'dataset',
		array(
			'id'          => $base,
			'name'        => $short . ' closing merits ' . min( $years ) . '–' . max( $years ),
			'description' => 'Program-wise closing aggregates (last admitted candidate) for ' . ( $uni['name'] ?? $short ) . ', by year, merit list, category and campus, copied from official merit lists.',
			'keywords'    => array( $short . ' merit list', $short . ' closing merit', 'aggregate' ),
			'temporal'    => min( $years ) . '/' . max( $years ),
			'based_on'    => $sources,
			'variables'   => array( 'Closing aggregate (%)' ),
			'json_url'    => am_data_url( (string) ( $uni['slug'] ?? $atts['uni'] ) ),
			'modified'    => (string) ( $uni['updated'] ?? '' ) ?: null,
		)
	);

	$big = $many && $n_more > 100;
	return am_season_status( $uni )
		. '<div class="am-merit" data-am-table="' . esc_attr( $id ) . '">'
		. ( $filters ? '<div class="am-filters">' . $filters . ( count( $all_tr ) > 20 ? '<div class="am-field am-js-only"><label for="am-mq-' . esc_attr( $id ) . '">Search program or campus</label><input id="am-mq-' . esc_attr( $id ) . '" type="search" data-am-q placeholder="e.g. Computer, Nishtar"></div>' : '' ) . '</div>' : '' )
		. '<p class="am-muted am-count" aria-live="polite"></p>'
		. '<p class="am-share am-wa-copy" data-am-wa hidden><button type="button" class="am-btn" data-am-copy="">Copy top 10 for WhatsApp</button> <a class="am-btn am-btn--wa" href="#" target="_blank" rel="noopener">Share top 10 on WhatsApp</a></p>'
		. '<div class="am-table-wrap"><table class="am-table am-sortable"><caption>' . esc_html( $caption ) . '</caption><thead><tr>' . $head . '</tr></thead><tbody>' . ( $big ? $def_tr : implode( '', $all_tr ) ) . '</tbody></table></div>'
		// Big lists (UET 700+, PU 600+ rows): rows outside the default view wait in a <template>, out of
		// the page's DOM, until a filter or sort needs them; data-i restores the table order (audit CI-04/TS-05).
		. ( $big ? '<template class="am-more" data-rows="' . count( $all_tr ) . '">' . $more_tr . '</template>' : '' )
		. $src_html
		. '<p class="am-muted am-data-link">Download this data: <a href="' . esc_url( am_data_url( (string) ( $uni['slug'] ?? $atts['uni'] ) ) ) . '">' . esc_html( $short ) . ' JSON</a> (' . esc_html( (string) am_site( 'data_license.attribution' ) ) . ', ' . esc_html( (string) am_site( 'data_license.name' ) ) . ').</p>'
		. am_facts_html( $uni, $def['list'], $def['category'] )
		. am_trend_svg( $uni, $def['list'], $def['category'] )
		. '</div>'
		. am_check_box( $uni );
}

/**
 * Official public-college fee lines for the predictor (Tier 2 idea 9), keyed by university short
 * name: shown when the result lists that university's public MBBS/BDS seats. UHS only for now
 * (uhs.json "fee", prospectus source); other universities have no stored fee.
 */
function am_predictor_fees(): array {
	$out = array();
	foreach ( am_unis() as $uni ) {
		$fee = (array) ( $uni['fee'] ?? array() );
		if ( empty( $fee['mbbs'] ) || empty( $fee['source_url'] ) ) {
			continue;
		}
		$rs = fn( $n ) => 'Rs ' . number_format( (float) $n );
		$out[ (string) $uni['short'] ] = array(
			'text' => 'Public MBBS fee, session ' . (string) $fee['session'] . ': ' . $rs( $fee['mbbs'][0] ) . ' in the first year, ' . $rs( array_sum( (array) $fee['mbbs'] ) ) . ' for five years, the same at every Punjab public medical college (Overseas Pakistanis quota excepted). Private colleges set their own fees.',
			'url'  => (string) $fee['source_url'],
		);
	}
	return $out;
}

// ------------------------------------------------------------------ [am_predictor]
function am_sc_predictor( $atts ): string {
	$rows   = array();
	$fields = array();
	$provs   = array();
	$sectors = array();
	$unis    = array();
	$saved  = array();
	foreach ( am_unis() as $slug => $uni ) {
		$short = (string) ( $uni['short'] ?? $uni['name'] ?? $slug );
		$url   = ! empty( $uni['meritlist_path'] ) ? home_url( (string) $uni['meritlist_path'] ) : '';
		$prov  = am_uni_provinces( $uni );
		$prows = am_predict_rows( $uni );
		if ( $prows ) {
			$unis[ $short ] = true;
			if ( ! empty( $uni['formulas'] ) ) {
				$saved[] = array(
					'id'  => am_slug( (string) ( $uni['slug'] ?? $slug ) ),
					'uni' => $short,
				);
			}
		}
		foreach ( $prows as $r ) {
			if ( ! empty( $uni['eligibility'] ) ) {
				list( $min, $note ) = am_floor_min( $uni, (string) $r['program'] );
				if ( $min > 0 ) {
					$r['min']     = $min;
					$r['minNote'] = $note;
				}
			}
			$r['uni']    = $short;
			$r['prov']   = $prov;
			// Public or private college (Tier 2 idea 9): UHS and NUMS name private seats "(private)".
			$r['sector']             = preg_match( '/\(private\)/i', (string) $r['program'] ) ? 'Private' : 'Public';
			$sectors[ $r['sector'] ] = true;
			$r['url']  = $url ? $url . '#' . $r['anchor'] : '';
			unset( $r['anchor'] );
			$rows[] = $r;
			if ( $r['field'] ) {
				$fields[ $r['field'] ] = true;
			}
		}
		foreach ( $prov as $pv ) {
			$provs[ $pv ] = true;
		}
	}
	if ( ! $rows ) {
		return am_stub( 'am_predictor', (array) $atts );
	}
	am_registry(
		'webapp',
		array(
			'id'          => 'predictor',
			'name'        => 'Admission Chance Predictor',
			'description' => 'Compares your aggregate with the newest official final-list, open-merit closing aggregate of each program across Pakistani universities.',
		)
	);
	$select = function ( string $key, string $label, array $opts ): string {
		if ( count( $opts ) < 2 ) {
			return '';
		}
		$o = '<option value="">All</option>';
		foreach ( array_keys( $opts ) as $v ) {
			$o .= '<option value="' . esc_attr( (string) $v ) . '">' . esc_html( ucfirst( (string) $v ) ) . '</option>';
		}
		return '<div class="am-field"><label for="am-pr-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><select id="am-pr-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . $o . '</select></div>';
	};
	ksort( $fields );
	ksort( $provs );
	ksort( $unis, SORT_FLAG_CASE | SORT_STRING );
	return '<form class="am-predictor" data-am-predictor novalidate>'
		. '<div class="am-field"><label for="am-pr-agg">Your aggregate (%)</label><input id="am-pr-agg" name="agg" type="number" inputmode="decimal" min="0" max="100" step="any" required><small>Each university uses its own formula, so use the aggregate you got from that university\'s calculator and pick the university below.</small></div>'
		. '<div class="am-saved" hidden></div>'
		. $select( 'uni', 'University', $unis )
		. $select( 'field', 'Field', $fields )
		. $select( 'prov', 'Province', $provs )
		. $select( 'sector', 'College type', $sectors )
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Show my chances</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<noscript><p>The predictor needs JavaScript. Each university\'s merit-list page shows the same closing aggregates as a table.</p></noscript>'
		. am_json_script(
			'am-pred-data',
			array(
				'rows'  => $rows,
				'band'  => AM_BAND,
				'saved' => $saved,
				'share'   => (string) am_site( 'share.predictor' ),
				'prefUrl' => am_find_page( '/guides/uhs-college-preference-order/' ) ? home_url( '/guides/uhs-college-preference-order/' ) : '',
				'fees'    => am_predictor_fees(),
			)
		)
		. '</form>';
}

// ------------------------------------------------------------------ [am_sources_table]
/**
 * Official sources per university (data/sources.json, one entry per body). For an entry with a
 * slug, the merit-list documents behind data/universities/<slug>.json rows are added from the data
 * itself, so a university with rows never reads "not published" (audit F-03).
 */
function am_sc_sources_table(): string {
	$items = (array) ( am_data( 'sources' )['universities'] ?? array() );
	if ( ! $items ) {
		return '';
	}
	$li   = fn( string $link, string $extra = '' ) => '<li>' . $link . ( '' !== $extra ? ' <span class="am-muted">(' . esc_html( $extra ) . ')</span>' : '' ) . '</li>';
	$body = '';
	foreach ( $items as $u ) {
		$docs = '';
		$seen = array();
		foreach ( (array) ( $u['documents'] ?? array() ) as $d ) {
			$seen[ (string) $d['url'] ] = true;
			$docs                      .= $li( '<a href="' . esc_url( (string) $d['url'] ) . '" rel="noopener" target="_blank">' . esc_html( (string) $d['label'] ) . '</a>', ! empty( $d['fetched'] ) ? 'checked ' . (string) $d['fetched'] : '' );
		}
		$uni  = ! empty( $u['slug'] ) ? am_uni( sanitize_key( (string) $u['slug'] ) ) : array();
		$rows = array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) );
		$by   = array();
		foreach ( $rows as $r ) {
			$k = (string) $r['source_url'] . '|' . (string) ( $r['archive_url'] ?? '' );
			$by[ $k ]['years'][ (int) $r['year'] ]            = true;
			$by[ $k ]['lists'][ (string) ( $r['list'] ?? '' ) ] = true;
			$by[ $k ]['n']                                    = ( $by[ $k ]['n'] ?? 0 ) + 1;
		}
		$merit = '';
		foreach ( $by as $k => $m ) {
			list( $url, $arch ) = explode( '|', $k, 2 );
			if ( isset( $seen[ $url ] ) && '' === $arch ) {
				continue;
			}
			$years = array_keys( $m['years'] );
			sort( $years );
			$lists  = array_map( 'am_list_label', array_filter( array_keys( $m['lists'] ) ) );
			$merit .= $li( am_source_link( $url, $arch ), implode( ', ', $years ) . ( $lists ? ', ' . implode( '; ', $lists ) : '' ) . ', ' . $m['n'] . ' closing merits' );
		}
		if ( $merit ) {
			$docs .= count( $by ) > 6
				? '<li><details><summary>' . esc_html( count( $by ) . ' merit-list documents behind ' . count( $rows ) . ' closing merits' ) . '</summary><ul>' . $merit . '</ul></details></li>'
				: $merit;
		}
		if ( $docs ) {
			$cell = '<ul>' . $docs . '</ul>' . ( $uni && ! $rows ? '<p class="am-muted">No official program-wise closing merit published (calculator only).</p>' : '' );
		} else {
			$cell = $uni ? 'No official program-wise closing merit published (calculator only).' : 'No official closing merit online yet.';
		}
		$body .= '<tr><th scope="row">' . esc_html( (string) $u['name'] ) . '</th>'
			. '<td><a href="' . esc_url( (string) $u['website'] ) . '" rel="noopener" target="_blank">' . esc_html( (string) preg_replace( '/^www\./', '', (string) wp_parse_url( (string) $u['website'], PHP_URL_HOST ) ) ) . '</a></td>'
			. '<td>' . $cell . '</td></tr>';
	}
	return '<div class="am-table-wrap"><table class="am-table"><caption>Official sources by university</caption><thead><tr><th scope="col">University / body</th><th scope="col">Official website</th><th scope="col">Documents we use</th></tr></thead><tbody>' . $body . '</tbody></table></div>';
}

// ------------------------------------------------------------------ [am_contact]
function am_sc_contact(): string {
	$email = sanitize_email( (string) am_site( 'contact.email' ) );
	if ( ! $email ) {
		return '<p>Email address coming soon.</p>';
	}
	return '<p class="am-contact"><strong>Email:</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_calculator', 'am_sc_calculator' );
		add_shortcode( 'am_merit_table', 'am_sc_merit_table' );
		add_shortcode( 'am_predictor', 'am_sc_predictor' );
		add_shortcode( 'am_sources_table', 'am_sc_sources_table' );
		add_shortcode( 'am_contact', 'am_sc_contact' );
		add_shortcode( 'am_eligibility', 'am_sc_eligibility' );
		add_shortcode(
			'am_faq',
			function ( $atts ) {
				$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_faq' );
				$uni  = am_uni( am_uni_attr( $atts ) );
				return empty( $uni['faq'] ) ? am_stub( 'am_faq', $atts ) : am_faq_html( (array) $uni['faq'] );
			}
		);
		add_shortcode(
			'am_answer',
			function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'uni'  => '',
						'type' => 'calculator',
					),
					(array) $atts,
					'am_answer'
				);
				$uni  = am_uni( am_uni_attr( $atts ) );
				$text = (string) ( $uni['answers'][ $atts['type'] ] ?? '' );
				$src  = (string) ( $uni['formulas'][0]['source_url'] ?? '' );
				return '' === $text ? am_stub( 'am_answer', $atts ) : am_answer_html( $text, $src );
			}
		);
	}
);
