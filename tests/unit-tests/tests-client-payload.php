<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\ClientPayload;

final class Tests_Client_Payload extends TestCase {
	public function test_masks_sensitive_settings_for_client_payload() {
		$payload = ClientPayload::sanitize_for_client(
			[
				'cloudflare_api_token' => 'my-real-secret',
				'enable_page_cache'    => 1,
			]
		);

		$this->assertSame( ClientPayload::MASKED_SECRET, $payload['cloudflare_api_token'] );
		$this->assertSame( 1, $payload['enable_page_cache'] );
	}

	public function test_detects_masked_secret_value() {
		$this->assertTrue( ClientPayload::is_masked_secret( ClientPayload::MASKED_SECRET ) );
		$this->assertFalse( ClientPayload::is_masked_secret( 'plain-value' ) );
	}

	public function test_textarea_array_values_are_normalized_for_client_payload() {
		$payload = ClientPayload::sanitize_for_client(
			[
				'cache_bypass_exact_paths' => [
					'/members',
					'/account/dashboard',
				],
			]
		);

		$this->assertSame( "/members\n/account/dashboard", $payload['cache_bypass_exact_paths'] );
	}
}
