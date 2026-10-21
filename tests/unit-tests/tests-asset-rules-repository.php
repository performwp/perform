<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Assets\AssetRulesRepository;

final class Tests_Asset_Rules_Repository extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [];
	}

	public function test_missing_or_invalid_option_returns_empty_rules(): void {
		$repository = new AssetRulesRepository();

		$this->assertSame( [], $repository->get() );

		$GLOBALS['perform_test_options']['perform_assets_manager_options'] = 'invalid';
		$this->assertSame( [], $repository->get() );
	}

	public function test_rules_can_be_saved_read_and_reset(): void {
		$repository = new AssetRulesRepository();
		$rules      = [
			'disabled' => [
				'js' => [
					'example' => [ 'everywhere' => 1 ],
				],
			],
		];

		$this->assertTrue( $repository->save( $rules ) );
		$this->assertSame( $rules, $repository->get() );
		$this->assertTrue( $repository->reset() );
		$this->assertSame( [], $repository->get() );
	}
}
