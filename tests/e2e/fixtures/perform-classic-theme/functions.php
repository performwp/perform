<?php
/**
 * Minimal classic-theme setup for disposable end-to-end tests.
 */

add_action(
	'after_setup_theme',
	static function () {
		register_nav_menus( [ 'primary' => 'Primary' ] );
	}
);
