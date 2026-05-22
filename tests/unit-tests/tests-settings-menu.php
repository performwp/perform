<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\Menu;

final class Tests_Settings_Menu extends TestCase {
	public function test_multiline_settings_preserve_line_boundaries() {
		$menu   = new Menu();
		$method = new ReflectionMethod( $menu, 'normalize_multiline_setting' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'https://fonts.example.com',
				'https://cdn.example.com',
			],
			$method->invoke( $menu, "https://fonts.example.com\nhttps://cdn.example.com" )
		);
	}

	public function test_existing_multiline_arrays_keep_their_storage_shape() {
		$menu   = new Menu();
		$method = new ReflectionMethod( $menu, 'normalize_multiline_setting' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'https://fonts.example.com',
				'https://cdn.example.com',
			],
			$method->invoke( $menu, [ 'https://fonts.example.com', '', 'https://cdn.example.com' ] )
		);
	}
}
