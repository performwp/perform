<?php
/**
 * Perform | Helpers
 *
 * @since 2.0.0
 *
 * @package Perform
 * @subpackage Includes
 * @author Mehul Gohil <hello@mehulgohil.com>
 */

namespace Perform\Includes;

use const WP_CONTENT_FOLDERNAME;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper Class.
 *
 * @since 2.0.0
 *
 * @return void
 */
class Helpers {

	/**
	 * Has Network Access.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public static function has_network_access() {
		// Bailout, if not Multi-Site instance.
		if ( ! is_multisite() ) {
			return true;
		}

		$network = get_site_option( 'perform_network' );

		if (
			! is_super_admin() &&
			! empty( $network['access'] ) &&
			'super' === $network['access']
		) {
			return false;
		}
	}

	/**
	 * Get the value of a settings field.
	 *
	 * @param string $option  Settings field name.
	 * @param string $section The section name this field belongs to.
	 * @param string $default Default text if it's not found.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function get_option( $option, $section, $default = '' ) {
		$options = get_option( $section );

		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return $default;
	}

	/**
	 * Check if radio(enabled/disabled) and checkbox(on) is active or not.
	 *
	 * @param string $value        Value.
	 * @param string $compare_with Compare With.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public static function is_settings_enabled( $value, $compare_with = '' ) {
		if ( ! is_null( $compare_with ) ) {
			if ( is_array( $compare_with ) ) {
				return in_array( $value, $compare_with, true );
			}

			return ( $value === $compare_with );
		}

		return ( in_array( $value, [ 'enabled', 'on', 'yes' ], true ) ? true : false );
	}

	/**
	 * Is Assets Manager Enabled?
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public static function is_assets_manager_enabled() {
		return self::get_option( 'enable_assets_manager', 'perform_advanced', false );
	}

	/**
	 * Is WooCommerce Active?
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		$is_active = false;

		// Return true when WooCommerce plugin is active.
		if ( class_exists( 'WooCommerce' ) ) {
			$is_active = true;
		}

		return $is_active;
	}

	/**
	 * Clean variables using `sanitize_text_field`.
	 * Arrays are cleaned recursively. Non-scalar values are ignored.
	 *
	 * @param string|array $var Sanitize the variable.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string|array
	 */
	public static function clean( $var ) {
		if ( is_array( $var ) ) {
			return array_map( [ __CLASS__, __METHOD__ ], $var );
		} else {
			return is_scalar( $var ) ? sanitize_text_field( wp_unslash( $var ) ) : $var;
		}
	}

	/**
	 * This function will return the content directory name.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function get_content_dir_name() {
		return defined( 'WP_CONTENT_FOLDERNAME' ) ? WP_CONTENT_FOLDERNAME : 'wp-content';
	}

	/**
	 * Can Display Assets Manager.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public static function can_display_assets_manager() {
		if ( isset( $_GET['perform'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current tab.
	 *
	 * This function will be used only in admin.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function get_current_tab() {
		$screen      = get_current_screen();
		$current_tab = ! empty( $_GET['tab'] ) ? $_GET['tab'] : '';

		if ( 'settings_page_perform_settings' === $screen->id ) {
			$current_tab = ! empty( $current_tab ) ? $current_tab : 'general';
		}

		return $current_tab;
	}

	/**
	 * Get Admin Settings.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return array
	 */
	public static function get_settings() {
		return get_option( 'perform_settings' );
	}

	/**
	 * Get settings tabs for the settings page.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @return array
	 */
	public static function get_settings_tabs() {
		$tabs = [
			'general'  => esc_html__( 'General', 'perform' ),
			'ssl'      => esc_html__( 'SSL', 'perform' ),
			'cdn'      => esc_html__( 'CDN', 'perform' ),
			'advanced' => esc_html__( 'Advanced', 'perform' ),
		];
		if ( self::is_woocommerce_active() ) {
			$tabs['woocommerce'] = esc_html__( 'WooCommerce', 'perform' );
		}
		return $tabs;
	}

	/**
	 * Get settings fields for the settings page, grouped by tab and card.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @return array
	 */
	public static function get_settings_fields() {
		$utm_args = [
			'utm_source'   => 'admin-settings',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'perform',
		];
		return [
			'general' => [
				[
					'title' => esc_html__('General Settings', 'perform'),
					'description' => esc_html__('General performance and cleanup options.', 'perform'),
					'fields' => [
						[
							'id'        => 'disable_emojis',
							'type'      => 'checkbox',
							'name'      => esc_html__( 'Disable Emoji\'s', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will disable the usage of emoji\'s in WordPress Posts, Pages, and Custom Post Types.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-emojis'
								)
							),
						],
						// ...existing general fields...
					]
				]
			],
			// ...other tabs (ssl, cdn, advanced, woocommerce) with similar card structure...
		];
	}

	/**
	 * Compress HTML.
	 *
	 * This function will be used to remove whitespaces around HTML tags.
	 *
	 * @param mixed $html HTML Content.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public static function compress_html( $html ) {
		return trim( preg_replace( '/\>\s+\</m', '><', $html ) );
	}
}
