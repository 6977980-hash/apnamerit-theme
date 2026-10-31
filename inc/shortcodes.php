<?php
/**
 * Shortcode registration. Phase 0 = stubs; real rendering arrives in Phase 1+ (master plan 0.4, 4.x).
 *
 *   [am_calculator uni="uet"]   calculator + predictor result for one university
 *   [am_merit_table uni="uet"]  closing-merit table with filters
 *   [am_predictor]              cross-university predictor
 *   [am_faq uni="uet"]          FAQ block (+ FAQPage schema)
 *   [am_answer uni="uet" type="calculator"]  40-60 word answer block
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise a university slug attribute.
 */
function am_uni_attr( array $atts ): string {
	return sanitize_key( $atts['uni'] ?? '' );
}

/**
 * Placeholder output used until a shortcode is implemented. Visible only to editors.
 */
function am_stub( string $tag, array $atts ): string {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return sprintf(
		'<div class="am-stub" data-shortcode="%s">[%s %s] — not implemented yet</div>',
		esc_attr( $tag ),
		esc_html( $tag ),
		esc_html( http_build_query( $atts, '', ' ' ) )
	);
}

add_action(
	'init',
	function () {
		add_shortcode(
			'am_calculator',
			fn( $atts ) => am_stub( 'am_calculator', shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_calculator' ) )
		);
		add_shortcode(
			'am_merit_table',
			fn( $atts ) => am_stub( 'am_merit_table', shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_merit_table' ) )
		);
		add_shortcode(
			'am_predictor',
			fn( $atts ) => am_stub( 'am_predictor', (array) $atts )
		);
		add_shortcode(
			'am_faq',
			fn( $atts ) => am_stub( 'am_faq', shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_faq' ) )
		);
		add_shortcode(
			'am_answer',
			fn( $atts ) => am_stub(
				'am_answer',
				shortcode_atts(
					array(
						'uni'  => '',
						'type' => 'calculator',
					),
					(array) $atts,
					'am_answer'
				)
			)
		);
	}
);
