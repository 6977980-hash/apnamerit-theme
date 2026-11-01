<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="am-wrap">
	<h1>Page not found</h1>
	<p>This page does not exist or has moved. Search the site or pick one of the pages below.</p>
	<?php
	echo am_search_form(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
	echo am_popular_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
	?>
</div>
<?php
get_footer();
