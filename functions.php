<?php
/**
 * Apna Merit theme bootstrap.
 *
 * Every theme change goes through git (Hostinger Git deploy). Never edit in wp-admin or FTP.
 */

defined( 'ABSPATH' ) || exit;

define( 'AM_VERSION', '0.17.0' );
define( 'AM_DIR', get_template_directory() );
define( 'AM_URI', get_template_directory_uri() );

require_once AM_DIR . '/inc/data.php';
require_once AM_DIR . '/inc/setup.php';
require_once AM_DIR . '/inc/cleanup.php';
require_once AM_DIR . '/inc/assets.php';
require_once AM_DIR . '/inc/deploy.php';
require_once AM_DIR . '/inc/router.php';
require_once AM_DIR . '/inc/seo.php';
require_once AM_DIR . '/inc/template-tags.php';
require_once AM_DIR . '/inc/schema.php';
require_once AM_DIR . '/inc/shortcodes.php';
require_once AM_DIR . '/inc/growth.php';
require_once AM_DIR . '/inc/aeo.php';
require_once AM_DIR . '/inc/indexnow.php';
require_once AM_DIR . '/inc/home.php';
