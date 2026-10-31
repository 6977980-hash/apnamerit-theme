<?php
/**
 * SEO layer. The owner chose NOT to use Rank Math (2026-10-31), so the theme does it all:
 * <title>, meta description, canonical, robots, Open Graph, JSON-LD, core sitemap and robots.txt.
 *
 * Per-page titles/descriptions live in data/pages.json (edited through git), keyed by URL path.
 * Tokens {season_year} and {data_year} are replaced from data/site.json, so years are never typed.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace {season_year}, {data_year} and {brand} tokens.
 */
function am_tokens( string $text ): string {
	return strtr(
		$text,
		array(
			'{season_year}' => (string) am_site( 'season_year' ),
			'{data_year}'   => (string) am_site( 'data_year' ),
			'{brand}'       => (string) am_site( 'brand', 'Apna Merit' ),
		)
	);
}

/**
 * Path of the current request as used in data/pages.json: "/" or "/some/slug/".
 */
function am_current_path(): string {
	if ( am_virtual_page() ) {
		return (string) am_virtual_page()['path'];
	}
	if ( is_front_page() ) {
		return '/';
	}
	if ( is_singular() ) {
		$path = wp_parse_url( (string) get_permalink(), PHP_URL_PATH );
		return '/' . trim( (string) $path, '/' ) . '/';
	}
	return '';
}

/**
 * SEO entry for the current page from data/pages.json, or an empty array.
 */
function am_page_seo(): array {
	$path = am_current_path();
	return '' === $path ? array() : am_find_page( $path );
}

/** Canonical URL for the current request (empty when the page should not have one). */
function am_canonical(): string {
	if ( am_virtual_page() ) {
		return home_url( am_virtual_page()['path'] );
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		return (string) get_permalink();
	}
	if ( is_category() ) {
		return (string) get_category_link( get_queried_object_id() );
	}
	return '';
}

/** Meta description: pages.json, else the post excerpt, else the site tagline. */
function am_description(): string {
	$seo = am_page_seo();
	if ( ! empty( $seo['description'] ) ) {
		return am_tokens( $seo['description'] );
	}
	if ( is_singular() && has_excerpt() ) {
		return wp_strip_all_tags( get_the_excerpt() );
	}
	return (string) am_site( 'tagline' );
}

// <title>.
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$seo = am_page_seo();
		return empty( $seo['title'] ) ? $title : am_tokens( $seo['title'] );
	}
);
add_filter( 'document_title_separator', fn() => '|' );

// Robots: noindex search, 404, paginated and non-category archives.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_search() || is_404() || is_paged() || ( is_archive() && ! is_category() ) || ! empty( am_page_seo()['noindex'] ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		} else {
			$robots['max-image-preview'] = 'large';
		}
		return $robots;
	}
);

// We print our own canonical.
remove_action( 'wp_head', 'rel_canonical' );

// Description, canonical, Open Graph, Twitter.
add_action(
	'wp_head',
	function () {
		$desc  = am_description();
		$canon = am_canonical();
		$title = wp_get_document_title();
		$image = am_page_seo()['og_image'] ?? '';
		$out   = array();
		$out[] = '<meta name="description" content="' . esc_attr( $desc ) . '">';
		if ( $canon && ! is_paged() ) {
			$out[] = '<link rel="canonical" href="' . esc_url( $canon ) . '">';
		}
		$out[] = '<meta property="og:locale" content="en_PK">';
		$out[] = '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">';
		$out[] = '<meta property="og:site_name" content="' . esc_attr( am_site( 'brand' ) ) . '">';
		$out[] = '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		$out[] = '<meta property="og:description" content="' . esc_attr( $desc ) . '">';
		if ( $canon ) {
			$out[] = '<meta property="og:url" content="' . esc_url( $canon ) . '">';
		}
		if ( $image ) {
			$out[] = '<meta property="og:image" content="' . esc_url( AM_URI . '/assets/og/' . $image ) . '">';
		}
		$out[] = '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">';
		echo implode( "\n", $out ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	2
);

// JSON-LD: Organization + WebSite on every page; templates add more nodes via am_jsonld_graph.
add_filter(
	'am_jsonld_graph',
	function ( array $graph ) {
		$url             = trailingslashit( (string) am_site( 'url', home_url( '/' ) ) );
		$graph['org']    = array_filter(
			array(
				'@type'  => 'Organization',
				'@id'    => $url . '#organization',
				'name'   => am_site( 'organization.name', am_site( 'brand' ) ),
				'url'    => $url,
				'logo'   => am_site( 'organization.logo' ) ?: null,
				'sameAs' => am_site( 'organization.same_as' ) ?: null,
			)
		);
		$graph['site']   = array(
			'@type'       => 'WebSite',
			'@id'         => $url . '#website',
			'name'        => am_site( 'brand' ),
			'description' => am_site( 'tagline' ),
			'url'         => $url,
			'inLanguage'  => am_site( 'language', 'en-PK' ),
			'publisher'   => array( '@id' => $url . '#organization' ),
		);
		return $graph;
	},
	5
);

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

// Core sitemap (/wp-sitemap.xml): pages, posts and categories only.
add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxonomies ) {
		unset( $taxonomies['post_tag'], $taxonomies['post_format'] );
		return $taxonomies;
	}
);

// robots.txt (virtual): allow everyone, AI crawlers named explicitly (plan section 5).
add_filter(
	'robots_txt',
	function ( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$lines = array();
		foreach ( array( 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot', 'Bingbot' ) as $bot ) {
			$lines[] = "User-agent: $bot";
			$lines[] = 'Allow: /';
			$lines[] = '';
		}
		$lines[] = 'User-agent: *';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Allow: /wp-admin/admin-ajax.php';
		$lines[] = 'Disallow: /?s=';
		$lines[] = '';
		$lines[] = 'Sitemap: ' . home_url( '/wp-sitemap.xml' );
		return implode( "\n", $lines ) . "\n";
	},
	10,
	2
);
