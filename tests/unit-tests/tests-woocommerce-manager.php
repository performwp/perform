<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\WooCommerce\WooManager;

final class Tests_WooCommerce_Manager extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'disable_woocommerce_assets'             => true,
				'disable_woocommerce_cart_fragmentation' => true,
				'disable_woocommerce_status'             => true,
				'disable_woocommerce_widgets'            => true,
			],
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_options'] );
	}

	public function test_should_not_load_when_woocommerce_is_inactive_even_if_settings_are_enabled() {
		$module = new WooManager();

		$this->assertFalse( $module->should_load() );
	}

	public function test_callbacks_return_when_woocommerce_helpers_are_unavailable() {
		$module = new WooManager();

		$module->disable_assets();
		$module->disable_cart_fragmentation();
		$module->disable_widgets();

		$this->assertTrue( true );
	}
}
