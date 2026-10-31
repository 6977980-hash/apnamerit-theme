<?php
/**
 * Template Name: Merit list
 *
 * Merit-list page (master plan 4.2). Page content holds [am_answer], [am_merit_table], [am_faq].
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'am-wrap am-merit-page' ); ?>>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;
get_footer();
