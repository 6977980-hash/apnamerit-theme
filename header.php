<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="am-skip" href="#main">Skip to content</a>
<header class="am-header">
	<div class="am-wrap">
		<a class="am-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( am_site( 'brand', 'Apna Merit' ) ); ?></a>
		<?php
		if ( has_nav_menu( 'primary' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => 'nav',
					'container_class' => 'am-nav',
					'depth'          => 1,
				)
			);
		}
		?>
	</div>
</header>
<main id="main" class="am-main">
