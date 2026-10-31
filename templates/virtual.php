<?php
/**
 * Renders a repo page from data/pages.json (see inc/router.php). Not a WordPress page template.
 */

defined( 'ABSPATH' ) || exit;

$am_page     = am_virtual_page();
$am_template = in_array( $am_page['template'] ?? 'default', AM_PAGE_TEMPLATES, true ) ? $am_page['template'] : 'default';
$am_updated  = am_page_updated( $am_page );

get_header();
?>
<article class="am-wrap am-page am-page--<?php echo esc_attr( $am_template ); ?>">
	<h1><?php echo esc_html( am_tokens( (string) ( $am_page['h1'] ?? $am_page['title'] ?? '' ) ) ); ?></h1>
	<?php echo am_render_page_content( $am_page ); // phpcs:ignore WordPress.Security.EscapeOutput -- trusted repo content ?>
	<p class="am-updated">Updated <time datetime="<?php echo esc_attr( $am_updated ); ?>"><?php echo esc_html( gmdate( 'j F Y', strtotime( $am_updated ) ) ); ?></time></p>
</article>
<?php
get_footer();
