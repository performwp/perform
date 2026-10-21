<?php
/**
 * Minimal classic-theme frontend used only by disposable end-to-end tests.
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php
wp_nav_menu(
	[
		'theme_location' => 'primary',
		'container'      => 'nav',
		'container_id'   => 'perform-test-navigation',
		'fallback_cb'    => false,
	]
);
wp_footer();
?>
</body>
</html>
