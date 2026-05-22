<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Cache\PageCache;

final class Tests_Page_Cache extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_transients'] = [
			'perform_cache_lock_test' => 'expected-token',
		];
		unset( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_transients'], $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] );
	}

	public function test_internal_regeneration_requires_matching_lock_token() {
		$page_cache = new PageCache();

		$lock_key = new ReflectionProperty( $page_cache, 'lock_key' );
		$lock_key->setAccessible( true );
		$lock_key->setValue( $page_cache, 'perform_cache_lock_test' );

		$is_internal_regen_request = new ReflectionMethod( $page_cache, 'is_internal_regen_request' );
		$is_internal_regen_request->setAccessible( true );

		$_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] = '1';
		$this->assertFalse( $is_internal_regen_request->invoke( $page_cache ) );

		$_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] = 'expected-token';
		$this->assertTrue( $is_internal_regen_request->invoke( $page_cache ) );
	}
}
