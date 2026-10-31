<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className+=' am-js'</script>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="am-skip" href="#main">Skip to content</a>
<header class="am-header">
	<div class="am-wrap">
		<a class="am-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( am_site( 'brand', 'Apna Merit' ) ); ?></a>
		<?php echo am_nav( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in am_nav() ?>
	</div>
</header>
<main id="main" class="am-main">
