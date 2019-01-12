<?php
/**
 * Admin Settings
 *
 * @since 1.0.0
 */

// Bailout, if accessed directly.
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
		    $this->tabs = array(
				'common'        => __( 'General', 'perform' ),
				'ssl'           => __( 'SSL', 'perform' ),
				'cdn'           => __( 'CDN', 'perform' ),
				'woocommerce'   => __( 'WooCommerce', 'perform' ),
				'advanced'      => __( 'Advanced', 'perform' ),
				'import_export' => __( 'Import/Export', 'perform' ),
				'support'       => __( 'Support', 'perform' ),
			);

			$this->add_tabs();
			$this->add_fields();


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
					'id'      => 'disable_emojis',
					'type'    => 'checkbox',
					'name'    => __( 'Disable Emoji\'s', 'perform' ),
					'desc'    => __( 'Enabling this will disabled the usage of emoji\'s in WordPress Posts, Pages, and Custom Post Types.', 'perform' ),
				)
			);

			// Disable Embeds.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'disable_embeds',
					'type'    => 'checkbox',
					'name'    => __( 'Disable Embeds', 'perform' ),
					'desc'    => __( 'Removes WordPress Embed JavaScript file (wp-embed.min.js).', 'perform' ),
				)
			);

			// Remove Query Strings for Assets.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'remove_query_strings',
					'type'    => 'checkbox',
					'name'    => __( 'Remove Query Strings', 'perform' ),
					'desc'    => __( 'Remove query strings from static resources (CSS, JS).', 'perform' ),
				)
			);

			// Disable XML-RPC.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'disable_xmlrpc',
					'type'    => 'checkbox',
					'name'    => __( 'Disable XML-RPC', 'perform' ),
					'desc'    => __( 'Disables WordPress XML-RPC functionality.', 'perform' ),
				)
			);

			// Remove jQuery Migrate.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'remove_jquery_migrate',
					'type'    => 'checkbox',
					'name'    => __( 'Remove jQuery Migrate', 'perform' ),
					'desc'    => __( 'Removes jQuery Migrate JS file (jquery-migrate.min.js).', 'perform' ),
				)
			);

			// Hide WP Version.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'hide_wp_version',
					'type'    => 'checkbox',
					'name'    => __( 'Hide WP Version', 'perform' ),
					'desc'    => __( 'Removes WordPress version generator meta tag.', 'perform' ),
				)
			);

			// Remove wlwmanifest Support.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'remove_wlwmanifest_link',
					'type'    => 'checkbox',
					'name'    => __( 'Remove wlwmanifest Link', 'perform' ),
					'desc'    => __( 'Remove wlwmanifest link tag. It is usually used to support Windows Live Writer.', 'perform' ),
				)
			);

			// Remove RSD Support
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'remove_rsd_link',
					'type'    => 'checkbox',
					'name'    => __( 'Remove RSD Link', 'perform' ),
					'desc'    => __( 'Remove RSD (Real Simple Discovery) link tag.', 'perform' ),
				)
			);

			// Remove Shortlink.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'remove_shortlink',
					'type'    => 'checkbox',
					'name'    => __( 'Remove Shortlink', 'perform' ),
					'desc'    => __( 'Remove Shortlink link tag.', 'perform' ),
				)
			);

			// Remove RSS feeds.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'   => 'disable_rss_feeds',
					'type' => 'checkbox',
					'name' => __( 'Disable RSS Feeds', 'perform' ),
					'desc' => __( 'Disable WordPress generated RSS feeds and 301 redirect URL to parent.', 'perform' ),
				)
			);

			// Remove Feed links.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'   => 'remove_feed_links',
					'type' => 'checkbox',
					'name' => __( 'Remove RSS Feed Links', 'perform' ),
					'desc' => __( 'Disable WordPress generated RSS feed link tags.', 'perform' ),
				)
			);

			// Remove Self Pingbacks.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'   => 'disable_self_pingbacks',
					'type' => 'checkbox',
					'name' => __( 'Disable Self Pingbacks', 'perform' ),
					'desc' => __( 'Disable Self Pingbacks (generated when linking to an article on your own blog).', 'perform' ),
				)
			);

			// Remove REST API links.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'   => 'remove_rest_api_links',
					'type' => 'checkbox',
					'name' => __( 'Remove REST API Links', 'perform' ),
					'desc' => __( 'Removes REST API link tag from the front end and the REST API header link from page requests.', 'perform' ),
				)
			);

			// Disable Dashicons JS.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'   => 'disable_dashicons',
					'type' => 'checkbox',
					'name' => __( 'Disable Dashicons', 'perform' ),
					'desc' => __( 'Disables dashicons js on the front end when not logged in.', 'perform' ),
				)
			);

			// Disable Password Strength Meter.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'   => 'disable_password_strength_meter',
					'type' => 'checkbox',
					'name' => __( 'Disable Password Strength Meter', 'perform' ),
					'desc' => __( 'Removes WordPress and WooCommerce Password Strength Meter scripts from non essential pages.', 'perform' ),
				)
			);

			// Disable Heartbeat.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'disable_heartbeat',
					'type'    => 'select',
					'name'    => __( 'Disable Heartbeat', 'perform' ),
					'options' => array(
						''                   => __('Default', 'perform'),
						'disable_everywhere' => __('Disable Everywhere', 'perform'),
						'allow_posts'        => __('Only Allow When Editing Posts/Pages', 'perform'),
					),
					'desc'    => __( 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).', 'perform' ),
				)
			);

			// Set Heartbeat Frequency.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'heartbeat_frequency',
					'type'    => 'select',
					'name'    => __( 'Heartbeat Frequency', 'perform' ),
					'options' => array(
						''   => sprintf(__('%s Seconds', 'perform'), '15') . ' (' . __('Default', 'perform') . ')',
						'30' => sprintf(__('%s Seconds', 'perform'), '30'),
						'45' => sprintf(__('%s Seconds', 'perform'), '45'),
						'60' => sprintf(__('%s Seconds', 'perform'), '60'),
					),
					'desc'    => __( 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).', 'perform' ),
				)
			);

			// Limit Post Revisions.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'limit_post_revisions',
					'type'    => 'select',
					'name'    => __( 'Limit Post Revisions', 'perform' ),
					'options' => array(
						''      => __( 'Default', 'perform'),
						'false' => __( 'Disable Post Revisions', 'perform'),
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
					'desc'    => __( 'Limits the maximum amount of revisions that are allowed for posts and pages.', 'perform' ),
				)
			);

			// Autosave Interval.
			$this->add_field(
				"{$this->prefix}common",
				array(
					'id'      => 'autosave_interval',
					'type'    => 'select',
					'name'    => __( 'Autosave Interval', 'perform' ),
					'options' => array(
						''    => __('1 Minute', 'perform') . ' (' . __('Default', 'perform') . ')',
						'120' => sprintf(__('%s Minutes', 'perform'), '2'),
						'180' => sprintf(__('%s Minutes', 'perform'), '3'),
						'240' => sprintf(__('%s Minutes', 'perform'), '4'),
						'300' => sprintf(__('%s Minutes', 'perform'), '5')
					),
					'desc'    => __( 'Controls how often WordPress will auto save posts and pages while editing.', 'perform' ),
				)
			);

			// Change Login URL.
