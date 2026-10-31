<?php
/**
 * SEO glue. Rank Math handles titles/meta/sitemap; its schema module stays OFF because the
 * theme outputs JSON-LD (Phase 1).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math custom variables: %am_season_year% and %am_data_year%, read from data/site.json,
 * so years are never typed into titles or templates.
 */
add_action(
	'rank_math/vars/register_extra_replacements',
	function () {
		if ( ! function_exists( 'rank_math_register_var_replacement' ) ) {
			return;
		}
		rank_math_register_var_replacement(
			'am_season_year',
			array(
				'name'        => 'Season year',
				'description' => 'Admission season year from data/site.json',
				'variable'    => 'am_season_year',
				'example'     => (string) am_site( 'season_year' ),
			),
			fn() => (string) am_site( 'season_year' )
		);
		rank_math_register_var_replacement(
			'am_data_year',
			array(
				'name'        => 'Data year',
				'description' => 'Closing-merit data year from data/site.json',
				'variable'    => 'am_data_year',
				'example'     => (string) am_site( 'data_year' ),
			),
			fn() => (string) am_site( 'data_year' )
		);
	}
);

/**
 * JSON-LD output hook. Phase 1 fills am_jsonld_graph with Organization, WebSite, BreadcrumbList, etc.
 */
add_action(
	'wp_head',
	function () {
		$graph = apply_filters( 'am_jsonld_graph', array() );
		if ( empty( $graph ) ) {
			return;
		}
		$doc = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	},
	20
);
