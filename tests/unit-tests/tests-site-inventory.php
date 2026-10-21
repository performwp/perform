<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\SiteInventory;
use Perform\Admin\Settings\SiteInventoryController;

final class Tests_Site_Inventory extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options']                = [
			'active_plugins'   => [ 'perform/perform.php', 'plugin-1/plugin.php' ],
			'perform_settings' => [
				'enable_page_cache' => true,
				'enable_cdn'        => false,
			],
			'show_on_front'    => 'page',
			'page_on_front'    => 42,
			'page_for_posts'   => 0,
		];
		$GLOBALS['perform_test_plugins']                = [];
		$GLOBALS['perform_test_post_types']             = [];
		$GLOBALS['perform_test_post_counts']            = [];
		$GLOBALS['perform_test_current_user_can']       = [ 'manage_options' => true ];
		$GLOBALS['perform_test_is_multisite']           = false;
		$GLOBALS['perform_test_blog_id']                = 1;
		$GLOBALS['perform_test_is_block_theme']         = true;
		$GLOBALS['perform_test_network_active_plugins'] = [];
		$GLOBALS['perform_test_theme']                  = new Perform_Test_Inventory_Theme();
		$GLOBALS['wpdb']                                = new Perform_Test_Inventory_Wpdb();
		$GLOBALS['wp_version']                          = '7.0.3';
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_plugins'],
			$GLOBALS['perform_test_post_types'],
			$GLOBALS['perform_test_post_counts'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_is_multisite'],
			$GLOBALS['perform_test_blog_id'],
			$GLOBALS['perform_test_is_block_theme'],
			$GLOBALS['perform_test_network_active_plugins'],
			$GLOBALS['perform_test_theme'],
			$GLOBALS['perform_test_rest_routes'],
			$GLOBALS['wpdb'],
			$GLOBALS['wp_version']
		);
	}

	public function test_refresh_collects_a_bounded_redacted_snapshot() {
		$GLOBALS['perform_test_options']['active_plugins'] = [ 'perform/perform.php', 'plugin-104/plugin.php' ];

		for ( $index = 0; $index < 105; ++$index ) {
			$file                                     = 0 === $index ? 'perform/perform.php' : 'plugin-' . $index . '/plugin.php';
			$GLOBALS['perform_test_plugins'][ $file ] = [
				'Name'    => 0 === $index ? 'Perform' : 'Plugin ' . $index,
				'Version' => '1.0.' . $index,
				'Secret'  => 'must-not-leak',
			];
		}

		for ( $index = 0; $index < 35; ++$index ) {
			$name                                        = 0 === $index ? 'post' : 'custom_' . $index;
			$GLOBALS['perform_test_post_types'][ $name ] = (object) [
				'label'           => 0 === $index ? 'Posts' : 'Custom ' . $index,
				'_builtin'        => 0 === $index,
				'public'          => true,
				'show_ui'         => true,
				'show_in_rest'    => true,
				'has_archive'     => 0 !== $index,
				'hierarchical'    => false,
				'capability_type' => 'post',
			];
			$GLOBALS['perform_test_post_counts'][ $name ] = [
				'publish' => 1000000 + $index,
				'draft'   => 2,
			];
		}

		$snapshot = SiteInventory::refresh();

		$this->assertSame( 'ready', $snapshot['status'] );
		$this->assertSame( 105, $snapshot['plugins']['installedCount'] );
		$this->assertSame( 2, $snapshot['plugins']['activeCount'] );
		$this->assertCount( SiteInventory::PLUGIN_LIMIT, $snapshot['plugins']['items'] );
		$this->assertTrue( $snapshot['plugins']['isTruncated'] );
		$this->assertCount( SiteInventory::POST_TYPE_LIMIT, $snapshot['postTypes']['items'] );
		$this->assertTrue( $snapshot['postTypes']['isTruncated'] );
		$post_type_counts = array_column( $snapshot['postTypes']['items'], 'contentCount', 'name' );
		$this->assertSame( 1000002, $post_type_counts['post'] );
		$this->assertSame( 29, $snapshot['patterns']['customContent'] );
		$this->assertFalse( $snapshot['postTypes']['items'][0]['hasArchive'] );
		$this->assertSame( 'needs-separate-test', $snapshot['signals']['fieldMetrics'] );
		$this->assertArrayNotHasKey( 'Secret', $snapshot['plugins']['items'][0] );
		$this->assertArrayNotHasKey( 'optionValue', $snapshot );
		$this->assertArrayNotHasKey( 'url', $snapshot['configuration'] );
		$this->assertTrue( $snapshot['collection']['bounded'] );
		$this->assertIsFloat( $snapshot['collection']['durationMs'] );
		$this->assertIsInt( $snapshot['collection']['peakMemoryDeltaBytes'] );
		$this->assertSame( $snapshot, $GLOBALS['perform_test_options'][ SiteInventory::OPTION_NAME ] );
	}

	public function test_multisite_inventory_is_explicitly_current_site_only() {
		$GLOBALS['perform_test_is_multisite']           = true;
		$GLOBALS['perform_test_blog_id']                = 7;
		$GLOBALS['perform_test_network_active_plugins'] = [ 'network/plugin.php' ];
		$GLOBALS['perform_test_plugins']                = [
			'network/plugin.php' => [
				'Name'    => 'Network Plugin',
				'Version' => '2.0.0',
			],
		];

		$snapshot = SiteInventory::refresh();

		$this->assertTrue( $snapshot['isMultisite'] );
		$this->assertSame( 7, $snapshot['siteId'] );
		$this->assertSame( 'site', $snapshot['scope'] );
		$this->assertSame( 1, $snapshot['plugins']['activeCount'] );
	}

	public function test_unavailable_local_values_are_reported_without_failure() {
		$GLOBALS['perform_test_theme'] = false;
		$GLOBALS['wpdb']->version      = '';

		$snapshot = SiteInventory::refresh();

		$this->assertSame( 'not-available', $snapshot['theme']['state'] );
		$this->assertSame( 'not-available', $snapshot['environment']['database']['state'] );
		$this->assertSame( [], $snapshot['postTypes']['items'] );
	}

	public function test_cached_snapshot_is_stale_without_recollection() {
		$GLOBALS['perform_test_options'][ SiteInventory::OPTION_NAME ] = [
			'schemaVersion' => SiteInventory::SCHEMA_VERSION,
			'status'        => 'ready',
			'expiresAt'     => time() - 1,
		];

		$snapshot = SiteInventory::get_cached_snapshot();

		$this->assertTrue( $snapshot['isStale'] );
	}

	public function test_controller_registers_post_route_and_requires_manage_options() {
		$controller = new SiteInventoryController();
		$controller->register_routes();

		$this->assertSame( 'perform/v1', $GLOBALS['perform_test_rest_routes'][0]['namespace'] );
		$this->assertSame( '/site-inventory', $GLOBALS['perform_test_rest_routes'][0]['route'] );
		$this->assertSame( 'POST', $GLOBALS['perform_test_rest_routes'][0]['args']['methods'] );
		$this->assertTrue( $controller->permissions_check() );

		$GLOBALS['perform_test_current_user_can']['manage_options'] = false;
		$error = $controller->permissions_check();

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 403, $error->get_error_data()['status'] );
	}

	public function test_controller_returns_the_shared_inventory_schema() {
		$result = ( new SiteInventoryController() )->refresh();

		$this->assertIsArray( $result );
		$this->assertSame( 'ready', $result['inventory']['status'] );
		$this->assertSame( SiteInventory::SCHEMA_VERSION, $result['inventory']['schemaVersion'] );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Focused theme fixture for this service.
final class Perform_Test_Inventory_Theme {
	public function exists() {
		return true;
	}

	public function get( $header ) {
		return 'Name' === $header ? 'Test Theme' : '1.2.3';
	}

	public function get_stylesheet() {
		return 'test-theme';
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Focused database fixture for this service.
final class Perform_Test_Inventory_Wpdb {
	/** @var string */
	public $version = '8.0.0';

	public function db_version() {
		return $this->version;
	}
}
