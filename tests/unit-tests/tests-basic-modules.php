<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Basic\DisableSelfPingbacks;

final class Tests_Basic_Modules extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [
			'home' => 'https://example.com',
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_options'] );
	}

	public function test_disable_self_pingbacks_removes_home_links_by_reference() {
		$links = [
			'https://example.com/internal-post',
			'https://external.example/post',
		];

		$module = new DisableSelfPingbacks();
		$module->disable_self_pingbacks( $links );

		$this->assertSame( [ 1 => 'https://external.example/post' ], $links );
	}
}
