<?php
/**
 * Shared page blocks: navigation, breadcrumb, answer, FAQ, Roman Urdu helper, sources,
 * author box, disclaimer, share, related links, ad slots. Used by templates and shortcodes.
 *
 * Content pages (rule 7) get every block; trust pages ("type": "trust") skip share and author box.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-request registry that shortcodes fill while the body renders (FAQ items, datasets,
 * web apps). The body is rendered before get_header(), so inc/schema.php can read it in <head>.
 *
 * @param string     $key  Registry bucket.
 * @param mixed|null $item Item to append; null to read.
 * @return array Items in the bucket.
 */
function am_registry( string $key, $item = null ): array {
	static $reg = array();
	if ( null !== $item ) {
		$reg[ $key ][] = $item;
	}
	return $reg[ $key ] ?? array();
}

/**
 * Slug used for anchors: "BSc Computer Science" → "bsc-computer-science".
 */
function am_slug( string $text ): string {
	return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $text ) ), '-' );
}

/**
 * Short label of a page for links and breadcrumbs: "crumb", else h1, else title.
 */
function am_page_label( array $page ): string {
	return am_tokens( (string) ( $page['crumb'] ?? $page['h1'] ?? $page['title'] ?? '' ) );
}

/**
 * Logo mark (inline SVG, no request): letter A on a green tile with a gold "top merit" dot.
 * Same drawing as assets/icons/icon.svg. Decorative: the brand text next to it is the name.
 */
function am_logo_mark( int $size = 36 ): string {
	return '<svg class="am-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><rect width="48" height="48" rx="12" fill="#0b6e4f"/><path d="M12 37 24 16l12 21M17.5 30h13" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="9.5" r="4" fill="#f5b700"/></svg>';
}

/**
 * Brand lockup: mark + "Apna Merit" wordmark (+ optional tagline), linked to home.
 */
function am_brand( bool $tagline = false ): string {
	$brand = (string) am_site( 'brand', 'Apna Merit' );
	$parts = explode( ' ', $brand, 2 );
	$words = '<span class="am-brand-a">' . esc_html( $parts[0] ) . '</span>' . ( isset( $parts[1] ) ? ' <span class="am-brand-b">' . esc_html( $parts[1] ) . '</span>' : '' );
	$tag   = $tagline ? '<span class="am-brand-s">Merit lists &amp; calculators</span>' : '';
	$cur   = '/' === am_request_path() ? ' aria-current="page"' : '';
	return '<a class="am-brand" href="' . esc_url( home_url( '/' ) ) . '"' . $cur . '>' . am_logo_mark() . '<span class="am-brand-t"><span class="am-brand-w">' . $words . '</span>' . $tag . '</span></a>';
}

/**
 * Navigation from data/nav.json. Footer items with a "col" field are grouped into titled columns.
 * "cta": true styles a header item as the highlighted button.
 */
function am_nav( string $which ): string {
	$items = (array) ( am_data( 'nav' )[ $which ] ?? array() );
	if ( ! $items ) {
		return '';
	}
	$current = am_request_path();
	$cols    = array();
	foreach ( $items as $item ) {
		$path  = (string) ( $item['path'] ?? '' );
		$aria  = $path === $current ? ' aria-current="page"' : '';
		$class = trim( ( empty( $item['hide_small'] ) ? '' : 'am-nav-wide' ) . ( empty( $item['cta'] ) ? '' : ' am-nav-cta' ) );
		$class = '' === $class ? '' : ' class="' . esc_attr( $class ) . '"';
		$col   = (string) ( $item['col'] ?? '' );
		$cols[ $col ] = ( $cols[ $col ] ?? '' ) . '<li' . $class . '><a href="' . esc_url( home_url( $path ) ) . '"' . $aria . '>' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</a></li>';
	}
	$label = 'header' === $which ? 'Main' : 'Footer';
	$out   = '';
	foreach ( $cols as $title => $lis ) {
		$list = '<ul>' . $lis . '</ul>';
		$out .= '' === $title ? $list : '<div class="am-fcol"><h2 class="am-fh">' . esc_html( (string) $title ) . '</h2>' . $list . '</div>';
	}
	return '<nav class="am-nav am-nav--' . esc_attr( $which ) . '" aria-label="' . esc_attr( $label ) . '">' . $out . '</nav>';
}

