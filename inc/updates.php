<?php
/**
 * Alerts without a backend (ideas 2026-11-01, Tier 1 idea 5):
 * - data/updates.json         dated log of every new list, page and date change (newest first on /updates/).
 * - /updates/feed/            RSS 2.0 of that log (also <link rel="alternate"> on every page).
 * - /calendar/<slug>.ics      the official season dates of data/calendar.json seasons.<slug> as an all-day calendar.
 * - [am_updates]              the log + the "get alerts" box; am_alerts_html() is reused by [am_season].
 * - site.json social.whatsapp_channel: when set, a "Follow on WhatsApp" button (the owner posts each entry there).
 */

defined( 'ABSPATH' ) || exit;

/** Entries of data/updates.json, newest first. Each: date (YYYY-MM-DD), title, text, url (site path or https). */
function am_updates(): array {
	$items = array_filter( (array) ( am_data( 'updates' )['updates'] ?? array() ), fn( $u ) => is_array( $u ) && ! empty( $u['date'] ) && ! empty( $u['title'] ) );
	usort( $items, fn( $a, $b ) => strcmp( (string) $b['date'], (string) $a['date'] ) );
	return array_values( $items );
}

/** Absolute URL of an update: site paths become home_url(). */
function am_update_url( array $u ): string {
	$url = (string) ( $u['url'] ?? '' );
	return '' === $url ? home_url( '/updates/' ) : ( str_starts_with( $url, '/' ) ? home_url( $url ) : $url );
}

/** Season slugs that have an .ics calendar (every data/calendar.json seasons.<slug> with steps). */
function am_ics_seasons(): array {
	return array_filter( (array) ( am_data( 'calendar' )['seasons'] ?? array() ), fn( $s ) => is_array( $s ) && ! empty( $s['steps'] ) && ! empty( $s['source_url'] ) );
}

/**
 * "Get alerts" box: WhatsApp Channel (if set), RSS, calendar files. $slug limits the calendar links to one season.
 */
function am_alerts_html( string $slug = '' ): string {
	$links = array();
	$wa    = (string) am_site( 'social.whatsapp_channel' );
	if ( '' !== $wa ) {
		$links[] = '<a class="am-btn am-btn--wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">Follow on WhatsApp</a>';
	}
	foreach ( am_ics_seasons() as $s_slug => $s ) {
		if ( '' !== $slug && $slug !== $s_slug ) {
			continue;
		}
		$links[] = '<a class="am-btn" href="' . esc_url( home_url( '/calendar/' . $s_slug . '.ics' ) ) . '" download>Add ' . esc_html( (string) ( am_uni( (string) $s_slug )['short'] ?? strtoupper( (string) $s_slug ) ) . ' ' . (string) $s['session'] ) . ' dates to calendar</a>';
	}
	if ( am_find_page( '/updates/' ) ) {
		$links[] = '<a class="am-btn" href="' . esc_url( home_url( '/updates/feed/' ) ) . '">RSS feed</a>';
	}
	if ( ! $links ) {
		return '';
	}
	$note = '' !== $wa ? 'Every new merit list and date change is posted on the WhatsApp Channel (no number is shared, you only follow).' : 'The calendar file adds the official dates to your phone; the feed works in any RSS reader.';
	return '<div class="am-answer am-alerts"><p><strong>Get alerts:</strong> ' . implode( ' ', $links ) . '</p><p class="am-muted">' . esc_html( $note ) . ( am_find_page( '/updates/' ) && '/updates/' !== am_request_path() ? ' See <a href="' . esc_url( home_url( '/updates/' ) ) . '">all updates</a>.' : '' ) . '</p></div>';
}

// ------------------------------------------------------------------ [am_updates]
function am_sc_updates(): string {
	$items = am_updates();
	if ( ! $items ) {
		return am_stub( 'am_updates', array() );
	}
	$out   = am_alerts_html();
	$month = '';
	foreach ( $items as $u ) {
		$ts = (int) strtotime( (string) $u['date'] . ' 12:00 UTC' );
		$m  = gmdate( 'F Y', $ts );
		if ( $m !== $month ) {
			$out  .= ( '' !== $month ? '</ul>' : '' ) . '<h2>' . esc_html( $m ) . '</h2><ul class="am-updates">';
			$month = $m;
		}
		$url  = am_update_url( $u );
		$out .= '<li><time datetime="' . esc_attr( (string) $u['date'] ) . '">' . esc_html( gmdate( 'j M', $ts ) ) . '</time> <a href="' . esc_url( $url ) . '">' . esc_html( (string) $u['title'] ) . '</a>'
			. ( ! empty( $u['text'] ) ? ' <span class="am-muted">' . esc_html( (string) $u['text'] ) . '</span>' : '' ) . '</li>';
	}
	return $out . '</ul>';
}

// ------------------------------------------------------------------ feed + calendar endpoints
add_action(
	'template_redirect',
	function () {
		$path = am_request_path();
		// One feed URL (audit CI-12): the unslashed form and the site feed /feed/ redirect to it (CI-09).
		if ( '/updates/feed' === $path || '/feed/' === $path || '/feed' === $path ) {
			wp_safe_redirect( home_url( '/updates/feed/' ), 301 );
			exit;
		}
		if ( '/updates/feed/' === $path ) {
			am_serve_feed();
		}
		if ( preg_match( '#^/calendar/([a-z0-9-]+)\.ics$#', $path, $m ) && isset( am_ics_seasons()[ $m[1] ] ) ) {
			am_serve_ics( $m[1] );
		}
	},
	-1 // before cleanup.php turns every WordPress feed URL (…/feed/) into a 404.
);

