<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="am-wrap">
	<h1>Page not found</h1>
	<p>This page does not exist. Try the <a href="<?php echo esc_url( home_url( '/' ) ); ?>">aggregate calculator</a> on the homepage.</p>
</div>
<?php
get_footer();