/**
 * Breadcrumb trail for a page: Home → optional "parents" (paths) → this page.
 *
 * @return array<int, array{name: string, url: string}>
 */
function am_breadcrumb_items( array $page ): array {
	if ( empty( $page['path'] ) || '/' === $page['path'] ) {
		return array();
	}
	$items = array(
		array(
			'name' => 'Home',
			'url'  => home_url( '/' ),
		),
	);
	foreach ( (array) ( $page['parents'] ?? array() ) as $parent_path ) {
		$parent = am_find_page( (string) $parent_path );
		if ( $parent ) {
			$items[] = array(
				'name' => am_page_label( $parent ),
				'url'  => home_url( $parent['path'] ),
			);
		}
	}
	$items[] = array(
		'name' => am_page_label( $page ),
		'url'  => home_url( $page['path'] ),
	);
	return $items;
}

function am_breadcrumb_html( array $page ): string {
	$items = am_breadcrumb_items( $page );
	if ( ! $items ) {
		return '';
	}
	$last = count( $items ) - 1;
	$out  = '';
	foreach ( $items as $i => $item ) {
		$out .= $i === $last
			? '<li aria-current="page">' . esc_html( $item['name'] ) . '</li>'
			: '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a></li>';
	}
	return '<nav class="am-crumbs" aria-label="Breadcrumb"><ol>' . $out . '</ol></nav>';
}

/**
 * 40-60 word answer block (AEO). $html is trusted repo content.
 */
function am_answer_html( string $html, string $source_url = '' ): string {
	if ( '' === trim( $html ) ) {
		return '';
	}
	$src = $source_url ? ' <a class="am-src" href="' . esc_url( $source_url ) . '" rel="nofollow noopener" target="_blank">Official source</a>' : '';
	return '<div class="am-answer"><p>' . wp_kses_post( am_tokens( $html ) ) . $src . '</p></div>';
}

/**
 * FAQ block (details/summary) and registers the items for FAQPage JSON-LD.
 *
 * @param array<int, array{q: string, a: string}> $items Questions and answers (answers may hold simple HTML).
 */
function am_faq_html( array $items, string $heading = 'Frequently asked questions' ): string {
	$out = '';
	foreach ( $items as $item ) {
		$q = am_tokens( (string) ( $item['q'] ?? '' ) );
		$a = am_tokens( (string) ( $item['a'] ?? '' ) );
		if ( '' === $q || '' === $a ) {
			continue;
		}
		am_registry(
			'faq',
			array(
				'q' => $q,
				'a' => $a,
			)
		);
		$lang = ! empty( $item['lang'] ) ? ' lang="' . esc_attr( $item['lang'] ) . '"' : '';
		$out .= '<details class="am-faq-item"' . $lang . '><summary><h3>' . esc_html( $q ) . '</h3></summary><div>' . wpautop( wp_kses_post( $a ) ) . '</div></details>';
	}
	if ( '' === $out ) {
		return '';
	}
	return '<section class="am-faq" id="faq"><h2>' . esc_html( $heading ) . '</h2>' . $out . '</section>';
}

/**
 * Roman Urdu helper block (rule 7).
 */
function am_roman_urdu_html( string $html, string $heading = '' ): string {
	if ( '' === trim( $html ) ) {
		return '';
	}
	// ru_heading: the Roman Urdu query the block answers ("... ka merit kitna jata hai?"), audit G-10.
	$heading = '' !== trim( $heading ) ? am_tokens( $heading ) : 'Asaan alfaaz mein (Roman Urdu)';
	return '<section class="am-ru" lang="' . esc_attr( (string) am_site( 'helper_language', 'ur-Latn' ) ) . '"><h2>' . esc_html( $heading ) . '</h2>' . wpautop( wp_kses_post( am_tokens( $html ) ) ) . '</section>';
}

/**
 * Sources list.
 *
 * @param array<int, array{label: string, url: string, fetched?: string}> $sources
 */
