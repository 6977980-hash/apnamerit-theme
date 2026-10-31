<?php
/**
 * Template Name: Calculator
 *
 * Calculator page (master plan 4.1). Page content holds [am_answer], [am_calculator], [am_faq].
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'am-wrap am-calc-page' ); ?>>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;
get_footer();
