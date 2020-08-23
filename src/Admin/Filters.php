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
	public function __construct() {
		add_filter( 'admin_footer_text', [ $this, 'add_admin_footer_text' ] );
		add_filter( 'plugin_action_links_' . PERFORM_PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );
		add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
	}

	/**
	 * Add rating links to the admin dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param string $footer_text The existing footer text
	 *
	 * @return string
	 */
	public function add_admin_footer_text( $footerText ) {
		$current_screen = get_current_screen();

		if ( true == stristr( $current_screen->base, 'perform' ) ) {

			$ratingText = sprintf(
				'%1$s <strong>%2$s</strong> %3$s <a href="%4$s" target="_blank" class="perform-rating-link">%5$s</a>',
				esc_html__( 'If you love using', 'perform' ),
				esc_html__( 'Perform WordPress Plugin', 'perform' ),
				esc_html__( 'please leave us a', 'perform' ),
				esc_url( 'https://wordpress.org/support/plugin/perform/reviews/?filter=5#postform' ),
				esc_html__( 'rating. It takes a minute and helps a lot. Thanks in advance!', 'perform' ),
			);

			return $ratingText;
		} else {
			return $footerText;
		}
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
				admin_url( 'admin.php?page=perform' ),
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
