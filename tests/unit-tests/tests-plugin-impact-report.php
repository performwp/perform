<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\PluginImpactReport;

final class Tests_Plugin_Impact_Report extends TestCase {
	public function test_requires_inventory_before_claiming_plugin_impact() {
		$report = PluginImpactReport::get_data(
			[ 'status' => 'not-run' ],
			[
				'enabled' => false,
				'screens' => [],
			]
		);

		$this->assertSame( 'inventory-needed', $report['status'] );
		$this->assertSame( [], $report['items'] );
		$this->assertSame( 'not-measured', $report['adminAssetAudit']['state'] );
	}

	public function test_joins_only_high_confidence_measured_admin_assets_to_active_plugins() {
		$inventory = $this->inventory(
			[
				$this->plugin( 'quiet', 'Quiet Plugin', true ),
				$this->plugin( 'sample', 'Sample Plugin', true ),
				$this->plugin( 'inactive', 'Inactive Plugin', false ),
			]
		);
		$audit     = [
			'enabled' => true,
			'screens' => [
				[
					'id'     => 'dashboard',
					'assets' => [ $this->asset( 'script', 'sample-admin', 'sample', 'high' ), $this->asset( 'style', 'uncertain', 'quiet', 'low' ) ],
				],
				[
					'id'     => 'edit-post',
					'assets' => [ $this->asset( 'script', 'sample-admin', 'sample', 'high' ), $this->asset( 'style', 'sample-style', 'sample', 'high' ) ],
				],
			],
		];

		$report = PluginImpactReport::get_data( $inventory, $audit );

		$this->assertSame( 'ready', $report['status'] );
		$this->assertCount( 2, $report['items'] );
		$this->assertSame( 'sample', $report['items'][0]['slug'] );
		$this->assertSame( 2, $report['items'][0]['adminAssets']['screenCount'] );
		$this->assertSame( 1, $report['items'][0]['adminAssets']['scriptCount'] );
		$this->assertSame( 1, $report['items'][0]['adminAssets']['styleCount'] );
		$this->assertSame( 'not-observed', $report['items'][1]['evidenceState'] );
		$this->assertSame( 'not-attributed', $report['unavailable']['queries'] );
		$this->assertSame( 'not-attributed', $report['unavailable']['memory'] );
	}

	public function test_many_plugins_remain_bounded_and_privacy_safe() {
		$plugins = [];
		for ( $index = 0; $index < 110; ++$index ) {
			$plugins[] = $this->plugin( 'plugin-' . $index, 'Plugin ' . $index, true );
		}
		$report = PluginImpactReport::get_data(
			$this->inventory( $plugins, true ),
			[
				'enabled' => true,
				'screens' => [
					[
						'id'     => 'settings?nonce=secret',
						'assets' => [ $this->asset( 'script', 'private?token=secret', 'plugin-1', 'high' ) ],
					],
				],
			]
		);

		$this->assertLessThanOrEqual( PluginImpactReport::MAX_PLUGINS, count( $report['items'] ) );
		$this->assertTrue( $report['isTruncated'] );
		$encoded = wp_json_encode( $report );
		$this->assertStringNotContainsString( 'nonce', $encoded );
		$this->assertStringNotContainsString( 'secret', $encoded );
		$this->assertStringNotContainsString( 'token', $encoded );
	}

	public function test_multisite_scope_is_explicitly_current_site() {
		$inventory                = $this->inventory( [ $this->plugin( 'sample', 'Sample', true ) ] );
		$inventory['isMultisite'] = true;
		$report                   = PluginImpactReport::get_data(
			$inventory,
			[
				'enabled' => false,
				'screens' => [],
			]
		);

		$this->assertTrue( $report['isMultisite'] );
		$this->assertSame( 'site', $report['scope'] );
	}

	private function inventory( array $plugins, $truncated = false ) {
		return [
			'status'      => 'ready',
			'plugins'     => [
				'activeCount' => count(
					array_filter(
						$plugins,
						static function ( $plugin ) {
							return ! empty( $plugin['active'] ); }
					)
				),
				'items'       => $plugins,
				'isTruncated' => $truncated,
			],
			'isMultisite' => false,
		];
	}

	private function plugin( $slug, $name, $active ) {
		return [
			'slug'    => $slug,
			'name'    => $name,
			'version' => '1.0',
			'active'  => $active,
		];
	}

	private function asset( $type, $handle, $source, $confidence ) {
		return [
			'type'       => $type,
			'handle'     => $handle,
			'source'     => $source,
			'confidence' => $confidence,
		];
	}
}
