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
function am_unis(): array {
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
 * Reference closing merit per program for the predictor (master plan 4.3; method on /methodology/):
 * default list + category, last two years up to data_year, reference = the higher closing.
 *
 * @return array<int, array> rows: program, campus, shift, ref, year, field, anchor
 */
function am_predict_rows( array $uni ): array {
	$dy   = (int) am_site( 'data_year' );
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
		$rows[ $key ]['ref']  = max( $rows[ $key ]['ref'], (float) $r['closing'] );
		$rows[ $key ]['year'] = max( $rows[ $key ]['year'], $year );
	}
	return array_values( $rows );
}

// ------------------------------------------------------------------ [am_calculator]
function am_sc_calculator( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_calculator' );
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
		);
		$html .= '<fieldset class="am-formula" data-am-fid="' . esc_attr( $fid ) . '"' . ( $n > 0 ? ' hidden' : '' ) . '><legend>' . esc_html( (string) $f['label'] ) . '</legend>';
		foreach ( (array) $f['parts'] as $p ) {
			$k     = esc_attr( $fid . '-' . $p['key'] );
			$html .= '<div class="am-field am-part"><label for="am-o-' . $k . '">' . esc_html( (string) $p['label'] ) . ' marks</label><div class="am-pair">'
				. '<input id="am-o-' . $k . '" name="o_' . esc_attr( (string) $p['key'] ) . '" type="number" inputmode="decimal" min="0" step="any" required>'
				. '<span>out of</span>'
				. '<input aria-label="' . esc_attr( (string) $p['label'] ) . ' total marks" name="t_' . esc_attr( (string) $p['key'] ) . '" type="number" inputmode="decimal" min="1" step="any" value="' . esc_attr( (string) $p['total'] ) . '" required>'
				. '</div><small>' . esc_html( ! empty( $p['hint'] ) ? (string) $p['hint'] : 'Weight ' . $p['weight'] . '%' ) . '</small></div>';
		}
		foreach ( (array) ( $f['bonus'] ?? array() ) as $b ) {
			$html .= '<label class="am-check"><input type="checkbox" name="b_' . esc_attr( (string) $b['key'] ) . '"> ' . esc_html( (string) $b['label'] ) . '</label>';
		}
		if ( ! empty( $f['source_url'] ) ) {
			$html .= '<p class="am-muted">Formula source: <a href="' . esc_url( (string) $f['source_url'] ) . '" rel="nofollow noopener" target="_blank">official notice</a></p>';
		}
		$html .= '</fieldset>';
	}

	$config = array(
		'uni'      => $short,
		'formulas' => $formulas,
		'rows'     => am_predict_rows( $uni ),
		'band'     => AM_BAND,
		'meritUrl' => ! empty( $uni['meritlist_path'] ) ? home_url( (string) $uni['meritlist_path'] ) : '',
		'url'      => home_url( am_request_path() ),
		'share'    => (string) am_site( 'share.whatsapp' ),
	);

	return '<form class="am-calc" data-am-calc="' . esc_attr( $id ) . '" novalidate>'
		. $html
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Calculate aggregate</button> <button type="reset" class="am-btn">Clear</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<noscript><p>The calculator needs JavaScript. You can still use the formula table below by hand.</p></noscript>'
		. am_json_script( 'am-calc-data', $config )
		. '</form>'
		. am_formula_table( $uni );
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
	}
	return $out;
}