//			$this->add_field(
//				"{$this->prefix}common",
//				array(
//					'id'   => 'login_url',
//					'type' => 'url',
//					'name' => __( 'Change Login URL', 'perform' ),
//					'desc' => __( 'When set, this will change your WordPress login URL (slug) to the provided string and will block wp-admin and wp-login endpoints from being directly accessed.', 'perform' ),
//				)
//			);

			// Enable SSL.
			$this->add_field(
				"{$this->prefix}ssl",
				array(
					'id'   => 'enable_ssl',
					'type' => 'checkbox',
					'name' => __( 'Enable SSL', 'perform' ),
					'desc' => __( 'Enabling this setting will let you automatically redirect visitors  to the SSL enabled URL of your website.', 'perform' ),
				)
			);

			// Disable WooCommerce Scripts.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'   => 'disable_woocommerce_assets',
					'type' => 'checkbox',
					'name' => __( 'Disable Default Assets', 'perform' ),
					'desc' => __( 'Disables WooCommerce default scripts and styles except on product, cart, and checkout pages.', 'perform' ),
				)
			);

			// Disable WooCommerce Cart Fragmentation.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'   => 'disable_woocommerce_cart_fragmentation',
					'type' => 'checkbox',
					'name' => __( 'Disable Cart Fragmentation', 'perform' ),
					'desc' => __( 'Completely disables WooCommerce cart fragmentation script.', 'perform' ),
				)
			);

			// Disable WooCommerce Status Meta-box.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'   => 'disable_woocommerce_status',
					'type' => 'checkbox',
					'name' => __( 'Disable Status Meta-box', 'perform' ),
					'desc' => __( 'Disables WooCommerce status meta-box from the WP Admin Dashboard.', 'perform' ),
				)
			);

			// Disable WooCommerce Widgets.
			$this->add_field(
				"{$this->prefix}woocommerce",
				array(
					'id'   => 'disable_woocommerce_widgets',
					'type' => 'checkbox',
					'name' => __( 'Disable Widgets', 'perform' ),
					'desc' => __( 'Disables all WooCommerce widgets.', 'perform' ),
				)
			);

			// Enable CDN Rewrite.
			$this->add_field(
				"{$this->prefix}cdn",
				array(
					'id'   => 'enable_cdn',
					'type' => 'checkbox',
					'name' => __( 'Enable CDN Rewrite', 'perform' ),
					'desc' => __( 'Enables rewriting of your site URLs with your CDN URLs which can be configured below.', 'perform' ),
				)
			);

			// CDN URL.
			$this->add_field(
				"{$this->prefix}cdn",
				array(
					'id'   => 'cdn_url',
					'type' => 'url',
					'name' => __( 'CDN URL', 'perform' ),
					'desc' => __( 'Enter your CDN URL without the trailing backslash. Example: https://cdn.example.com', 'perform' ),
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
				)
			);


			// Enable Assets Manager.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'   => 'enable_assets_manager',
					'type' => 'checkbox',
					'name' => __( 'Enable Assets Manager', 'perform' ),
					'desc' => __( 'Enables the Assets Manager which will provide you the ability to enable or disable CSS and JS files on per-page basis.', 'perform' ),
				)
			);

			// DNS Prefetch.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'   => 'dns_prefetch',
					'type' => 'textarea',
					'name' => __( 'DNS Prefetch', 'perform' ),
					'desc' => __( 'Resolve domain names before a user clicks. Format: //domain.tld (one per line)', 'perform' ),
				)
			);

			// DNS Prefetch.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'   => 'preconnect',
					'type' => 'textarea',
					'name' => __( 'Preconnect', 'perform' ),
					'desc' => __( 'Preconnect allows the browser to set up early connections before an HTTP request, eliminating roundtrip latency and saving time for users. Format: scheme://domain.tld (one per line)', 'perform' ),
				)
			);

			// Remove Data on Uninstall.
			$this->add_field(
				"{$this->prefix}advanced",
				array(
					'id'   => 'remove_data_on_uninstall',
					'type' => 'checkbox',
					'name' => __( 'Remove Data on Uninstall', 'perform' ),
					'desc' => __( 'When enabled, this will cause all the options data to be removed from your database when the plugin is uninstalled.', 'perform' ),
				)
			);

		}

	}
}