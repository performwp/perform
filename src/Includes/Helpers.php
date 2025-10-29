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
			// Recursively clean array values by calling this same method.
			return array_map( [ __CLASS__, 'clean' ], $var );
		}

		return is_scalar( $var ) ? sanitize_text_field( wp_unslash( $var ) ) : $var;
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
			'bloat'    => esc_html__( 'Bloat', 'perform' ),
			'assets'   => esc_html__( 'Assets', 'perform' ),
			'cdn'      => esc_html__( 'CDN', 'perform' ),
			'advanced' => esc_html__( 'Advanced', 'perform' ),
		];

		// Add WooCommerce tab if WooCommerce is active.
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
		// Generate UTM args for help links.
		$utm_args = [
			'utm_source'   => 'admin-settings',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'perform',
		];

		// Settings Fields.
		return [
			'general' => [
				[
					'title'       => esc_html__('General Settings', 'perform'),
					'description' => esc_html__('Configure general performance settings for your WordPress site.', 'perform'),
					'fields'      => [
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
					],
				]
			],
			'bloat' => [
				[
					'title'       => esc_html__('Frontend Optimization', 'perform'),
					'description' => esc_html__('Remove unnecessary frontend scripts and tags that add extra requests or bytes to every page. These optimizations reduce file requests, improve cacheability, and clean up redundant page elements.', 'perform'),
					'fields'      => [
						[
							'id'        => 'disable_emojis',
							'type'      => 'toggle',
							'name'      => __( 'Disable Emoji\'s', 'perform' ),
							'desc'      => __( 'Prevents WordPress from loading the emoji detection script and related styles, reducing one extra HTTP request.', 'perform' ),
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
							'desc'      => esc_html__( 'Removes the WordPress Embed script (wp-embed.min.js) used for embedding posts and media across sites.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-embeds'
								)
							),
						],
						[
							'id'        => 'remove_query_strings',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove Query Strings', 'perform' ),
							'desc'      => esc_html__( 'Strips version query strings from static resources (?ver=) to improve caching efficiency on CDNs and browsers.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-query-strings'
								)
							),
						],
						[
							'id'        => 'remove_jquery_migrate',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove jQuery Migrate', 'perform' ),
							'desc'      => esc_html__( 'Prevents loading of the legacy jquery-migrate.min.js file, used mainly for backward compatibility with outdated scripts.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-jquery-migrate'
								)
							),
						],
						[
							'id'        => 'disable_dashicons',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Dashicons', 'perform' ),
							'desc'      => esc_html__( 'Prevents loading the dashicons.css icon font on the frontend for non-logged-in visitors.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-dashicons'
								)
							),
						],
						[
							'id'        => 'hide_wp_version',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Hide WP Version', 'perform' ),
							'desc'      => esc_html__( 'Removes the WordPress version meta tag from the page source, reducing page markup size slightly.', 'perform' ),
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
							'desc'      => esc_html__( 'Removes the Windows Live Writer manifest tag, an obsolete feature unused on modern sites.', 'perform' ),
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
							'desc'      => esc_html__( 'Removes the Real Simple Discovery (RSD) tag used by remote publishing tools.', 'perform' ),
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
							'desc'      => esc_html__( 'Removes the rel="shortlink" tag generated for posts to reduce unnecessary metadata output.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-shortlink'
								)
							),
						],
					],
				],
				[
					'title'       => esc_html__('Network Requests and Endpoints', 'perform'),
					'description' => esc_html__('Disable unused services that generate background or external HTTP requests. Ideal for sites that don\'t rely on remote publishing or REST-based integrations.', 'perform'),
					'fields'      => [
						[
							'id'        => 'disable_xmlrpc',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable XML-RPC', 'perform' ),
							'desc'      => esc_html__( 'Disables WordPress XML-RPC functionality, which handles remote publishing and pingbacks, saving processing overhead.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-xmlrpc'
								)
							),
						],
						[
							'id'        => 'remove_rest_api_links',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove REST API Links', 'perform' ),
							'desc'      => esc_html__( 'Removes REST API discovery links from the site header and page responses, reducing unnecessary HTTP headers.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-rest-api-links'
								)
							),
						],
					],
				],
				[
					'title'       => esc_html__('Feed and Discovery Optimization', 'perform'),
					'description' => esc_html__('Stop generating feed files and related discovery tags that most modern sites don\'t need. Helps reduce crawl requests and prevents unnecessary feed generation.', 'perform'),
					'fields'      => [
						[
							'id'        => 'disable_rss_feeds',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable RSS Feeds', 'perform' ),
							'desc'      => esc_html__( 'Disables WordPress-generated RSS feeds and redirects feed URLs back to the homepage.', 'perform' ),
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
							'desc'      => esc_html__( 'Removes all RSS feed link tags from the site’s header.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/remove-rss-feed-links'
								)
							),
						],
					],
				],
				[
					'title'       => esc_html__('Editor and Backend Performance', 'perform'),
					'description' => esc_html__('Limit WordPress background activity during content editing to reduce CPU and database usage. These controls keep your admin fast, reduce CPU cycles, and optimize database performance.', 'perform'),
					'fields'      => [
						[
							'id'        => 'disable_self_pingbacks',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Self Pingbacks', 'perform' ),
							'desc'      => esc_html__( 'Prevents WordPress from sending self-pingbacks when linking to your own posts.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/disable-self-pingbacks'
								)
							),
						],
						[
							'id'        => 'disable_password_strength_meter',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Disable Password Strength Meter', 'perform' ),
							'desc'      => esc_html__( 'Prevents loading of the password strength meter script (zxcvbn.js) on non-essential admin pages.', 'perform' ),
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
							'desc'      => esc_html__( 'Stops or limits the WordPress Heartbeat API that sends frequent AJAX requests from the browser to the server.', 'perform' ),
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
							'desc'      => esc_html__( 'Adjusts how often the Heartbeat API runs (lower frequency = fewer background requests).', 'perform' ),
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
							'desc'      => esc_html__( 'Limits the number of post revisions stored in the database to prevent bloat.', 'perform' ),
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
							'desc'      => esc_html__( 'Controls how often posts are autosaved while editing, reducing unnecessary database writes.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/autosave-intervals'
								)
							),
						],
					],
				],
			],
			'assets' => [
				[
					'title'       => esc_html__('Assets Optimization', 'perform'),
					'description' => esc_html__('Settings to manage asset loading and optimization.', 'perform'),
					'fields'      => [
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
							'id'        => 'dns_prefetch',
							'type'      => 'textarea',
							'name'      => esc_html__( 'DNS Prefetch', 'perform' ),
							'desc'      => esc_html__( 'Resolve domain names before a user clicks. Format: //domain.tld (one per line)', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/dns-prefetch'
								)
							),
						],
						[
							'id'        => 'preconnect',
							'type'      => 'textarea',
							'name'      => esc_html__( 'Preconnect', 'perform' ),
							'desc'      => esc_html__( 'Establish a connection to another origin before a user clicks. Format: //domain.tld (one per line)', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/preconnect'
								)
							),
						],
					]
				]
			],
			'cdn' => [
				[
					'title'       => esc_html__('CDN Settings', 'perform'),
					'description' => esc_html__('Settings to manage CDN configurations.', 'perform'),
					'fields'      => [
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
						[
							'id'        => 'cdn_directories',
							'type'      => 'text',
							'name'      => esc_html__( 'Included Directories', 'perform' ),
							'desc'      => esc_html__( 'Enter any directories you would like to be included in CDN rewriting, separated by commas (,). Default: wp-content,wp-includes', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/cdn-rewrite'
								)
							),
						],
						[
							'id'        => 'cdn_exclusions',
							'type'      => 'text',
							'name'      => esc_html__( 'CDN Exclusions', 'perform' ),
							'desc'      => esc_html__( 'Enter any directories or file extensions you would like to be excluded from CDN rewriting, separated by commas (,). Default: .php', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/cdn-rewrite'
								)
							),
						],
					]
				]
			],
			'advanced' => [
				[
					'title'       => esc_html__('Advanced Settings', 'perform'),
					'description' => esc_html__('Settings for advanced configurations.', 'perform'),
					'fields'      => [
						[
							'id'        => 'remove_data_on_uninstall',
							'type'      => 'toggle',
							'name'      => esc_html__( 'Remove Data on Uninstall', 'perform' ),
							'desc'      => esc_html__( 'Enabling this will remove all plugin data upon uninstallation.', 'perform' ),
							'help_link' => esc_url(
								add_query_arg(
									$utm_args,
									'https://performwp.com/docs/clean-uninstall'
								)
							),
						],
					]
				]
			],
			'woocommerce' => [
				[
					'title'       => esc_html__('WooCommerce Settings', 'perform'),
					'description' => esc_html__('Settings specific to WooCommerce.', 'perform'),
					'fields'      => [
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