/** RSS 2.0 of data/updates.json. */
function am_serve_feed(): void {
	$items = am_updates();
	if ( ! $items || ! am_find_page( '/updates/' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/rss+xml; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=0, s-maxage=300' );
	$brand = (string) am_site( 'brand' );
	$x     = fn( string $s ) => htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	$out   = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
		. '<title>' . $x( $brand . ' updates' ) . '</title><link>' . $x( home_url( '/updates/' ) ) . '</link>'
		. '<description>' . $x( 'New merit lists, closing merits and admission dates for Pakistani universities, from official sources.' ) . '</description>'
		. '<language>en-pk</language><atom:link href="' . $x( home_url( '/updates/feed/' ) ) . '" rel="self" type="application/rss+xml"/>'
		. '<lastBuildDate>' . gmdate( 'D, d M Y 12:00:00 +0000', (int) strtotime( (string) $items[0]['date'] ) ) . '</lastBuildDate>';
	foreach ( array_slice( $items, 0, 50 ) as $u ) {
		$url  = am_update_url( $u );
		$out .= '<item><title>' . $x( (string) $u['title'] ) . '</title><link>' . $x( $url ) . '</link>'
			. '<guid isPermaLink="false">' . $x( (string) $u['date'] . ':' . $url ) . '</guid>'
			. '<pubDate>' . gmdate( 'D, d M Y 12:00:00 +0000', (int) strtotime( (string) $u['date'] ) ) . '</pubDate>'
			. '<description>' . $x( (string) ( $u['text'] ?? $u['title'] ) ) . '</description></item>';
	}
	echo $out . '</channel></rss>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above with htmlspecialchars.
	exit;
}

/** iCalendar of one season: an all-day event per step and program (ranges become multi-day events). */
function am_serve_ics( string $slug ): void {
	$s = (array) am_ics_seasons()[ $slug ];
	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $slug . '-' . preg_replace( '/[^0-9-]/', '', (string) $s['session'] ) . '.ics"' );
	header( 'Cache-Control: public, max-age=0, s-maxage=300' );
	$short = (string) ( am_uni( $slug )['short'] ?? strtoupper( $slug ) );
	$page  = ! empty( $s['page'] ) ? home_url( (string) $s['page'] ) : home_url( '/updates/' );
	$esc   = fn( string $t ) => str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\;', '\,', '\n' ), $t );
	$stamp = gmdate( 'Ymd\THis\Z', (int) strtotime( (string) ( $s['checked'] ?? 'now' ) ) );
	$lines = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $esc( (string) am_site( 'brand' ) ) . '//' . $esc( $short . ' ' . (string) $s['session'] ) . '//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . $esc( $short . ' admissions ' . (string) $s['session'] ) );
	foreach ( (array) $s['steps'] as $i => $st ) {
		foreach ( (array) ( $s['columns'] ?? array() ) as $col ) {
			$d = (string) ( $st[ $col ] ?? '' );
			if ( '' === $d ) {
				continue;
			}
			$p       = explode( '/', $d );
			$start   = str_replace( '-', '', $p[0] );
			$end     = gmdate( 'Ymd', (int) strtotime( ( $p[1] ?? $p[0] ) . ' 12:00 UTC' ) + 86400 );
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $slug . '-' . (string) $s['session'] . '-' . $i . '-' . sanitize_title( (string) $col ) . '@' . (string) am_site( 'domain' );
			$lines[] = 'DTSTAMP:' . $stamp;
			$lines[] = 'DTSTART;VALUE=DATE:' . $start;
			$lines[] = 'DTEND;VALUE=DATE:' . $end;
			$lines[] = 'SUMMARY:' . $esc( $short . ' ' . (string) $col . ': ' . (string) $st['step'] );
			$lines[] = 'DESCRIPTION:' . $esc( 'Official ' . $short . ' schedule, session ' . (string) $s['session'] . '. Source: ' . (string) $s['source_url'] . ' Merit lists and what to do next: ' . $page );
			$lines[] = 'URL:' . $esc( $page );
			$lines[] = 'END:VEVENT';
		}
	}
	$lines[] = 'END:VCALENDAR';
	// RFC 5545: CRLF line ends, lines folded at 75 octets.
	$out = '';
	foreach ( $lines as $l ) {
		// mb_str_split never cuts a UTF-8 character in two (audit TS-14); 73 bytes is a safe line for 1-3 byte characters.
		$parts = array();
		$cur   = '';
		foreach ( mb_str_split( $l, 1, 'UTF-8' ) as $ch ) {
			if ( strlen( $cur ) + strlen( $ch ) > 73 ) {
				$parts[] = $cur;
				$cur     = '';
			}
			$cur .= $ch;
		}
		$parts[] = $cur;
		$out    .= implode( "\r\n ", $parts ) . "\r\n";
	}
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput -- text/calendar, escaped per RFC 5545 above.
	exit;
}

// Feed autodiscovery (one tag; cleanup.php removes the WordPress post/comment feed links).
add_action(
	'wp_head',
	function () {
		if ( am_find_page( '/updates/' ) && am_updates() ) {
			echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr( (string) am_site( 'brand' ) . ' updates' ) . '" href="' . esc_url( home_url( '/updates/feed/' ) ) . '">' . "\n";
		}
	},
	2
);

add_action( 'init', fn() => add_shortcode( 'am_updates', 'am_sc_updates' ) );
