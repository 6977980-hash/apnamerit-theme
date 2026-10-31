<?php
/**
 * Core-sitemap provider for repo pages in data/pages.json.
 */

defined( 'ABSPATH' ) || exit;

class AM_Sitemap_Provider extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'ampages';
		$this->object_type = 'ampages';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( 1 !== (int) $page_num ) {
			return array();
		}
		$urls = array();
		foreach ( (array) ( am_data( 'pages' )['pages'] ?? array() ) as $page ) {
			if ( ! is_array( $page ) || ! empty( $page['noindex'] ) || empty( $page['path'] ) ) {
				continue;
			}
			$urls[] = array(
				'loc'     => home_url( $page['path'] ),
				'lastmod' => am_page_updated( $page ) . 'T00:00:00+05:00',
			);
		}
		return $urls;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
