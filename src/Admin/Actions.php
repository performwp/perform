<?php
/**
 * Perform - Admin Actions.
 *
 * @package    Perform
 * @subpackage Admin/Actions
 * @since      2.0.0
 * @author     Mehul Gohil
 */

namespace Perform\Admin;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'registerAssets' ] );
		add_action( 'admin_bar_menu', [ $this, 'add_to_admin_bar' ], 1000, 1 );
	}

	/**
	 * Add Admin Assets.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function registerAssets() {
		wp_register_style( 'perform-admin', PERFORM_PLUGIN_URL . 'assets/dist/css/admin.css', '', PERFORM_VERSION );
		wp_enqueue_style( 'perform-admin' );
	}

	/**
	 * This function is used to add assets manager button in admin bar.
	 *
	 * @param object $wp_admin_bar List of items on admin bar.
	 *
	 * @since  1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function add_to_admin_bar( $wp_admin_bar ) {
		// Bailout, if conditions below doesn't pass through.
		if ( ! current_user_can( 'manage_options' ) || is_admin() ) {
			return;
		}

		global $wp;

		$server_data = Helpers::clean( filter_input_array( INPUT_SERVER ) );

		$href = add_query_arg(
			str_replace( [ '&perform', 'perform' ], '', $server_data['QUERY_STRING'] ),
			'',
			home_url( $wp->request )
		);

		if ( ! isset( $_GET['perform'] ) ) {
			$href     .= ! empty( $server_data['QUERY_STRING'] ) ? '&perform' : '?perform';
			$menu_text = esc_html__( 'Assets Manager', 'perform' );
		} else {
			$menu_text = esc_html__( 'Close Assets Manager', 'perform' );
		}

		// Add Parent Menu.
		$wp_admin_bar->add_menu(
			[
				'id'    => 'perform',
				'title' => esc_html__( 'Perform', 'perform' ),
				'href'  => esc_url( admin_url( 'options-general.php?page=perform' ) ),
			]
		);

		// Add Assets Manager Sub-menu.
		$wp_admin_bar->add_menu(
			[
				'parent' => 'perform',
				'id'     => 'assets-manager',
				'title'  => $menu_text,
				'href'   => $href,
			]
		);

		// Add Support Sub-menu.
		$wp_admin_bar->add_menu(
			[
				'parent' => 'perform',
				'id'     => 'support-forum',
				'title'  => esc_html__( 'Support Forum', 'perform' ),
				'href'   => esc_url( 'https://wordpress.org/support/plugin/perform/' ),
			]
		);
	}
}
