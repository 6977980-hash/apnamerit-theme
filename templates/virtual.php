<?php
/**
 * Renders a repo page from data/pages.json (see inc/router.php). Not a WordPress page template.
 * The body renders first so shortcodes can register JSON-LD nodes before wp_head() runs.
 */

defined( 'ABSPATH' ) || exit;

$am_page     = am_virtual_page();
$am_template = in_array( $am_page['template'] ?? 'default', AM_PAGE_TEMPLATES, true ) ? $am_page['template'] : 'default';
$am_main     = am_render_page( $am_page );

get_header();
?>
<article class="am-wrap am-page am-page--<?php echo esc_attr( $am_template ); ?> am-type--<?php echo esc_attr( (string) ( $am_page['type'] ?? 'content' ) ); ?>">
	<?php echo $am_main; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts + trusted repo content ?>
</article>
<?php
get_footer();
