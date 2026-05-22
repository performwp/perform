<?php

use PHPUnit\Framework\TestCase;
use Perform\Includes\Actions;

final class Tests_Actions extends TestCase {
	protected function setUp(): void {
		$_GET['perform']                          = '1';
		$GLOBALS['perform_test_current_user_can'] = [
			'manage_options' => false,
		];
		$GLOBALS['perform_test_enqueued_scripts'] = [];
		$GLOBALS['perform_test_enqueued_styles']  = [];
	}

	protected function tearDown(): void {
		unset(
			$_GET['perform'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_enqueued_scripts'],
			$GLOBALS['perform_test_enqueued_styles']
		);
	}

	public function test_assets_manager_assets_require_manage_options() {
		$actions = new Actions();

		$actions->enqueue_scripts();
		$actions->enqueue_styles();

		$this->assertSame( [], $GLOBALS['perform_test_enqueued_scripts'] );
		$this->assertSame( [], $GLOBALS['perform_test_enqueued_styles'] );
	}
}
