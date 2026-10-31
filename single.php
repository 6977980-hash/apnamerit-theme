<?php
/**
 * Guide posts (Gutenberg allowed here only).
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'am-wrap am-guide' ); ?>>
		<h1><?php the_title(); ?></h1>
		<p class="am-updated">Updated <time datetime="<?php echo esc_attr( get_the_modified_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'j F Y' ) ); ?></time></p>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;
get_footer();
