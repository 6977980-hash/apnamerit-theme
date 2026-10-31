<?php defined( 'ABSPATH' ) || exit; ?>
</main>
<footer class="am-footer">
	<div class="am-wrap">
		<?php echo am_nav( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in am_nav() ?>
		<p><?php echo esc_html( (string) am_site( 'disclaimer' ) ); ?></p>
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( am_site( 'brand', 'Apna Merit' ) ); ?>. Data licensed <a href="<?php echo esc_url( (string) am_site( 'data_license.url' ) ); ?>" rel="license noopener"><?php echo esc_html( (string) am_site( 'data_license.name' ) ); ?></a>: <?php echo esc_html( (string) am_site( 'data_license.attribution' ) ); ?>.</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
