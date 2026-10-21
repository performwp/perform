<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\CDN\CDNManager;
use Perform\Modules\Loader;

final class Tests_CDN_Manager extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_actions'] = [];
		$GLOBALS['perform_test_options'] = [
			'home' => 'https://example.com',
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_actions'], $GLOBALS['perform_test_options'] );
	}

	public function test_should_load_requires_a_valid_cdn_url() {
		$disabled = new CDNManager(
			[
				'enable_cdn' => 1,
				'cdn_url'    => '',
			]
		);
		$this->assertFalse( $disabled->should_load() );

		$invalid = new CDNManager(
			[
				'enable_cdn' => 1,
				'cdn_url'    => 'cdn.example.com',
			]
		);
		$this->assertFalse( $invalid->should_load() );

		$valid = new CDNManager(
			[
				'enable_cdn' => 1,
				'cdn_url'    => 'https://cdn.example.com',
			]
		);
		$this->assertTrue( $valid->should_load() );
	}

	public function test_loader_only_registers_the_module_for_valid_settings() {
		$loader = new Loader(
			[
				'enable_cdn' => 1,
				'cdn_url'    => '',
			]
		);
		$loader->register_modules( [ CDNManager::class ] );

		$this->assertSame( [], $GLOBALS['perform_test_actions'] );

		$GLOBALS['perform_test_actions'] = [];

		$loader = new Loader(
			[
				'enable_cdn' => 1,
				'cdn_url'    => 'https://cdn.example.com',
			]
		);
		$loader->register_modules( [ CDNManager::class ] );

		$this->assertCount( 1, $GLOBALS['perform_test_actions'] );
		$this->assertSame( 'template_redirect', $GLOBALS['perform_test_actions'][0]['hook'] );
	}

	public function test_rewrite_with_cdn_url_preserves_exclusions() {
		$manager = new CDNManager(
			[
				'enable_cdn'      => 1,
				'cdn_url'         => 'https://cdn.example.com',
				'cdn_exclusions'  => '.php,skip-me',
				'cdn_directories' => 'wp-content,wp-includes',
			]
		);

		$html      = '<script src="https://example.com/wp-content/skip-me.js"></script><img src="https://example.com/wp-content/uploads/image.jpg" />';
		$rewritten = $manager->rewrite_with_cdn_url( $html );

		$this->assertStringContainsString( 'https://example.com/wp-content/skip-me.js', $rewritten );
		$this->assertStringContainsString( 'https://cdn.example.com/wp-content/uploads/image.jpg', $rewritten );
	}
}
