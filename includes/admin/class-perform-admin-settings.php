<?php
/**
 * Perform - Admin Settings.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Admin Settings
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Perform_Admin_Settings' ) ) {

	/**
	 * Class Perform_Admin_Settings
	 *
	 * @since 1.0.0
	 */
	class Perform_Admin_Settings extends Perform_Admin_Settings_API {

		/**
		 * Admin Settings API.
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public $settings_api = array();

		/**
		 * List of Tabs.
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public $tabs = array();

		/**
		 * Perform_Admin_Settings constructor.
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function __construct() {

		    parent::__construct();

		    $this->prefix = 'perform_';
		    $this->tabs   = array(
				'common'        => __( 'General', 'perform' ),
				'ssl'           => __( 'SSL', 'perform' ),
				'cdn'           => __( 'CDN', 'perform' ),
				'advanced'      => __( 'Advanced', 'perform' ),
				// 'import_export' => __( 'Import/Export', 'perform' ),
				// 'support'       => __( 'Support', 'perform' ),
			);

			// Display WooCommerce tab when WooCommerce plugin is active.
			if ( perform_is_woocommerce_active() ) {
				$this->tabs['woocommerce'] = __( 'WooCommerce', 'perform' );
			}

			$this->add_tabs();
			$this->add_fields();

			// Admin Menu.
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 9 );

		}

		/**
		 * This function will add admin menu.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function add_admin_menu() {

			add_options_page(
				__( 'Perform', 'perform' ),
				__( 'Perform', 'perform' ),
				'manage_options',
				'perform',
				array( $this, 'settings_page' )
			);

		}

		/**
		 * Add Tabs
         *
         * @since 1.0.0
		 */
		public function add_tabs() {

            foreach ( $this->tabs as $slug => $name ) {

	            $this->add_section(
		            array(
			            'id'    => $this->prefix . $slug,
			            'title' => $name,
		            )
	            );

            }

		}

		/**
		 * This function will add fields.
		 *
		 * @since 1.0.0
		 */
		public function add_fields() {

		    // Disable Emoji's.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_emojis',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Emoji\'s', 'perform' ),
					'desc'      => __( 'Enabling this will disable the usage of emoji\'s in WordPress Posts, Pages, and Custom Post Types.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-emojis'
						)
					),
				)
			);

			// Disable Embeds.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_embeds',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Embeds', 'perform' ),
					'desc'      => __( 'Removes WordPress Embed JavaScript file (wp-embed.min.js).', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-embeds'
						)
					),
				)
			);

			// Remove Query Strings for Assets.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_query_strings',
					'type'      => 'checkbox',
					'name'      => __( 'Remove Query Strings', 'perform' ),
					'desc'      => __( 'Remove query strings from static resources (CSS, JS).', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-query-strings'
						)
					),
				)
			);

			// Disable XML-RPC.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_xmlrpc',
					'type'      => 'checkbox',
					'name'      => __( 'Disable XML-RPC', 'perform' ),
					'desc'      => __( 'Disables WordPress XML-RPC functionality.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-xmlrpc'
						)
					),
				)
			);

			// Remove jQuery Migrate.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_jquery_migrate',
					'type'      => 'checkbox',
					'name'      => __( 'Remove jQuery Migrate', 'perform' ),
					'desc'      => __( 'Removes jQuery Migrate JS file (jquery-migrate.min.js).', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-jquery-migrate'
						)
					),
				)
			);

			// Hide WP Version.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'hide_wp_version',
					'type'      => 'checkbox',
					'name'      => __( 'Hide WP Version', 'perform' ),
					'desc'      => __( 'Removes WordPress version generator meta tag.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/hide-wp-version'
						)
					),
				)
			);

			// Remove wlwmanifest Support.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_wlwmanifest_link',
					'type'      => 'checkbox',
					'name'      => __( 'Remove wlwmanifest Link', 'perform' ),
					'desc'      => __( 'Remove wlwmanifest link tag. It is usually used to support Windows Live Writer.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-wlwmanifest-link'
						)
					),
				)
			);

			// Remove RSD Support
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_rsd_link',
					'type'      => 'checkbox',
					'name'      => __( 'Remove RSD Link', 'perform' ),
					'desc'      => __( 'Remove RSD (Real Simple Discovery) link tag.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-rsd-link'
						)
					),
				)
			);

			// Remove Shortlink.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_shortlink',
					'type'      => 'checkbox',
					'name'      => __( 'Remove Shortlink', 'perform' ),
					'desc'      => __( 'Remove Shortlink link tag.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-shortlink'
						)
					),
				)
			);

			// Remove RSS feeds.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_rss_feeds',
					'type'      => 'checkbox',
					'name'      => __( 'Disable RSS Feeds', 'perform' ),
					'desc'      => __( 'Disable WordPress generated RSS feeds and 301 redirect URL to parent.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-rss-feeds'
						)
					),
				)
			);

			// Remove Feed links.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_feed_links',
					'type'      => 'checkbox',
					'name'      => __( 'Remove RSS Feed Links', 'perform' ),
					'desc'      => __( 'Disable WordPress generated RSS feed link tags.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-rss-feed-links'
						)
					),
				)
			);

			// Remove Self Pingbacks.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_self_pingbacks',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Self Pingbacks', 'perform' ),
					'desc'      => __( 'Disable Self Pingbacks (generated when linking to an article on your own blog).', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-self-pingbacks'
						)
					),
				)
			);

			// Remove REST API links.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'remove_rest_api_links',
					'type'      => 'checkbox',
					'name'      => __( 'Remove REST API Links', 'perform' ),
					'desc'      => __( 'Removes REST API link tag from the front end and the REST API header link from page requests.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/remove-rest-api-links'
						)
					),
				)
			);

			// Disable Dashicons JS.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_dashicons',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Dashicons', 'perform' ),
					'desc'      => __( 'Disables dashicons js on the front end when not logged in.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-dashicons'
						)
					),
				)
			);

			// Disable Password Strength Meter.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_password_strength_meter',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Password Strength Meter', 'perform' ),
					'desc'      => __( 'Removes WordPress and WooCommerce Password Strength Meter scripts from non essential pages.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-password-strength-meter'
						)
					),
				)
			);

			// Disable Heartbeat.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'disable_heartbeat',
					'type'      => 'select',
					'name'      => __( 'Disable Heartbeat', 'perform' ),
					'options'   => array(
						''                   => __( 'Default', 'perform' ),
						'disable_everywhere' => __( 'Disable Everywhere', 'perform' ),
						'allow_posts'        => __( 'Only Allow When Editing Posts/Pages', 'perform' ),
					),
					'desc'      => __( 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-heartbeat'
						)
					),
				)
			);

			// Set Heartbeat Frequency.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'heartbeat_frequency',
					'type'      => 'select',
					'name'      => __( 'Heartbeat Frequency', 'perform' ),
					'options'   => array(
						''   => sprintf( __( '%s Seconds', 'perform' ), '15') . ' (' . __( 'Default', 'perform' ) . ')',
						'30' => sprintf( __( '%s Seconds', 'perform' ), '30'),
						'45' => sprintf( __( '%s Seconds', 'perform' ), '45'),
						'60' => sprintf( __( '%s Seconds', 'perform' ), '60'),
					),
					'desc'      => __( 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-heartbeat'
						)
					),
				)
			);

			// Limit Post Revisions.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'        => 'limit_post_revisions',
					'type'      => 'select',
					'name'      => __( 'Limit Post Revisions', 'perform' ),
					'options'   => array(
						''      => __( 'Default', 'perform' ),
						'false' => __( 'Disable Post Revisions', 'perform' ),
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
					),
					'desc'      => __( 'Limits the maximum amount of revisions that are allowed for posts and pages.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/limit-post-revisions'
						)
					),
				)
			);

			// Autosave Interval.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'       => 'autosave_interval',
					'type'     => 'select',
					'name'     => __( 'Autosave Interval', 'perform' ),
					'options'  => array(
						''    => __( '1 Minute', 'perform' ) . ' (' . __( 'Default', 'perform' ) . ')',
						'120' => sprintf( __( '%s Minutes', 'perform' ), '2' ),
						'180' => sprintf( __( '%s Minutes', 'perform' ), '3' ),
						'240' => sprintf( __( '%s Minutes', 'perform' ), '4' ),
						'300' => sprintf( __( '%s Minutes', 'perform' ), '5' )
					),
					'desc'     => __( 'Controls how often WordPress will auto save posts and pages while editing.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/autosave-intervals'
						)
					),
				)
			);

			// Enable SSL.
			$this->add_field(
				"{$this->prefix}ssl",
				array(
					'id'        => 'enable_ssl',
					'type'      => 'checkbox',
					'name'      => __( 'Enable SSL', 'perform' ),
					'desc'      => __( 'Enabling this setting will let you automatically redirect visitors  to the SSL enabled URL of your website.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/auto-ssl-redirect'
						)
					),
				)
			);

			// Disable WooCommerce Scripts.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'        => 'disable_woocommerce_assets',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Default Assets', 'perform' ),
					'desc'      => __( 'Disables WooCommerce default scripts and styles except on product, cart, and checkout pages.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-woocommerce-assets'
						)
					),
				)
			);

			// Disable WooCommerce Cart Fragmentation.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'        => 'disable_woocommerce_cart_fragmentation',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Cart Fragmentation', 'perform' ),
					'desc'      => __( 'Completely disables WooCommerce cart fragmentation script.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-woocommerce-cart-fragmentation'
						)
					),
				)
			);

			// Disable WooCommerce Status Meta-box.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'        => 'disable_woocommerce_status',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Status Meta-box', 'perform' ),
					'desc'      => __( 'Disables WooCommerce status meta-box from the WP Admin Dashboard.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-woocommerce-status'
						)
					),
				)
			);

			// Disable WooCommerce Widgets.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'        => 'disable_woocommerce_widgets',
					'type'      => 'checkbox',
					'name'      => __( 'Disable Widgets', 'perform' ),
					'desc'      => __( 'Disables all WooCommerce widgets.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/disable-widgets'
						)
					),
				)
			);

			// Enable CDN Rewrite.
			$this->add_field(
				"{$this->prefix}cdn",
				array(
					'id'        => 'enable_cdn',
					'type'      => 'checkbox',
					'name'      => __( 'Enable CDN Rewrite', 'perform' ),
					'desc'      => __( 'Enables rewriting of your site URLs with your CDN URLs which can be configured below.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				)
			);

			// CDN URL.
			$this->add_field(
				"{$this->prefix}cdn",
				array(
					'id'        => 'cdn_url',
					'type'      => 'url',
					'name'      => __( 'CDN URL', 'perform' ),
					'desc'      => __( 'Enter your CDN URL without the trailing backslash. Example: https://cdn.example.com', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				)
			);

			// CDN Inclusions.
			$this->add_field(
				"{$this->prefix}cdn",
				array(
					'id'          => 'cdn_directories',
					'type'        => 'text',
					'placeholder' => 'wp-content, wp-includes',
					'name'        => __( 'Included Directories', 'perform' ),
					'desc'        => __( 'Enter any directories you would like to be included in CDN rewriting, separated by commas (,). Default: wp-content,wp-includes', 'perform' ),
					'help_link'   => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				)
			);

			// CDN Exclusions.
			$this->add_field(
				"{$this->prefix}cdn",
				array(
					'id'          => 'cdn_exclusions',
					'type'        => 'text',
					'placeholder' => '.php',
					'name'        => __( 'CDN Exclusions', 'perform' ),
					'desc'        => __( 'Enter any directories or file extensions you would like to be excluded from CDN rewriting, separated by commas (,). Default: .php', 'perform' ),
					'help_link'   => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				)
			);

			// Enable Menu Cache.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'        => 'enable_navigation_menu_cache',
					'type'      => 'checkbox',
					'name'      => __( 'Enable Menu Cache', 'perform' ),
					'desc'      => __( 'Enables the Navigation Menu Cache which will provide you the ability to cache all the menus on your WordPress site to reduce the time taken by outputting the menu\'s.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/navigation-menu-cache'
						)
					),
				)
			);

			// Enable Assets Manager.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'        => 'enable_assets_manager',
					'type'      => 'checkbox',
					'name'      => __( 'Enable Assets Manager', 'perform' ),
					'desc'      => __( 'Enables the Assets Manager which will provide you the ability to enable or disable CSS and JS files on per-page basis.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/assets-manager'
						)
					),
				)
			);

			// DNS Prefetch.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'        => 'dns_prefetch',
					'type'      => 'textarea',
					'name'      => __( 'DNS Prefetch', 'perform' ),
					'desc'      => __( 'Resolve domain names before a user clicks. Format: //domain.tld (one per line)', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/dns-prefetch'
						)
					),
				)
			);

			// DNS Prefetch.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'        => 'preconnect',
					'type'      => 'textarea',
					'name'      => __( 'Preconnect', 'perform' ),
					'desc'      => __( 'Preconnect allows the browser to set up early connections before an HTTP request, eliminating roundtrip latency and saving time for users. Format: scheme://domain.tld (one per line)', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/preconnect'
						)
					),
				)
			);

			// Remove Data on Uninstall.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'        => 'remove_data_on_uninstall',
					'type'      => 'checkbox',
					'name'      => __( 'Remove Data on Uninstall', 'perform' ),
					'desc'      => __( 'When enabled, this will cause all the options data to be removed from your database when the plugin is uninstalled.', 'perform' ),
					'help_link' => esc_url(
						add_query_arg(
							array(
								'utm_source'   => 'admin-settings',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'perform',
							),
							'https://performwp.com/docs/clean-uninstall'
						)
					),
				)
			);

		}
	}
}
