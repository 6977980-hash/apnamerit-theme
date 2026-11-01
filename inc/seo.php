<?php
/**
 * SEO layer. The owner chose NOT to use Rank Math (2026-10-31), so the theme does it all:
 * <title>, meta description, canonical, robots, Open Graph, JSON-LD, core sitemap and robots.txt.
 *
 * Per-page titles/descriptions live in data/pages.json (edited through git), keyed by URL path.
 * Tokens {season_year}, {data_year} and {year:<slug>} are replaced from data/site.json, so years are never typed.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace {season_year}, {data_year}, {brand} and {founded} tokens, plus {year:<slug>} = the
 * year of that university's default merit view (am_uni_year), e.g. {year:pu}, and
 * {predict_count} = programs the predictor covers, {session:<slug>} = data/calendar.json seasons.<slug>.session.
 */
function am_tokens( string $text ): string {
	if ( str_contains( $text, '{year:' ) ) {
		$text = (string) preg_replace_callback(
			'/\{year:([a-z0-9-]+)\}/',
			fn( $m ) => (string) ( am_uni_year( am_uni( $m[1] ) ) ?: am_site( 'data_year' ) ),
			$text
		);
	}
	if ( str_contains( $text, '{session:' ) ) {
		$text = (string) preg_replace_callback(
			'/\{session:([a-z0-9-]+)\}/',
			fn( $m ) => (string) ( am_data( 'calendar' )['seasons'][ $m[1] ]['session'] ?? am_site( 'season_year' ) ),
			$text
		);
	}
	if ( str_contains( $text, '{predict_count}' ) ) {
		$text = str_replace( '{predict_count}', (string) array_sum( array_map( fn( $u ) => count( am_predict_rows( $u ) ), am_unis() ) ), $text );
	}
	return strtr(
		$text,
		array(
			'{season_year}' => (string) am_site( 'season_year' ),
			'{data_year}'   => (string) am_site( 'data_year' ),
			'{brand}'       => (string) am_site( 'brand', 'Apna Merit' ),
			'{founded}'     => (string) am_site( 'founded' ),
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

/**
 * OG image URL: the entry's "og_image" (file in assets/og/), else assets/og/<page-slug>.png made by
 * scripts/og.py, else assets/og/default.png.
 */
function am_og_image(): string {
	$seo   = am_page_seo();
	$names = array();
	if ( ! empty( $seo['og_image'] ) ) {
		$names[] = basename( (string) $seo['og_image'] );
	}
	if ( ! empty( $seo['path'] ) ) {
		$names[] = am_path_slug( (string) $seo['path'] ) . '.png';
	}
	$names[] = 'default.png';
	foreach ( $names as $name ) {
		$file = AM_DIR . '/assets/og/' . $name;
		if ( is_readable( $file ) ) {
			return AM_URI . '/assets/og/' . $name . '?v=' . (int) filemtime( $file );
		}
	}
	return '';
}

/**
 * File-safe slug of a page path: "/" → "home", "/uet/merit-list/" → "uet-merit-list" (same rule as scripts/og.py).
 */
function am_path_slug( string $path ): string {
	$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $path ) ), '-' );
	return '' === $slug ? 'home' : $slug;
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

// Robots: noindex search, 404, paginated pages and every archive.
add_filter(
	'wp_robots',
	function ( $robots ) {
		// A repo page with a query string is a shared result (?agg=…, ?o_mdcat=…): its canonical points
		// to the clean URL, which is enough. No noindex on top (audit CI-02: Google advises against
		// noindex plus a canonical to another URL).
		if ( is_search() || is_404() || is_paged() || is_archive() || ! empty( am_page_seo()['noindex'] ) ) {
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
		$image = am_og_image();
		$out   = array();
		$out[] = '<meta name="description" content="' . esc_attr( $desc ) . '">';
		foreach ( array( 'google' => 'google-site-verification', 'bing' => 'msvalidate.01' ) as $key => $meta ) {
			$code = (string) am_site( 'verification.' . $key );
			if ( '' !== $code && is_front_page() ) {
				$out[] = '<meta name="' . $meta . '" content="' . esc_attr( $code ) . '">';
			}
		}
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
			$out[] = '<meta property="og:image" content="' . esc_url( $image ) . '">';
			$out[] = '<meta property="og:image:width" content="1200">';
			$out[] = '<meta property="og:image:height" content="630">';
			$out[] = '<meta property="og:image:alt" content="' . esc_attr( $title ) . '">';
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
				'logo'   => am_logo_url() ?: null,
				'sameAs' => am_site( 'organization.same_as' ) ?: null,
				'foundingDate' => am_site( 'founded' ) ? (string) am_site( 'founded' ) : null,
				'founder'      => am_site( 'author.name' ) ? array( '@id' => $url . 'about/#author' ) : null,
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

// Core sitemap (/wp-sitemap.xml): repo pages (inc/router.php) and guide posts only (categories 301 home).
// WP 'page' post type is dropped: pages live in data/pages.json, and it would duplicate the homepage.
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		unset( $types['page'] );
		return $types;
	}
);
add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxonomies ) {
		unset( $taxonomies['post_tag'], $taxonomies['post_format'], $taxonomies['category'] );
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
		// One group for every crawler (audit CI-05): a named group would make that bot ignore the
		// disallows below. AI and search crawlers are all welcome (plan section 5).
		$lines   = array( '# All crawlers, including GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot and Bingbot, are welcome.' );
		$lines[] = 'User-agent: *';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Allow: /wp-admin/admin-ajax.php';
		$lines[] = 'Disallow: /?s=';
		$lines[] = 'Disallow: /*?s=';
		$lines[] = 'Disallow: /search/';
		$lines[] = '';
		$lines[] = 'Sitemap: ' . home_url( '/wp-sitemap.xml' );
		return implode( "\n", $lines ) . "\n";
	},
	10,
	2
);

/** Organization logo URL: site.json organization.logo, a full URL or a path inside the theme. */
function am_logo_url(): string {
	$logo = (string) am_site( 'organization.logo', '' );
	if ( '' === $logo || preg_match( '#^https?://#', $logo ) ) {
		return $logo;
	}
	$file = AM_DIR . '/' . ltrim( $logo, '/' );
	return is_readable( $file ) ? AM_URI . '/' . ltrim( $logo, '/' ) : '';
}

// <html lang>: the site is written for Pakistan (site.json language); ur-Latn helper blocks keep their own lang.
add_filter(
	'language_attributes',
	function ( $output ) {
		$lang = (string) am_site( 'language', 'en-PK' );
		return (string) preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output );
	}
);

/**
 * Security headers on every theme response (audit F-08). Sent from PHP because the theme's .htaccess
 * only covers the theme folder. /embed/<slug>/ widgets must stay embeddable by other sites, so they
 * get no frame-ancestors policy.
 */
add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! preg_match( '#^/embed/[a-z0-9-]+/?$#', $path ) ) {
			header( "Content-Security-Policy: frame-ancestors 'self'" );
			header( 'X-Frame-Options: SAMEORIGIN' );
		}
	}
);
