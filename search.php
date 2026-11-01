<?php
/**
 * Search results from data/pages.json (no WordPress posts on this site). noindex via inc/seo.php.
 */
defined( 'ABSPATH' ) || exit;
$am_q    = trim( (string) get_search_query( false ) );
$am_hits = am_search_pages( $am_q );
get_header();
?>
<div class="am-wrap">
	<h1><?php echo '' === $am_q ? 'Search' : esc_html( 'Search results for "' . $am_q . '"' ); ?></h1>
	<?php echo am_search_form( $am_q ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
	<?php if ( $am_hits ) : ?>
		<ul class="am-plist">
			<?php foreach ( $am_hits as $am_page ) : ?>
				<li><a href="<?php echo esc_url( home_url( (string) $am_page['path'] ) ); ?>"><?php echo esc_html( am_tokens( (string) ( $am_page['h1'] ?? $am_page['title'] ?? '' ) ) ); ?></a><br><span class="am-muted"><?php echo esc_html( am_tokens( (string) ( $am_page['description'] ?? '' ) ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( '' !== $am_q ) : ?>
		<p>Nothing matched. Try a university (UHS, UET, PU), a college (KEMU, Nishtar) or a tool (calculator, merit list).</p>
	<?php endif; ?>
	<?php echo am_popular_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
</div>
<?php
get_footer();
