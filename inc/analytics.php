<?php
/**
 * Google Tag Manager (owner request 2026-11-23). Container ID lives in data/site.json `gtm_id`.
 *
 * Speed: the dataLayer and gtm.start time are set inline in <head>, but gtm.js itself is only
 * requested on the first interaction or 4 s after the load event, so it never competes with the
 * page's own CSS/JS, first paint or the first tap. /embed/<slug>/ widgets have their own template without
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
		// Load gtm.js on the first scroll/tap/key/mouse move, or 4 s after the load event, whichever
		// comes first: real visitors are counted, but the ~300 ms of GTM + GA4 script work stays out of
		// first paint and first input (Lighthouse TBT 230-330 ms when loaded right after load).
		echo '<script>window.dataLayer=window.dataLayer||[];dataLayer.push({"gtm.start":Date.now(),event:"gtm.js"});'
			. '(function(){var d=0,e=["scroll","pointerdown","keydown","mousemove","touchstart"];function l(){if(d)return;d=1;e.forEach(function(n){removeEventListener(n,g,{passive:true})});'
			. 'var s=document.createElement("script");s.async=true;s.src=' . wp_json_encode( $src, JSON_UNESCAPED_SLASHES ) . ';document.head.appendChild(s)}'
			. 'function g(){setTimeout(l,50)}e.forEach(function(n){addEventListener(n,g,{passive:true})});'
			. 'function t(){setTimeout(l,4000)}"complete"===document.readyState?t():addEventListener("load",t)})()</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- ID validated by am_gtm_id(), URL JSON-encoded
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
