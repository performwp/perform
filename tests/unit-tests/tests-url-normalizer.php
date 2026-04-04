<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Cache\UrlNormalizer;

final class Tests_Url_Normalizer extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'cache_separate_query_params' => 'lang',
			],
		];
	}

	public function test_normalize_ignores_tracking_and_non_whitelisted_params() {
		$normalizer = new UrlNormalizer();
		$url = $normalizer->normalize( 'https://example.com/blog/?utm_source=newsletter&fbclid=123&lang=en&page=2' );

		$this->assertSame( 'https://example.com/blog/?lang=en', $url );
	}

	public function test_key_for_url_is_stable() {
		$normalizer = new UrlNormalizer();
		$key_a = $normalizer->key_for_url( 'https://example.com/' );
		$key_b = $normalizer->key_for_url( 'https://example.com/' );

		$this->assertSame( $key_a, $key_b );
		$this->assertSame( 32, strlen( $key_a ) );
	}
}