// ------------------------------------------------------------------ [am_merit_table]
function am_sc_merit_table( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_merit_table' );
	$uni  = am_uni( am_uni_attr( $atts ) );
	$rows = array_values( array_filter( (array) ( $uni['merit'] ?? array() ), fn( $r ) => is_numeric( $r['closing'] ?? null ) && ! empty( $r['source_url'] ) ) );
	if ( ! $rows ) {
		return am_stub( 'am_merit_table', $atts );
	}
	$short = (string) ( $uni['short'] ?? $uni['name'] ?? '' );
	$id    = am_slug( (string) ( $uni['slug'] ?? $atts['uni'] ) );
	$dy    = (int) am_site( 'data_year' );
	$def   = array(
		'year'     => (string) $dy,
		'list'     => (string) ( $uni['defaults']['list'] ?? 'Final' ),
		'category' => (string) ( $uni['defaults']['category'] ?? 'Open merit' ),
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

	$body    = '';
	$anchors = array();
	$years   = array();
	foreach ( $rows as $r ) {
		$years[] = (int) $r['year'];
		$prevkey = implode( '|', array( $r['year'] - 1, $r['program'], $r['campus'] ?? '', $r['category'] ?? '', $r['list'] ?? '', $r['shift'] ?? '' ) );
		$delta   = isset( $index[ $prevkey ] ) ? (float) $r['closing'] - $index[ $prevkey ] : null;
		$is_def  = true;
		$attrs   = '';
		foreach ( $facets as $key => $label ) {
			$v      = (string) ( $r[ $key ] ?? '' );
			$attrs .= ' data-' . $key . '="' . esc_attr( $v ) . '"';
			if ( isset( $def[ $key ] ) && count( $values[ $key ] ) > 1 && $def[ $key ] !== $v ) {
				$is_def = false;
			}
		}
		$anchor = 'p-' . am_slug( $r['program'] . ' ' . ( $r['campus'] ?? '' ) . ' ' . ( $r['shift'] ?? '' ) );
		$idattr = '';
		if ( $is_def && ! isset( $anchors[ $anchor ] ) ) {
			$anchors[ $anchor ] = true;
			$idattr             = ' id="' . esc_attr( $anchor ) . '"';
		}
		$dtxt  = null === $delta ? '—' : ( $delta > 0 ? '+' : '' ) . number_format( $delta, 2 );
		$body .= '<tr' . $idattr . $attrs . ( $is_def ? '' : ' class="am-off"' ) . '>'
			. '<th scope="row">' . esc_html( (string) $r['program'] ) . ( ! empty( $r['note'] ) ? '<small>' . esc_html( (string) $r['note'] ) . '</small>' : '' ) . '</th>'
			. '<td>' . esc_html( (string) ( $r['campus'] ?? '' ) ) . ( ! empty( $r['shift'] ) ? '<small>' . esc_html( (string) $r['shift'] ) . '</small>' : '' ) . '</td>'
			. '<td>' . esc_html( (string) ( $r['category'] ?? '' ) ) . '</td>'
			. '<td>' . esc_html( (string) ( $r['list'] ?? '' ) ) . '</td>'
			. '<td>' . esc_html( (string) $r['year'] ) . '</td>'
			. '<td data-v="' . esc_attr( (string) (float) $r['closing'] ) . '"><strong>' . esc_html( am_pct( $r['closing'] ) ) . '</strong></td>'
			. '<td data-v="' . esc_attr( null === $delta ? '' : (string) $delta ) . '">' . esc_html( $dtxt ) . '</td>'
			. '<td><a href="' . esc_url( (string) $r['source_url'] ) . '" rel="nofollow noopener" target="_blank">Source</a></td>'
			. '</tr>';
	}

	$cols = array( 'Program', 'Campus', 'Category', 'List', 'Year', 'Closing aggregate', 'Change vs previous year', 'Source' );
	$head = '';
	foreach ( $cols as $i => $c ) {
		$head .= $i < 7
			? '<th scope="col"><button type="button" data-am-sort="' . $i . '"' . ( 5 === $i ? ' data-num' : '' ) . ( 6 === $i ? ' data-num' : '' ) . '>' . esc_html( $c ) . '</button></th>'
			: '<th scope="col">' . esc_html( $c ) . '</th>';
	}

	$sources = array_values( array_unique( array_map( fn( $r ) => (string) $r['source_url'], $rows ) ) );
	am_registry(
		'dataset',
		array(
			'id'          => $id,
			'name'        => $short . ' closing merits ' . min( $years ) . '–' . max( $years ),
			'description' => 'Program-wise closing aggregates (last admitted candidate) for ' . ( $uni['name'] ?? $short ) . ', by year, merit list, category and campus, copied from official merit lists.',
			'keywords'    => array( $short . ' merit list', $short . ' closing merit', 'aggregate' ),
			'temporal'    => min( $years ) . '/' . max( $years ),
			'based_on'    => $sources,
			'variables'   => array( 'Closing aggregate (%)' ),
			'json_url'    => AM_URI . '/data/universities/' . rawurlencode( (string) ( $uni['slug'] ?? $atts['uni'] ) ) . '.json',
			'modified'    => (string) ( $uni['updated'] ?? '' ) ?: null,
		)
	);

	return '<div class="am-merit" data-am-table="' . esc_attr( $id ) . '">'
		. ( $filters ? '<div class="am-filters">' . $filters . '</div>' : '' )
		. '<p class="am-muted am-count" aria-live="polite"></p>'
		. '<div class="am-table-wrap"><table class="am-table am-sortable"><caption>' . esc_html( $short . ' merit list: closing aggregates' ) . '</caption><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>'
		. '</div>';
}

// ------------------------------------------------------------------ [am_predictor]
function am_sc_predictor( $atts ): string {
	$rows   = array();
	$fields = array();
	$provs  = array();
	foreach ( am_unis() as $slug => $uni ) {
		$short = (string) ( $uni['short'] ?? $uni['name'] ?? $slug );
		$url   = ! empty( $uni['meritlist_path'] ) ? home_url( (string) $uni['meritlist_path'] ) : '';
		$prov  = (string) ( $uni['province'] ?? '' );
		foreach ( am_predict_rows( $uni ) as $r ) {
			$r['uni']  = $short;
			$r['prov'] = $prov;
			$r['url']  = $url ? $url . '#' . $r['anchor'] : '';
			unset( $r['anchor'] );
			$rows[] = $r;
			if ( $r['field'] ) {
				$fields[ $r['field'] ] = true;
			}
		}
		if ( $prov ) {
			$provs[ $prov ] = true;
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
			'description' => 'Compares your aggregate with the last two years of final-list open-merit closing aggregates across Pakistani universities.',
		)
	);
	$select = function ( string $key, string $label, array $opts ): string {
		if ( count( $opts ) < 2 ) {
			return '';
		}
		$o = '<option value="">All</option>';
		foreach ( array_keys( $opts ) as $v ) {
			$o .= '<option>' . esc_html( (string) $v ) . '</option>';
		}
		return '<div class="am-field"><label for="am-pr-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><select id="am-pr-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . $o . '</select></div>';
	};
	ksort( $fields );
	ksort( $provs );
	return '<form class="am-predictor" data-am-predictor novalidate>'
		. '<div class="am-field"><label for="am-pr-agg">Your aggregate (%)</label><input id="am-pr-agg" name="agg" type="number" inputmode="decimal" min="0" max="100" step="any" required></div>'
		. $select( 'field', 'Field', $fields )
		. $select( 'prov', 'Province', $provs )
		. '<div class="am-actions"><button type="submit" class="am-btn am-btn--primary">Show my chances</button></div>'
		. '<div class="am-result" aria-live="polite" hidden></div>'
		. '<noscript><p>The predictor needs JavaScript. Each university\'s merit-list page shows the same closing aggregates as a table.</p></noscript>'
		. am_json_script(
			'am-pred-data',
			array(
				'rows' => $rows,
				'band' => AM_BAND,
			)
		)
		. '</form>';
}

// ------------------------------------------------------------------ [am_sources_table]
function am_sc_sources_table(): string {
	$items = (array) ( am_data( 'sources' )['universities'] ?? array() );
	if ( ! $items ) {
		return '';
	}
	$body = '';
	foreach ( $items as $u ) {
		$docs = '';
		foreach ( (array) ( $u['documents'] ?? array() ) as $d ) {
			$docs .= '<li><a href="' . esc_url( (string) $d['url'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) $d['label'] ) . '</a>' . ( ! empty( $d['fetched'] ) ? ' <span class="am-muted">(checked ' . esc_html( (string) $d['fetched'] ) . ')</span>' : '' ) . '</li>';
		}
		$body .= '<tr><th scope="row">' . esc_html( (string) $u['name'] ) . '</th>'
			. '<td><a href="' . esc_url( (string) $u['website'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) wp_parse_url( (string) $u['website'], PHP_URL_HOST ) ) . '</a></td>'
			. '<td>' . ( $docs ? '<ul>' . $docs . '</ul>' : 'Not published yet' ) . '</td></tr>';
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
