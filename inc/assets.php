<?php
/**
 * One CSS file (critical part inlined) and one deferred vanilla JS file.
 */

defined( 'ABSPATH' ) || exit;

/** Max bytes of CSS inlined in <head>: 20 KB raw (about 4.5 KB gzipped, well inside the 14 KB first round trip). */
const AM_CRITICAL_CSS_MAX = 20480;

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

// Site icon from the theme (no wp-admin Site Icon): SVG + PNG for Google results and home screens.
add_action(
	'wp_head',
	function () {
		$v = AM_VERSION;
		echo '<link rel="icon" href="' . esc_url( AM_URI . '/assets/icons/icon.svg?ver=' . $v ) . '" type="image/svg+xml">' . "\n";
		echo '<link rel="icon" href="' . esc_url( AM_URI . '/assets/icons/icon-48.png?ver=' . $v ) . '" sizes="48x48" type="image/png">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( AM_URI . '/assets/icons/icon-180.png?ver=' . $v ) . '">' . "\n";
	},
	2
);

// /favicon.ico (requested by browsers and crawlers whatever the <link> says) → the PNG icon.
add_action(
	'do_faviconico',
	function () {
		wp_redirect( AM_URI . '/assets/icons/icon-48.png', 301 );
		exit;
	},
	1
);
add_action(
	'template_redirect',
	function () {
		if ( '/favicon.ico' === am_request_path() ) {
			wp_redirect( AM_URI . '/assets/icons/icon-48.png', 301 );
			exit;
		}
	},
	-3
);

// The server answers /favicon.ico itself (static 404) before WordPress runs, so copy the icon
// into the WordPress root after each deploy. Never overwrite an icon someone else put there.
add_action(
	'am_deployed',
	function () {
		$src = AM_DIR . '/assets/icons/favicon.ico';
		$dst = ABSPATH . 'favicon.ico';
		if ( is_readable( $src ) && ( ! file_exists( $dst ) || get_option( 'am_favicon_md5' ) === md5_file( $dst ) ) && @copy( $src, $dst ) ) {
			update_option( 'am_favicon_md5', md5_file( $dst ), false );
		}
	}
);
