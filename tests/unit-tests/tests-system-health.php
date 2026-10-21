<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\SystemHealth;

final class Tests_System_Health extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_filters']          = [];
		$GLOBALS['perform_test_is_multisite']     = false;
		$GLOBALS['perform_test_ext_object_cache'] = false;
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_filters'],
			$GLOBALS['perform_test_is_multisite'],
			$GLOBALS['perform_test_ext_object_cache']
		);
	}

	public function test_filtered_signals_are_bounded_normalized_and_summarized() {
		$GLOBALS['perform_test_filters']['perform_system_health_signals'] = [
			[
				'id'             => 'php-version',
				'label'          => 'PHP version',
				'value'          => '8.3.0',
				'status'         => 'good',
				'recommendation' => 'Actively tested.',
			],
			[
				'id'             => 'memory-limit',
				'label'          => 'Memory',
				'value'          => '64M',
				'status'         => 'action-recommended',
				'recommendation' => 'Review pressure first.',
			],
			[
				'id'             => 'unknown-state',
				'label'          => 'Unknown state',
				'value'          => 'Value',
				'status'         => 'invented',
				'recommendation' => 'Unavailable.',
			],
			'invalid',
		];

		$health = SystemHealth::get_data();

		$this->assertSame( 'site', $health['scope'] );
		$this->assertFalse( $health['isMultisite'] );
		$this->assertCount( 3, $health['signals'] );
		$this->assertSame( 1, $health['summary']['good'] );
		$this->assertSame( 1, $health['summary']['actionRecommended'] );
		$this->assertSame( 1, $health['summary']['unavailable'] );
		$this->assertSame( 'unavailable', $health['signals'][2]['status'] );
	}

	public function test_default_payload_excludes_paths_and_private_runtime_values() {
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/2.0';
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.27.0 private-build';

		$health = SystemHealth::get_data();
		$json   = wp_json_encode( $health );
		$ids    = array_column( $health['signals'], 'id' );
		$http   = $health['signals'][ array_search( 'http-server', $ids, true ) ];

		$this->assertContains( 'php-version', $ids );
		$this->assertContains( 'memory-limit', $ids );
		$this->assertContains( 'object-cache', $ids );
		$this->assertContains( 'database-version', $ids );
		$this->assertContains( 'http-server', $ids );
		$this->assertContains( 'debug-log', $ids );
		$this->assertStringContainsString( 'HTTP/2.0', $http['value'] );
		$this->assertStringContainsString( 'nginx', $json );
		$this->assertStringNotContainsString( 'private-build', $json );
		$this->assertStringNotContainsString( '/var/', $json );
		$this->assertStringNotContainsString( 'password', strtolower( $json ) );
	}

	public function test_multisite_scope_is_current_site_only() {
		$GLOBALS['perform_test_is_multisite'] = true;

		$health = SystemHealth::get_data();

		$this->assertTrue( $health['isMultisite'] );
		$this->assertSame( 'site', $health['scope'] );
	}
}
