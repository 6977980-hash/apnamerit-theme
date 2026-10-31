<?php
/**
 * WordPress cleanup: master plan section 0.4 "Disable" list.
 */

defined( 'ABSPATH' ) || exit;

// Emojis.
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}
);

// Embeds (oEmbed discovery + wp-embed script).
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover', '__return_false' );
	},
	20
);
add_action( 'wp_footer', fn() => wp_dequeue_script( 'wp-embed' ) );

// Head noise.
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'feed_links_extra', 3 );

// XML-RPC.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

// Comments: off everywhere.
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 10 );
add_action(
	'init',
	function () {
		foreach ( get_post_types() as $type ) {
			if ( post_type_supports( $type, 'comments' ) ) {
				remove_post_type_support( $type, 'comments' );
				remove_post_type_support( $type, 'trackbacks' );
			}
		}
	},
	100
);
add_action( 'admin_menu', fn() => remove_menu_page( 'edit-comments.php' ) );
add_action(
	'wp_before_admin_bar_render',
	function () {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu( 'comments' );
	}
);

// REST user enumeration (keep it for logged-in editors).
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		return $endpoints;
	}
);

// Author, date, tag and attachment archives -> 301 to a real page. ?author=N enumeration too.
add_action(
	'template_redirect',
	function () {
		if ( is_author() || isset( $_GET['author'] ) ) {
			wp_safe_redirect( home_url( '/about/' ), 301 );
			exit;
		}
		if ( is_date() || is_tag() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		if ( is_attachment() ) {
			$parent = wp_get_post_parent_id( get_queried_object_id() );
			wp_safe_redirect( $parent ? get_permalink( $parent ) : home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

// Block-library CSS on the front end (Gutenberg only used for guide posts, styled by am.css).
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_singular( 'post' ) ) {
			return;
		}
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	},
	100
);

// No jQuery on the front end.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_admin() && ! is_user_logged_in() ) {
			wp_deregister_script( 'jquery' );
		}
	},
	100
);
