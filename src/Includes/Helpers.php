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
			'bloat'  => esc_html__( 'Bloat', 'perform' ),
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
			'bloat' => [
				[
					'title' => esc_html__('Assets Optimization', 'perform'),
					'description' => esc_html__('Fine-tune how WordPress loads scripts and assets across your site. Disabling unnecessary features reduces HTTP requests, removes bloat, and improves load times for your visitors.', 'perform'),
					'fields' => [
						[
							'id'        => 'disable_emojis',
							'type'      => 'toggle',
							'name'      => __( 'Disable Emoji\'s', 'perform' ),
							'desc'      => __( 'Enabling this will disable the usage of emoji\'s in WordPress Posts, Pages, and Custom Post Types.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-emojis'
								)
							),
						],
						[
							'id'        => 'disable_embeds',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Embeds', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will disable the usage of embeds in WordPress Posts, Pages, and Custom Post Types.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-embeds'
								)
							),
						],
					],
				],
				[
					'title' => esc_html__('Bloat Settings', 'perform'),
					'description' => esc_html__('Settings to reduce bloat and improve performance.', 'perform'),
					'fields' => [
						[
							'id'        => 'remove_query_strings',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove Query Strings', 'perform' ),
							'desc'      => esc_html__( 'Remove query strings from static resources (CSS, JS).', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-query-strings'
								)
							),
						],
						[
							'id'        => 'disable_xmlrpc',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable XML-RPC', 'perform' ),
							'desc'      => esc_html__( 'Disables WordPress XML-RPC functionality.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-xmlrpc'
								)
							),
						],
						[
							'id'        => 'remove_jquery_migrate',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove jQuery Migrate', 'perform' ),
							'desc'      => esc_html__( 'Removes jQuery Migrate JS file (jquery-migrate.min.js).', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-jquery-migrate'
								)
							),
						],
						[
							'id'        => 'hide_wp_version',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Hide WP Version', 'perform' ),
							'desc'      => esc_html__( 'Removes WordPress version generator meta tag.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/hide-wp-version'
								)
							),
						],
						[
							'id'        => 'remove_wlwmanifest_link',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove wlwmanifest Link', 'perform' ),
							'desc'      => esc_html__( 'Remove wlwmanifest link tag. It is usually used to support Windows Live Writer.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-wlwmanifest-link'
								)
							),
						],
						[
							'id'        => 'remove_rsd_link',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove RSD Link', 'perform' ),
							'desc'      => esc_html__( 'Remove RSD (Real Simple Discovery) link tag.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-rsd-link'
								)
							),
						],
						[
							'id'        => 'remove_shortlink',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove Shortlink', 'perform' ),
							'desc'      => esc_html__( 'Remove Shortlink link tag.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-shortlink'
								)
							),
						],
						[
							'id'        => 'disable_rss_feeds',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable RSS Feeds', 'perform' ),
							'desc'      => esc_html__( 'Disable WordPress generated RSS feeds and 301 redirect URL to parent.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-rss-feeds'
								)
							),
						],
						[
							'id'        => 'remove_feed_links',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove RSS Feed Links', 'perform' ),
							'desc'      => esc_html__( 'Disable WordPress generated RSS feed link tags.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-rss-feed-links'
								)
							),
						],
						[
							'id'        => 'remove_rest_api_links',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove REST API Links', 'perform' ),
							'desc'      => esc_html__( 'Removes REST API link tag from the front end and the REST API header link from page requests.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-rest-api-links'
								)
							),
						],
						[
							'id'        => 'disable_self_pingbacks',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Self Pingbacks', 'perform' ),
							'desc'      => esc_html__( 'Disable Self Pingbacks (generated when linking to an article on your own blog).', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-self-pingbacks'
								)
							),
						],
						[
							'id'        => 'disable_dashicons',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Dashicons', 'perform' ),
							'desc'      => esc_html__( 'Disables dashicons js on the front end when not logged in.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-dashicons'
								)
							),
						],
						[
							'id'        => 'disable_password_strength_meter',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Password Strength Meter', 'perform' ),
							'desc'      => esc_html__( 'Removes WordPress and WooCommerce Password Strength Meter scripts from non essential pages.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-password-strength-meter'
								)
							),
						],
						[
							'id'        => 'disable_heartbeat',
							'type'      => 'select',
							'name'      => esc_html__( 'Disable Heartbeat', 'perform' ),
							'options'   => [
								''                   => esc_html__( 'Default', 'perform' ),
								'disable_everywhere' => esc_html__( 'Disable Everywhere', 'perform' ),
								'allow_posts'        => esc_html__( 'Only Allow When Editing Posts/Pages', 'perform' ),
							],
							'desc'      => esc_html__( 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-heartbeat'
								)
							),
						],
						[
							'id'        => 'heartbeat_frequency',
							'type'      => 'select',
							'name'      => esc_html__( 'Heartbeat Frequency', 'perform' ),
							'options'   => [
								''   => sprintf( esc_html__( '%s Seconds', 'perform' ), '15' ) . ' (' . esc_html__( 'Default', 'perform' ) . ')',
								'30' => sprintf( esc_html__( '%s Seconds', 'perform' ), '30' ),
								'45' => sprintf( esc_html__( '%s Seconds', 'perform' ), '45' ),
								'60' => sprintf( esc_html__( '%s Seconds', 'perform' ), '60' ),
							],
							'desc'      => esc_html__( 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-heartbeat'
								)
							),
						],
						[
							'id'        => 'limit_post_revisions',
							'type'      => 'select',
							'name'      => esc_html__( 'Limit Post Revisions', 'perform' ),
							'options'   => [
								''      => esc_html__( 'Default', 'perform' ),
								'false' => esc_html__( 'Disable Post Revisions', 'perform' ),
								'1'     => '1',
								'2'     => '2',
								'3'     => '3',
								'4'     => '4',
								'5'     => '5',
								'10'    => '10',
								'15'    => '15',
								'20'    => '20',
								'25'    => '25',
								'30'    => '30',
							],
							'desc'      => esc_html__( 'Limits the maximum amount of revisions that are allowed for posts and pages.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/limit-post-revisions'
								)
							),
						],
						[
							'id'        => 'autosave_interval',
							'type'      => 'select',
							'name'      => esc_html__( 'Autosave Interval', 'perform' ),
							'options'   => [
								''    => esc_html__( '1 Minute', 'perform' ) . ' (' . esc_html__( 'Default', 'perform' ) . ')',
								'120' => sprintf( esc_html__( '%s Minutes', 'perform' ), '2' ),
								'180' => sprintf( esc_html__( '%s Minutes', 'perform' ), '3' ),
								'240' => sprintf( esc_html__( '%s Minutes', 'perform' ), '4' ),
								'300' => sprintf( esc_html__( '%s Minutes', 'perform' ), '5' ),
							],
							'desc'      => esc_html__( 'Controls how often WordPress will auto save posts and pages while editing.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/autosave-intervals'
								)
							),
						],

					]
				]
			],
			'ssl' => [
				[
					'title' => esc_html__('SSL Settings', 'perform'),
					'description' => esc_html__('Settings to manage SSL and HTTPS related configurations.', 'perform'),
					'fields' => [
						[
							'id'        => 'force_ssl',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Force SSL', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will force all traffic to use SSL.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/force-ssl'
								)
							),
						],
					]
				]
			],
			'cdn' => [
				[
					'title' => esc_html__('CDN Settings', 'perform'),
					'description' => esc_html__('Settings to manage CDN configurations.', 'perform'),
					'fields' => [
						[
							'id'        => 'enable_cdn',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Enable CDN', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will allow you to use a CDN for your static assets.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/cdn-manager'
								)
							),
						],
						[
							'id'        => 'cdn_url',
							'type'      => 'text',
							'name'      => esc_html__( 'CDN URL', 'perform' ),
							'desc'      => esc_html__( 'Enter the URL of your CDN provider.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/cdn-manager'
								)
							),
						],
					]
				]
			],
			'advanced' => [
				[
					'title' => esc_html__('Advanced Settings', 'perform'),
					'description' => esc_html__('Settings for advanced configurations.', 'perform'),
					'fields' => [
						[
							'id'        => 'enable_assets_manager',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Enable Assets Manager', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will allow you to manage your assets more effectively.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/assets-manager'
								)
							),
						],
						[
							'id'        => 'enable_menu_cache',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Enable Menu Cache', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will cache your menu items for better performance.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/menu-cache'
								)
							),
						],
					]
				]
			],
			'woocommerce' => [
				[
					'title' => esc_html__('WooCommerce Settings', 'perform'),
					'description' => esc_html__('Settings specific to WooCommerce.', 'perform'),
					'fields' => [
						[
							'id'        => 'enable_woocommerce_manager',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Enable WooCommerce Manager', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will allow you to manage WooCommerce specific settings.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/woocommerce-manager'
								)
							),
						],
						[
							'id'        => 'woocommerce_cache',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Enable WooCommerce Cache', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will cache WooCommerce pages for better performance.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/woocommerce-cache'
								)
							),
						],
					]
				]
			],
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