function am_sources_html( array $sources ): string {
	$out = '';
	foreach ( $sources as $s ) {
		if ( empty( $s['url'] ) ) {
			continue;
		}
		$fetched = ! empty( $s['fetched'] ) ? ' <span class="am-muted">(checked ' . esc_html( (string) $s['fetched'] ) . ')</span>' : '';
		$out    .= '<li><a href="' . esc_url( (string) $s['url'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( (string) ( $s['label'] ?? $s['url'] ) ) . '</a>' . $fetched . '</li>';
	}
	return $out ? '<section class="am-sources"><h2>Sources</h2><ul>' . $out . '</ul></section>' : '';
}

/**
 * "Updated <date>" line.
 */
function am_updated_html( string $date ): string {
	$ts = strtotime( $date );
	if ( ! $ts ) {
		return '';
	}
	return '<p class="am-updated">Updated <time datetime="' . esc_attr( gmdate( 'Y-m-d', $ts ) ) . '">' . esc_html( gmdate( 'j F Y', $ts ) ) . '</time></p>';
}

/**
 * Author box → author.url (/about/ali-ahmad/; Person schema lives on every page, see inc/schema.php).
 */
function am_author_box_html(): string {
	$name = (string) am_site( 'author.name' );
	if ( '' === $name ) {
		return '';
	}
	return '<aside class="am-author" aria-label="Author"><p><strong>Written and checked by <a href="' . esc_url( (string) am_site( 'author.url', home_url( '/about/#author' ) ) ) . '" rel="author">' . esc_html( $name ) . '</a></strong>, ' . esc_html( (string) am_site( 'author.role' ) ) . ' of ' . esc_html( (string) am_site( 'brand' ) ) . '.</p><p>' . esc_html( (string) am_site( 'author.bio' ) ) . ' <a href="' . esc_url( home_url( '/editorial-policy/' ) ) . '">Editorial policy</a>.</p></aside>';
}

function am_disclaimer_html(): string {
	$text = (string) am_site( 'disclaimer' );
	if ( '' === $text ) {
		return '';
	}
	return '<aside class="am-disclaimer" aria-label="Disclaimer"><p>' . esc_html( $text ) . ' <a href="' . esc_url( home_url( '/disclaimer/' ) ) . '">Full disclaimer</a>.</p></aside>';
}

/**
 * Share row: WhatsApp (works without JS), copy link and native share (JS shows them).
 */
function am_share_html( string $url, string $title ): string {
	$wa = 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url );
	return '<div class="am-share" data-url="' . esc_attr( $url ) . '" data-title="' . esc_attr( $title ) . '"><span>Share:</span> <a class="am-btn am-btn--wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">WhatsApp</a> <button type="button" class="am-btn am-js-only" data-am-copy="' . esc_attr( $url ) . '">Copy link</button> <button type="button" class="am-btn am-js-only" data-am-share hidden>Share…</button></div>';
}

/**
 * Related links from a list of pages.json paths.
 */
function am_related_html( array $paths, string $heading = 'Related' ): string {
	$out = '';
	foreach ( $paths as $path ) {
		$page = am_find_page( (string) $path );
		if ( $page ) {
			$out .= '<li><a href="' . esc_url( home_url( $page['path'] ) ) . '">' . esc_html( am_page_label( $page ) ) . '</a></li>';
		}
	}
	return $out ? '<nav class="am-related" aria-label="' . esc_attr( $heading ) . '"><h2>' . esc_html( $heading ) . '</h2><ul>' . $out . '</ul></nav>' : '';
}

/**
 * Reserved ad slot (fixed min-height, no CLS). Prints nothing until ads.enabled is true in site.json.
 */
function am_ad_slot( string $name ): string {
	if ( ! am_site( 'ads.enabled', false ) ) {
		return '';
	}
	return '<div class="am-ad" data-am-ad="' . esc_attr( $name ) . '" aria-hidden="true"></div>';
}

/**
 * Body HTML of a pages.json entry: "content" (inline) or "content_file" (path under content/).
 */
function am_page_body( array $page ): string {
	$html = (string) ( $page['content'] ?? '' );
	if ( ! empty( $page['content_file'] ) ) {
		$file = AM_DIR . '/content/' . basename( (string) $page['content_file'] );
		if ( is_readable( $file ) ) {
			$html .= (string) file_get_contents( $file );
		}
	}
	return do_shortcode( am_tokens( $html ) );
}

/**
 * "Other <field> calculators / merit lists" under a calculator or merit-list page: links to the
 * same kind of page for every other university in the same field (internal links, ranking plan Nov 2026).
 */
function am_siblings_html( array $page ): string {
	$tpl  = (string) ( $page['template'] ?? 'default' );
	$key  = array( 'calculator' => 'calculator_path', 'meritlist' => 'meritlist_path' )[ $tpl ] ?? '';
	$path = (string) ( $page['path'] ?? '' );
	if ( '' === $key || ! function_exists( 'am_unis' ) ) {
		return '';
	}
	$group = fn( $u ) => in_array( $u['field'] ?? '', array( 'engineering', 'computing' ), true ) ? 'engineering and computing' : (string) ( $u['field'] ?? '' );
	$unis  = am_unis();
	$field = '';
	foreach ( $unis as $uni ) {
		if ( ( $uni[ $key ] ?? '' ) === $path ) {
			$field = $group( $uni );
		}
	}
	if ( '' === $field ) {
		return '';
	}
	$links = array();
	foreach ( $unis as $uni ) {
		$p = (string) ( $uni[ $key ] ?? '' );
		if ( '' !== $p && $p !== $path && $group( $uni ) === $field && am_find_page( $p ) ) {
			$links[ am_path_label( $p ) ] = '<a href="' . esc_url( home_url( $p ) ) . '">' . esc_html( am_path_label( $p ) ) . '</a>';
		}
	}
	if ( ! $links ) {
		return '';
	}
	ksort( $links, SORT_NATURAL | SORT_FLAG_CASE );
	$label = array( 'medical' => 'medical', 'general' => 'university' )[ $field ] ?? $field;
	$what  = 'calculator' === $tpl ? 'aggregate calculators' : 'merit lists';
	$hub   = 'calculator' === $tpl ? '/aggregate-calculator/' : '/merit-list/';
	return '<section class="am-sibs"><h2>Other ' . esc_html( $label ) . ' ' . esc_html( $what ) . '</h2><p>' . implode( ' · ', $links )
		. ( am_find_page( $hub ) ? ' · <a href="' . esc_url( home_url( $hub ) ) . '">All ' . esc_html( $what ) . '</a>' : '' ) . '</p></section>';
}

/**
 * Full main column for a pages.json entry (everything except header/footer). Rendered before
 * get_header() so shortcodes can register FAQ/Dataset/WebApplication nodes for <head>.
 */
function am_render_page( array $page ): string {
	$type    = (string) ( $page['type'] ?? 'content' );
	$is_home = '/' === ( $page['path'] ?? '' );
	$url     = home_url( (string) ( $page['path'] ?? '/' ) );
	$h1      = am_tokens( (string) ( $page['h1'] ?? $page['title'] ?? '' ) );
	if ( $is_home && 'home' === ( $page['template'] ?? '' ) && function_exists( 'am_home_html' ) ) {
		return am_home_html( $page )
			. am_faq_html( (array) ( $page['faq'] ?? array() ) )
			. am_roman_urdu_html( (string) ( $page['roman_urdu'] ?? '' ), (string) ( $page['ru_heading'] ?? '' ) )
			. am_disclaimer_html();
	}
	$out     = am_breadcrumb_html( $page );
	$out    .= '<h1>' . esc_html( $h1 ) . '</h1>';
	if ( ! empty( $page['subtitle'] ) ) {
		$out .= '<p class="am-lead">' . esc_html( am_tokens( (string) $page['subtitle'] ) ) . '</p>';
	}
	if ( 'trust' !== $type && ! $is_home ) {
		$out .= am_updated_html( am_page_updated( $page ) );
	}
	$out .= am_answer_html( (string) ( $page['answer'] ?? '' ), (string) ( $page['answer_source'] ?? '' ) );
	$out .= '<div class="am-body">' . am_page_body( $page ) . '</div>';
	$out .= am_siblings_html( $page );
	$out .= am_faq_html( (array) ( $page['faq'] ?? array() ) );
	$out .= am_roman_urdu_html( (string) ( $page['roman_urdu'] ?? '' ), (string) ( $page['ru_heading'] ?? '' ) );
	$out .= am_sources_html( (array) ( $page['sources'] ?? array() ) );
	if ( 'trust' === $type ) {
		$out .= am_updated_html( am_page_updated( $page ) );
	} else {
		$out .= am_share_html( $url, $h1 );
		$out .= am_author_box_html();
		$out .= am_disclaimer_html();
	}
	$out .= am_related_html( (array) ( $page['related'] ?? array() ) );
	return $out;
}
