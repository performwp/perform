<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Actions as AdminActions;

final class Tests_Admin_Actions extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_current_user_can'] = [
			'manage_options' => true,
		];
		$GLOBALS['perform_test_is_admin']         = false;
		$GLOBALS['perform_test_current_url']      = 'https://example.com/current-page/?foo=bar';
		unset( $_GET['perform'] );
	}

	protected function tearDown(): void {
		unset(
			$_GET['perform'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_is_admin'],
			$GLOBALS['perform_test_current_url']
		);
	}

	public function test_admin_bar_assets_manager_link_adds_perform_query_arg() {
		$admin_bar = new Perform_Test_Admin_Bar();
		$actions   = new AdminActions();

		$actions->add_to_admin_bar( $admin_bar );

		$this->assertSame( 'https://example.com/current-page/?foo=bar&perform=1', $admin_bar->get_menu_href( 'assets-manager' ) );
	}

	public function test_admin_bar_assets_manager_close_link_removes_perform_query_arg() {
		$_GET['perform']                     = '1';
		$GLOBALS['perform_test_current_url'] = 'https://example.com/current-page/?foo=bar&perform=1';

		$admin_bar = new Perform_Test_Admin_Bar();
		$actions   = new AdminActions();

		$actions->add_to_admin_bar( $admin_bar );

		$this->assertSame( 'https://example.com/current-page/?foo=bar', $admin_bar->get_menu_href( 'assets-manager' ) );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Small test fixture for the admin bar API.
final class Perform_Test_Admin_Bar {
	/**
	 * Captured admin bar menu items.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $menus = [];

	/**
	 * Capture an admin bar menu registration.
	 *
	 * @param array<string, mixed> $args Menu args.
	 *
	 * @return void
	 */
	public function add_menu( $args ) {
		$this->menus[ $args['id'] ] = $args;
	}

	/**
	 * Get captured menu href by ID.
	 *
	 * @param string $id Menu ID.
	 *
	 * @return string
	 */
	public function get_menu_href( $id ) {
		return isset( $this->menus[ $id ]['href'] ) ? (string) $this->menus[ $id ]['href'] : '';
	}
}
