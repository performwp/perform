<?php
/**
 * Perform - Actions.
 *
 * @package    Perform
 * @subpackage Includes/Actions
 * @since      2.0.0
 * @author     Mehul Gohil
 */

namespace Perform\Includes;

use Perform\Includes\Helpers;

class Actions {

	/**
	 * Constructor
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue Styles
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function enqueue_styles() {

		// Bailout, if can't display assets manager.
		if ( ! Helpers::can_display_assets_manager() ) {
			return;
		}

		wp_enqueue_style( 'perform', PERFORM_PLUGIN_URL . 'assets/dist/css/perform.css' );
	}

	/**
	 * Enqueue Scripts
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		// Bailout, if can't display assets manager.
		if ( ! Helpers::can_display_assets_manager() ) {
			return;
		}

		wp_enqueue_script( 'perform', PERFORM_PLUGIN_URL . 'assets/dist/js/perform.js', '', PERFORM_VERSION, false );
	}
}
