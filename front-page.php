<?php
/**
 * Homepage: the "/" entry of data/pages.json, rendered like every other repo page.
 */

defined( 'ABSPATH' ) || exit;

$am_page = am_find_page( '/' );
if ( ! $am_page ) {
	$am_page = array(
		'path' => '/',
		'h1'   => 'Aggregate Calculator for Pakistani Universities ({season_year})',
	);
}
$am_main = am_render_page( $am_page );

get_header();
?>
<article class="am-wrap am-page am-page--home">
	<?php echo $am_main; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts + trusted repo content ?>
</article>
<?php
get_footer();
