<?php
/**
 * Homepage: the "/" entry of data/pages.json (h1 + content). Falls back to a placeholder.
 */

defined( 'ABSPATH' ) || exit;
get_header();
$am_page = am_find_page( '/' );
$am_h1   = am_tokens( (string) ( $am_page['h1'] ?? 'Aggregate Calculator for Pakistani Universities ({season_year})' ) );
?>
<div class="am-wrap am-hero">
	<h1><?php echo esc_html( $am_h1 ); ?></h1>
	<?php
	if ( ! empty( $am_page['content'] ) ) {
		echo am_render_page_content( $am_page ); // phpcs:ignore WordPress.Security.EscapeOutput -- trusted repo content
	}
	?>
</div>
<?php
get_footer();
