<?php
/**
 * One CSS file (critical part inlined) and one deferred vanilla JS file.
 */

defined( 'ABSPATH' ) || exit;

/** Max bytes of CSS inlined in <head> (plan: critical CSS <= 14 KB). */
const AM_CRITICAL_CSS_MAX = 14336;

add_action(
	'wp_head',
	function () {
		$file = AM_DIR . '/assets/css/am.css';
		if ( is_readable( $file ) && filesize( $file ) <= AM_CRITICAL_CSS_MAX ) {
			echo '<style id="am-css">' . file_get_contents( $file ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	5
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$css = AM_DIR . '/assets/css/am.css';
		// Once am.css grows past the inline budget, load it as a normal file instead.
		if ( is_readable( $css ) && filesize( $css ) > AM_CRITICAL_CSS_MAX ) {
			wp_enqueue_style( 'am', AM_URI . '/assets/css/am.css', array(), (string) filemtime( $css ) );
		}
		$js = AM_DIR . '/assets/js/am.js';
		if ( is_readable( $js ) ) {
			wp_enqueue_script(
				'am',
				AM_URI . '/assets/js/am.js',
				array(),
				(string) filemtime( $js ),
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}
	}
);
