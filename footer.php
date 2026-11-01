<?php defined( 'ABSPATH' ) || exit; ?>
</main>
<footer class="am-footer">
	<div class="am-wrap am-fgrid">
		<div class="am-fabout">
			<?php echo am_brand(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in am_brand() ?>
			<p><?php echo esc_html( (string) am_site( 'tagline' ) ); ?>. Every figure links to its official source.</p>
			<p><a href="<?php echo esc_url( 'mailto:' . am_site( 'contact.email' ) ); ?>"><?php echo esc_html( (string) am_site( 'contact.email' ) ); ?></a></p>
		</div>
		<?php echo am_nav( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in am_nav() ?>
	</div>
	<div class="am-fbar">
		<div class="am-wrap">
			<p><?php echo esc_html( (string) am_site( 'disclaimer' ) ); ?></p>
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( am_site( 'brand', 'Apna Merit' ) ); ?>. Data licensed <a href="<?php echo esc_url( (string) am_site( 'data_license.url' ) ); ?>" rel="license noopener"><?php echo esc_html( (string) am_site( 'data_license.name' ) ); ?></a>: <?php echo esc_html( (string) am_site( 'data_license.attribution' ) ); ?>.</p>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
