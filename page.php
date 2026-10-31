<?php
/**
 * Default page template. The theme prints the single H1; page content is shortcodes.
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'am-wrap' ); ?>>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;
get_footer();
