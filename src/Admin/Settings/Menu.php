<?php
/**
 * Perform - Admin Menu
 *
 * @package    Perform
 * @subpackage Admin/Menu
 * @since      2.0.0
 * @author     Mehul Gohil <hello@mehulgohil.com>
 */

namespace Perform\Admin\Settings;

use Perform\Admin\Settings\Api;
use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu extends Api {

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		$this->prefix = 'perform_';
		$this->tabs   = [
			'common'   => esc_html__( 'General', 'perform' ),
			'ssl'      => esc_html__( 'SSL', 'perform' ),
			'cdn'      => esc_html__( 'CDN', 'perform' ),
			'advanced' => esc_html__( 'Advanced', 'perform' ),
			// 'import_export' => __( 'Import/Export', 'perform' ),
			// 'support'       => __( 'Support', 'perform' ),
		];

		// Display WooCommerce tab when WooCommerce plugin is active.
		if ( Helpers::is_woocommerce_active() ) {
			$this->tabs['woocommerce'] = esc_html__( 'WooCommerce', 'perform' );
		}

		$this->add_tabs();
		$this->add_fields();

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'in_admin_header', [ $this, 'render_settings_page_header' ] );
	}

	/**
	 * Register Admin Menu
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_options_page(
			esc_html__( 'Perform', 'perform' ),
			esc_html__( 'Perform', 'perform' ),
			'manage_options',
			'perform_settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Render Settings Page Header.
	 *
	 * @since  1.4.0
	 * @access public
	 *
	 * @return void|mixed
	 */
	public function render_settings_page_header() {
		$screen = get_current_screen();

		// Bailout, if screen id doesn't match.
		if ( 'settings_page_perform_settings' !== $screen->id ) {
			return;
		}
		?>
		<div class="perform-dashboard-header">
			<div class="perform-dashboard-header-title">
				<img src="<?php echo PERFORM_PLUGIN_URL . 'assets/dist/images/logo.png'; ?>" alt="<?php esc_html_e( 'PerformWP', 'perform' ); ?>"/>
			</div>
			<?php $this->render_header_navigation(); ?>
		</div>
		<?php
	}

	/**
	 * Render Header Navigation.
	 *
	 * @since  1.4.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_header_navigation() {
		$screen      = get_current_screen();
		$current_tab = ! empty( $_GET['tab'] ) ? $_GET['tab'] : '';
		$tabs        = apply_filters(
			'perform_settings_navigation_tabs',
			[
				'general'      => [
					'name'  => esc_html__( 'General', 'perform' ),
					'url'   => admin_url( 'options-general.php?page=perform_settings' ),
					'class' => 'settings_page_perform_settings' === $screen->id && '' === $current_tab ? 'active' : '',
				],
				'advanced'     => [
					'name'  => esc_html__( 'Advanced', 'perform' ),
					'url'   => admin_url( 'options-general.php?page=perform_settings&tab=advanced' ),
					'class' => 'settings_page_perform_settings' === $screen->id && 'advanced' === $current_tab ? 'active' : '',
				],
				'tools'        => [
					'name'  => esc_html__( 'Tools', 'perform' ),
					'url'   => admin_url( 'options-general.php?page=perform_settings&tab=tools' ),
					'class' => 'settings_page_perform_settings' === $screen->id && 'tools' === $current_tab ? 'active' : '',
				],
				'experimental' => [
					'name'  => esc_html__( 'Experimental', 'perform' ),
					'url'   => admin_url( 'options-general.php?page=perform_settings&tab=experimental' ),
					'class' => 'settings_page_perform_settings' === $screen->id && 'experimental' === $current_tab ? 'active' : '',
				],
			],
		);

		// Don't print any markup if we only have one tab.
		if ( count( $tabs ) === 1 ) {
			return;
		}
		?>
		<div class="perform-header-navigation">
			<?php
			foreach ( $tabs as $tab ) {
				printf(
					'<a href="%1$s" class="%2$s">%3$s</a>',
					esc_url( $tab['url'] ),
					esc_attr( $tab['class'] ),
					esc_html( $tab['name'] )
				);
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render Settings Page.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_settings_page() {
		?>
		<div class="wrap perform-admin-settings-wrap">
			<div class="perform-admin-settings--left-section">
				<?php $this->render_left_section(); ?>
			</div>
			<div class="perform-admin-settings--right-section">
				<?php $this->render_right_section(); ?>
			</div>
			<?php // $this->navigation_html(); ?>
			<?php $this->display_form(); ?>
		</div>
		<?php
	}

	/**
	 * Render `Left Section` of the admin settings.
	 *
	 * This section will display the admin settings fields.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_left_section() {

	}

	/**
	 * Render `Right Section` of the admin settings.
	 *
	 * This section will display the help area to ensure better utilizing of the plugin.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_right_section() {

	}

	/**
	 * Add Tabs
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function add_tabs() {
		foreach ( $this->tabs as $slug => $name ) {
			$this->add_section(
				[
					'id'    => $this->prefix . $slug,
					'title' => $name,
				]
			);
		}
	}

	/**
	 * Add Fields.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function add_fields() {
		// Disable Emoji's.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_emojis',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Emoji\'s', 'perform' ),
				'desc'      => esc_html__( 'Enabling this will disable the usage of emoji\'s in WordPress Posts, Pages, and Custom Post Types.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-emojis'
					)
				),
			]
		);

		// Disable Embeds.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_embeds',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Embeds', 'perform' ),
				'desc'      => esc_html__( 'Removes WordPress Embed JavaScript file (wp-embed.min.js).', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-embeds'
					)
				),
			]
		);

		// Remove Query Strings for Assets.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_query_strings',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove Query Strings', 'perform' ),
				'desc'      => esc_html__( 'Remove query strings from static resources (CSS, JS).', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-query-strings'
					)
				),
			]
		);

		// Disable XML-RPC.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_xmlrpc',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable XML-RPC', 'perform' ),
				'desc'      => esc_html__( 'Disables WordPress XML-RPC functionality.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-xmlrpc'
					)
				),
			]
		);

		// Remove jQuery Migrate.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_jquery_migrate',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove jQuery Migrate', 'perform' ),
				'desc'      => esc_html__( 'Removes jQuery Migrate JS file (jquery-migrate.min.js).', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-jquery-migrate'
					)
				),
			]
		);

		// Hide WP Version.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'hide_wp_version',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Hide WP Version', 'perform' ),
				'desc'      => esc_html__( 'Removes WordPress version generator meta tag.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/hide-wp-version'
					)
				),
			]
		);

		// Remove wlwmanifest Support.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_wlwmanifest_link',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove wlwmanifest Link', 'perform' ),
				'desc'      => esc_html__( 'Remove wlwmanifest link tag. It is usually used to support Windows Live Writer.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-wlwmanifest-link'
					)
				),
			]
		);

		// Remove RSD Support
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_rsd_link',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove RSD Link', 'perform' ),
				'desc'      => esc_html__( 'Remove RSD (Real Simple Discovery) link tag.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-rsd-link'
					)
				),
			]
		);

		// Remove Shortlink.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_shortlink',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove Shortlink', 'perform' ),
				'desc'      => esc_html__( 'Remove Shortlink link tag.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-shortlink'
					)
				),
			]
		);

		// Remove RSS feeds.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_rss_feeds',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable RSS Feeds', 'perform' ),
				'desc'      => esc_html__( 'Disable WordPress generated RSS feeds and 301 redirect URL to parent.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-rss-feeds'
					)
				),
			]
		);

		// Remove Feed links.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_feed_links',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove RSS Feed Links', 'perform' ),
				'desc'      => esc_html__( 'Disable WordPress generated RSS feed link tags.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-rss-feed-links'
					)
				),
			]
		);

		// Remove Self Pingbacks.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_self_pingbacks',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Self Pingbacks', 'perform' ),
				'desc'      => esc_html__( 'Disable Self Pingbacks (generated when linking to an article on your own blog).', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-self-pingbacks'
					)
				),
			]
		);

		// Remove REST API links.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'remove_rest_api_links',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove REST API Links', 'perform' ),
				'desc'      => esc_html__( 'Removes REST API link tag from the front end and the REST API header link from page requests.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/remove-rest-api-links'
					)
				),
			]
		);

		// Disable Dashicons JS.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_dashicons',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Dashicons', 'perform' ),
				'desc'      => esc_html__( 'Disables dashicons js on the front end when not logged in.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-dashicons'
					)
				),
			]
		);

		// Disable Password Strength Meter.
		$this->add_field(
			"{$this->prefix}common",
			[
				'id'        => 'disable_password_strength_meter',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Password Strength Meter', 'perform' ),
				'desc'      => esc_html__( 'Removes WordPress and WooCommerce Password Strength Meter scripts from non essential pages.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-password-strength-meter'
					)
				),
			]
		);

		// Disable Heartbeat.
		$this->add_field(
			"{$this->prefix}common",
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
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-heartbeat'
					)
				),
			]
		);

		// Set Heartbeat Frequency.
		$this->add_field(
			"{$this->prefix}common",
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
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-heartbeat'
					)
				),
			]
		);

		// Limit Post Revisions.
		$this->add_field(
			"{$this->prefix}common",
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
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/limit-post-revisions'
					)
				),
			]
		);

		// Autosave Interval.
		$this->add_field(
			"{$this->prefix}common",
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
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/autosave-intervals'
					)
				),
			]
		);

		// Enable SSL.
		$this->add_field(
			"{$this->prefix}ssl",
			[
				'id'        => 'enable_ssl',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Enable SSL', 'perform' ),
				'desc'      => esc_html__( 'Enabling this setting will let you automatically redirect visitors  to the SSL enabled URL of your website.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/auto-ssl-redirect'
					)
				),
			]
		);

		// Disable WooCommerce Scripts.
		$this->add_field(
			"{$this->prefix}woocommerce",
			[
				'id'        => 'disable_woocommerce_assets',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Default Assets', 'perform' ),
				'desc'      => esc_html__( 'Disables WooCommerce default scripts and styles except on product, cart, and checkout pages.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-woocommerce-assets'
					)
				),
			]
		);

		// Disable WooCommerce Cart Fragmentation.
		$this->add_field(
			"{$this->prefix}woocommerce",
			[
				'id'        => 'disable_woocommerce_cart_fragmentation',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Cart Fragmentation', 'perform' ),
				'desc'      => esc_html__( 'Completely disables WooCommerce cart fragmentation script.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-woocommerce-cart-fragmentation'
					)
				),
			]
		);

		// Disable WooCommerce Status Meta-box.
		$this->add_field(
			"{$this->prefix}woocommerce",
			[
				'id'        => 'disable_woocommerce_status',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Status Meta-box', 'perform' ),
				'desc'      => esc_html__( 'Disables WooCommerce status meta-box from the WP Admin Dashboard.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-woocommerce-status'
					)
				),
			]
		);

		// Disable WooCommerce Widgets.
		$this->add_field(
			"{$this->prefix}woocommerce",
			[
				'id'        => 'disable_woocommerce_widgets',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Disable Widgets', 'perform' ),
				'desc'      => esc_html__( 'Disables all WooCommerce widgets.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/disable-widgets'
					)
				),
			]
		);

		// Enable CDN Rewrite.
		$this->add_field(
			"{$this->prefix}cdn",
			[
				'id'        => 'enable_cdn',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Enable CDN Rewrite', 'perform' ),
				'desc'      => esc_html__( 'Enables rewriting of your site URLs with your CDN URLs which can be configured below.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/cdn-rewrite'
					)
				),
			]
		);

		// CDN URL.
		$this->add_field(
			"{$this->prefix}cdn",
			[
				'id'        => 'cdn_url',
				'type'      => 'url',
				'name'      => esc_html__( 'CDN URL', 'perform' ),
				'desc'      => esc_html__( 'Enter your CDN URL without the trailing backslash. Example: https://cdn.example.com', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/cdn-rewrite'
					)
				),
			]
		);

		// CDN Inclusions.
		$this->add_field(
			"{$this->prefix}cdn",
			[
				'id'          => 'cdn_directories',
				'type'        => 'text',
				'placeholder' => 'wp-content, wp-includes',
				'name'        => esc_html__( 'Included Directories', 'perform' ),
				'desc'        => esc_html__( 'Enter any directories you would like to be included in CDN rewriting, separated by commas (,). Default: wp-content,wp-includes', 'perform' ),
				'help_link'   => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/cdn-rewrite'
					)
				),
			]
		);

		// CDN Exclusions.
		$this->add_field(
			"{$this->prefix}cdn",
			[
				'id'          => 'cdn_exclusions',
				'type'        => 'text',
				'placeholder' => '.php',
				'name'        => esc_html__( 'CDN Exclusions', 'perform' ),
				'desc'        => esc_html__( 'Enter any directories or file extensions you would like to be excluded from CDN rewriting, separated by commas (,). Default: .php', 'perform' ),
				'help_link'   => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/cdn-rewrite'
					)
				),
			]
		);

		// Enable Menu Cache.
		$this->add_field(
			"{$this->prefix}advanced",
			[
				'id'        => 'enable_navigation_menu_cache',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Enable Menu Cache', 'perform' ),
				'desc'      => esc_html__( 'Enables the Navigation Menu Cache which will provide you the ability to cache all the menus on your WordPress site to reduce the time taken by outputting the menu\'s.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/navigation-menu-cache'
					)
				),
			]
		);

		// Enable Assets Manager.
		$this->add_field(
			"{$this->prefix}advanced",
			[
				'id'        => 'enable_assets_manager',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Enable Assets Manager', 'perform' ),
				'desc'      => esc_html__( 'Enables the Assets Manager which will provide you the ability to enable or disable CSS and JS files on per-page basis.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/assets-manager'
					)
				),
			]
		);

		// DNS Prefetch.
		$this->add_field(
			"{$this->prefix}advanced",
			[
				'id'        => 'dns_prefetch',
				'type'      => 'textarea',
				'data_type' => 'one_per_line',
				'name'      => esc_html__( 'DNS Prefetch', 'perform' ),
				'desc'      => esc_html__( 'Resolve domain names before a user clicks. Format: //domain.tld (one per line)', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/dns-prefetch'
					)
				),
			]
		);

		// DNS Prefetch.
		$this->add_field(
			"{$this->prefix}advanced",
			[
				'id'        => 'preconnect',
				'type'      => 'textarea',
				'name'      => esc_html__( 'Preconnect', 'perform' ),
				'desc'      => esc_html__( 'Preconnect allows the browser to set up early connections before an HTTP request, eliminating roundtrip latency and saving time for users. Format: scheme://domain.tld (one per line)', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/preconnect'
					)
				),
			]
		);

		// Remove Data on Uninstall.
		$this->add_field(
			"{$this->prefix}advanced",
			[
				'id'        => 'remove_data_on_uninstall',
				'type'      => 'checkbox',
				'name'      => esc_html__( 'Remove Data on Uninstall', 'perform' ),
				'desc'      => esc_html__( 'When enabled, this will cause all the options data to be removed from your database when the plugin is uninstalled.', 'perform' ),
				'help_link' => esc_url(
					add_query_arg(
						[
							'utm_source'   => 'admin-settings',
							'utm_medium'   => 'plugin',
							'utm_campaign' => 'perform',
						],
						'https://performwp.com/docs/clean-uninstall'
					)
				),
			]
		);

	}
}
