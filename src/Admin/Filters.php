<?php
/**
 * Perform - Admin Filters.
 *
 * @package    Perform
 * @subpackage Admin/Filters
 * @since      2.0.0
 * @author     Mehul Gohil
 */

namespace Perform\Admin;

class Filters {

	/**
	 * Constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . PERFORM_PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );
		add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
	}


	/**
	 * Plugin page action links.
	 *
	 * @param array $actions An array of plugin action links.
	 *
	 * @since 1.0.1
	 *
	 * @return array
	 */
	public function add_plugin_action_links( $actions ) {
		$new_actions = [
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				admin_url( 'options-general.php?page=perform_settings' ),
				esc_html__( 'Settings', 'perform' )
			),
			'support'  => sprintf(
				'<a target="_blank" href="%1$s">%2$s</a>',
				esc_url_raw( 'https://wordpress.org/support/plugin/perform/' ),
				esc_html__( 'Support', 'perform' )
			),
		];

		return array_merge( $new_actions, $actions );
	}

	/**
	 * This function is used to add `Manage Assets` in quick action under admin CPT listing.
	 *
	 * @since  1.1.2
	 * @access public
	 *
	 * @return array
	 */
	public function add_row_actions( $actions, $post ) {
		if ( 'publish' === $post->post_status ) {
			$actions['assets_manager'] = sprintf(
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( get_the_permalink( $post->ID ) . '?perform' ),
				esc_html__( 'Manage Assets', 'perform' )
			);
		}

		return $actions;
	}
}
