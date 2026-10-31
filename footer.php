<?php defined( 'ABSPATH' ) || exit; ?>
</main>
<footer class="am-footer">
	<div class="am-wrap">
		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => 'nav',
					'container_class' => 'am-footer-nav',
					'depth'          => 1,
				)
			);
		}
		?>
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( am_site( 'brand', 'Apna Merit' ) ); ?>. <?php echo esc_html( am_site( 'tagline' ) ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
