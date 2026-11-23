<?php
/**
 * Google Tag Manager (owner request 2026-11-23). Container ID lives in data/site.json `gtm_id`.
 *
 * Speed: the dataLayer and gtm.start time are set inline in <head>, but gtm.js itself is only
 * requested after the window load event, when the browser is idle, so it never competes with the
 * page's own CSS/JS or delays first paint. /embed/<slug>/ widgets have their own template without
 * wp_head(), so GTM never runs on other people's sites.
 */

defined( 'ABSPATH' ) || exit;

/** Valid container ID from site.json, or '' when none is set. */
function am_gtm_id(): string {
	$id = (string) am_site( 'gtm_id', '' );
	return preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $id ) ? $id : '';
}

add_action(
	'wp_head',
	function () {
		$id = am_gtm_id();
		if ( '' === $id ) {
			return;
		}
		$src = 'https://www.googletagmanager.com/gtm.js?id=' . $id;
		echo '<script>window.dataLayer=window.dataLayer||[];dataLayer.push({"gtm.start":Date.now(),event:"gtm.js"});'
			. '(function(){function l(){var s=document.createElement("script");s.async=true;s.src=' . wp_json_encode( $src, JSON_UNESCAPED_SLASHES ) . ';document.head.appendChild(s)}'
			. 'function i(){"requestIdleCallback" in window?requestIdleCallback(l,{timeout:2000}):setTimeout(l,1)}'
			. '"complete"===document.readyState?i():addEventListener("load",i)})()</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- ID validated by am_gtm_id(), URL JSON-encoded
	},
	1
);

add_action(
	'wp_body_open',
	function () {
		$id = am_gtm_id();
		if ( '' === $id ) {
			return;
		}
		echo '<noscript><iframe src="' . esc_url( 'https://www.googletagmanager.com/ns.html?id=' . $id ) . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>' . "\n";
	}
);
