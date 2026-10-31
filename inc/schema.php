<?php
/**
 * Page-level JSON-LD nodes added to the am_jsonld_graph filter (Organization + WebSite are in
 * inc/seo.php). Every repo page gets WebPage (+ BreadcrumbList) and the author Person; shortcodes
 * register FAQ items, Datasets and WebApplications through am_registry() while the body renders.
 */

defined( 'ABSPATH' ) || exit;

/** Absolute site URL with a trailing slash. */
function am_site_url(): string {
	return trailingslashit( (string) am_site( 'url', home_url( '/' ) ) );
}

/** The author Person node (also the target of every author box). */
function am_person_node(): array {
	$base = am_site_url();
	return array_filter(
		array(
			'@type'    => 'Person',
			'@id'      => $base . 'about/#author',
			'name'     => am_site( 'author.name' ),
			'url'      => am_site( 'author.url', $base . 'about/#author' ),
			'jobTitle' => am_site( 'author.role' ) ?: null,
			'worksFor' => array( '@id' => $base . '#organization' ),
			'address'  => am_site( 'author.city' ) ? array(
				'@type'           => 'PostalAddress',
				'addressLocality' => am_site( 'author.city' ),
				'addressCountry'  => 'PK',
			) : null,
			'sameAs'   => am_site( 'author.same_as' ) ?: null,
		)
	);
}

/** Map of a page's "schema_type" field to schema.org WebPage subtypes. */
const AM_WEBPAGE_TYPES = array( 'WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'FAQPage' );

add_filter(
	'am_jsonld_graph',
	function ( array $graph ) {
		$page = am_page_seo();
		if ( ! $page ) {
			return $graph;
		}
		$base    = am_site_url();
		$url     = home_url( (string) $page['path'] );
		$updated = am_page_updated( $page );
		$type    = in_array( $page['schema_type'] ?? '', AM_WEBPAGE_TYPES, true ) ? $page['schema_type'] : 'WebPage';
		$crumbs  = am_breadcrumb_items( $page );

		$graph['person'] = am_person_node();

		$webpage = array(
			'@type'        => $type,
			'@id'          => $url . '#webpage',
			'url'          => $url,
			'name'         => wp_get_document_title(),
			'description'  => am_description(),
			'inLanguage'   => am_site( 'language', 'en-PK' ),
			'isPartOf'     => array( '@id' => $base . '#website' ),
			'dateModified' => $updated,
			'author'       => array( '@id' => $base . 'about/#author' ),
			'publisher'    => array( '@id' => $base . '#organization' ),
		);
		if ( ! empty( $page['published'] ) ) {
			$webpage['datePublished'] = $page['published'];
		}
		if ( 'AboutPage' === $type && '/about/' === $page['path'] ) {
			$webpage['mainEntity'] = array( '@id' => $base . '#organization' );
		}
		$image = am_og_image();
		if ( $image ) {
			$webpage['primaryImageOfPage'] = array(
				'@type' => 'ImageObject',
				'url'   => $image,
			);
		}
		if ( $crumbs ) {
			$webpage['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
			$list                  = array();
			foreach ( $crumbs as $i => $crumb ) {
				$list[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb['name'],
					'item'     => $crumb['url'],
				);
			}
			$graph['breadcrumb'] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => $list,
			);
		}
		$graph['webpage'] = $webpage;

		// FAQPage: one node per page, all FAQ items rendered on it.
		$faq = am_registry( 'faq' );
		if ( $faq ) {
			$entities = array();
			$seen     = array();
			foreach ( $faq as $item ) {
				if ( isset( $seen[ $item['q'] ] ) ) {
					continue;
				}
				$seen[ $item['q'] ] = true;
				$entities[]         = array(
					'@type'          => 'Question',
					'name'           => $item['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $item['a'] ),
					),
				);
			}
			$graph['faq'] = array(
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'isPartOf'   => array( '@id' => $url . '#webpage' ),
				'mainEntity' => $entities,
			);
		}

		// WebApplication (calculators, predictor) and Dataset (merit tables) from shortcodes.
		foreach ( am_registry( 'webapp' ) as $i => $app ) {
			$graph[ 'webapp' . $i ] = array_filter(
				array(
					'@type'               => 'WebApplication',
					'@id'                 => $url . '#app-' . $app['id'],
					'name'                => $app['name'],
					'url'                 => $url,
					'description'         => $app['description'] ?? null,
					'applicationCategory' => 'EducationalApplication',
					'operatingSystem'     => 'Any',
					'browserRequirements' => 'Requires JavaScript',
					'isAccessibleForFree' => true,
					'offers'              => array(
						'@type'         => 'Offer',
						'price'         => '0',
						'priceCurrency' => 'PKR',
					),
					'publisher'           => array( '@id' => $base . '#organization' ),
				)
			);
		}
		foreach ( am_registry( 'dataset' ) as $i => $ds ) {
			$graph[ 'dataset' . $i ] = array_filter(
				array(
					'@type'               => 'Dataset',
					'@id'                 => $url . '#dataset-' . $ds['id'],
					'name'                => $ds['name'],
					'description'         => $ds['description'],
					'url'                 => $url,
					'keywords'            => $ds['keywords'] ?? null,
					'temporalCoverage'    => $ds['temporal'] ?? null,
					'spatialCoverage'     => array(
						'@type' => 'Place',
						'name'  => 'Pakistan',
					),
					'dateModified'        => $ds['modified'] ?? $updated,
					'isAccessibleForFree' => true,
					'license'             => am_site( 'data_license.url' ),
					'creditText'          => am_site( 'data_license.attribution' ),
					'creator'             => array( '@id' => $base . '#organization' ),
					'isBasedOn'           => $ds['based_on'] ?? null,
					'variableMeasured'    => $ds['variables'] ?? null,
					'distribution'        => array(
						array(
							'@type'          => 'DataDownload',
							'encodingFormat' => 'application/json',
							'contentUrl'     => $ds['json_url'],
						),
					),
				)
			);
		}
		return $graph;
	},
	10
);
