<?php
/**
 * Perform - Admin Menu
 * Optimized and enhanced for better performance and maintainability.
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
	 * Tabs for the settings page.
	 * 
	 * @since 2.0.0
	 * @access public
	 * 
	 * @var array
	 */
	public $tabs = [];

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->prefix = 'perform_';
		$this->tabs   = $this->initialize_tabs();

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'in_admin_header', [ $this, 'render_settings_page_header' ] );
		add_action( 'wp_ajax_perform_save_settings', [ $this, 'save_settings' ] );
	}

	/**
	 * Initialize tabs for the settings page.
	 *
	 * @return array
	 */
	private function initialize_tabs() {
		$tabs = [
			'general'  => 'General',
			'ssl'      => 'SSL',
			'cdn'      => 'CDN',
			'advanced' => 'Advanced',
		];
	
		if ( Helpers::is_woocommerce_active() ) {
			$tabs['woocommerce'] = 'WooCommerce';
		}
	
		return $tabs;
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
	 * @return void
	 */
	public function render_settings_page_header() {
		$screen = get_current_screen();

		if ( 'settings_page_perform_settings' !== $screen->id ) {
			return;
		}
		?>
		<div class="perform-dashboard-header">
			<div class="perform-dashboard-header-title">
				<img src="<?php echo esc_url( PERFORM_PLUGIN_URL . 'assets/dist/images/logo.png' ); ?>" alt="<?php esc_attr_e( 'PerformWP', 'perform' ); ?>"/>
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
		$current_tab = Helpers::get_current_tab();
		$tabs        = apply_filters( 'perform_settings_navigation_tabs', $this->tabs );
	
		if ( count( $tabs ) === 1 ) {
			return;
		}
		?>
		<ul class="perform-header-navigation">
			<?php foreach ( $tabs as $slug => $tab ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=perform_settings&tab=' . $slug ) ); ?>" class="<?php echo esc_attr( $slug === $current_tab ? 'active' : '' ); ?>">
						<?php echo esc_html__( $tab, 'perform' ); // Translate here ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
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
		$utm_args = [
			'utm_source'   => 'admin-settings',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'perform',
		];

		$fields = [
			'general'     => [
				[
					'id'        => 'disable_emojis',
					'type'      => 'checkbox',
					'name'      => 'Disable Emoji\'s',
					'desc'      => 'Enabling this will disable the usage of emoji\'s in WordPress Posts, Pages, and Custom Post Types.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-emojis'
						)
					),
				],
				[
					'id'        => 'disable_embeds',
					'type'      => 'checkbox',
					'name'      => 'Disable Embeds',
					'desc'      => 'Removes WordPress Embed JavaScript file (wp-embed.min.js).',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-embeds'
						)
					),
				],
				[
					'id'        => 'remove_query_strings',
					'type'      => 'checkbox',
					'name'      => 'Remove Query Strings',
					'desc'      => 'Remove query strings from static resources (CSS, JS).',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-query-strings'
						)
					),
				],
				[
					'id'        => 'disable_xmlrpc',
					'type'      => 'checkbox',
					'name'      => 'Disable XML-RPC',
					'desc'      => 'Disables WordPress XML-RPC functionality.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-xmlrpc'
						)
					),
				],
				[
					'id'        => 'remove_jquery_migrate',
					'type'      => 'checkbox',
					'name'      => 'Remove jQuery Migrate',
					'desc'      => 'Removes jQuery Migrate JS file (jquery-migrate.min.js).',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-jquery-migrate'
						)
					),
				],
				[
					'id'        => 'hide_wp_version',
					'type'      => 'checkbox',
					'name'      => 'Hide WP Version',
					'desc'      => 'Removes WordPress version generator meta tag.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/hide-wp-version'
						)
					),
				],
				[
					'id'        => 'remove_wlwmanifest_link',
					'type'      => 'checkbox',
					'name'      => 'Remove wlwmanifest Link',
					'desc'      => 'Remove wlwmanifest link tag. It is usually used to support Windows Live Writer.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-wlwmanifest-link'
						)
					),
				],
				[
					'id'        => 'remove_rsd_link',
					'type'      => 'checkbox',
					'name'      => 'Remove RSD Link',
					'desc'      => 'Remove RSD (Real Simple Discovery) link tag.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-rsd-link'
						)
					),
				],
				[
					'id'        => 'remove_shortlink',
					'type'      => 'checkbox',
					'name'      => 'Remove Shortlink',
					'desc'      => 'Remove Shortlink link tag.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-shortlink'
						)
					),
				],
				[
					'id'        => 'disable_rss_feeds',
					'type'      => 'checkbox',
					'name'      => 'Disable RSS Feeds',
					'desc'      => 'Disable WordPress generated RSS feeds and 301 redirect URL to parent.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-rss-feeds'
						)
					),
				],
				[
					'id'        => 'remove_feed_links',
					'type'      => 'checkbox',
					'name'      => 'Remove RSS Feed Links',
					'desc'      => 'Disable WordPress generated RSS feed link tags.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-rss-feed-links'
						)
					),
				],
				[
					'id'        => 'remove_rest_api_links',
					'type'      => 'checkbox',
					'name'      => 'Remove REST API Links',
					'desc'      => 'Removes REST API link tag from the front end and the REST API header link from page requests.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/remove-rest-api-links'
						)
					),
				],
				[
					'id'        => 'disable_self_pingbacks',
					'type'      => 'checkbox',
					'name'      => 'Disable Self Pingbacks',
					'desc'      => 'Disable Self Pingbacks (generated when linking to an article on your own blog).',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-self-pingbacks'
						)
					),
				],
				[
					'id'        => 'disable_dashicons',
					'type'      => 'checkbox',
					'name'      => 'Disable Dashicons',
					'desc'      => 'Disables dashicons js on the front end when not logged in.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-dashicons'
						)
					),
				],
				[
					'id'        => 'disable_password_strength_meter',
					'type'      => 'checkbox',
					'name'      => 'Disable Password Strength Meter',
					'desc'      => 'Removes WordPress and WooCommerce Password Strength Meter scripts from non essential pages.',
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
					'name'      => 'Disable Heartbeat',
					'options'   => [
						''                   => 'Default',
						'disable_everywhere' => 'Disable Everywhere',
						'allow_posts'        => 'Only Allow When Editing Posts/Pages',
					],
					'desc'      => 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).',
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
					'name'      => 'Heartbeat Frequency',
					'options'   => [
						''   => '15 Seconds (Default)',
						'30' => '30 Seconds',
						'45' => '45 Seconds',
						'60' => '60 Seconds',
					],
					'desc'      => 'Disable WordPress Heartbeat everywhere or in certain areas (used for auto saving and revision tracking).',
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
					'name'      => 'Limit Post Revisions',
					'options'   => [
						''      => 'Default',
						'false' => 'Disable Post Revisions',
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
					'desc'      => 'Limits the maximum amount of revisions that are allowed for posts and pages.',
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
					'name'      => 'Autosave Interval',
					'options'   => [
						''    => '1 Minute (Default)',
						'120' => '2 Minutes',
						'180' => '3 Minutes',
						'240' => '4 Minutes',
						'300' => '5 Minutes',
					],
					'desc'      => 'Controls how often WordPress will auto save posts and pages while editing.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/autosave-intervals'
						)
					),
				],
			],
			'ssl'         => [
				[
					'id'        => 'enable_ssl',
					'type'      => 'checkbox',
					'name'      => 'Enable SSL',
					'desc'      => 'Enabling this setting will let you automatically redirect visitors  to the SSL enabled URL of your website.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/auto-ssl-redirect'
						)
					),
				],
			],
			'cdn'         => [
				[
					'id'        => 'enable_cdn',
					'type'      => 'checkbox',
					'name'      => 'Enable CDN Rewrite',
					'desc'      => 'Enables rewriting of your site URLs with your CDN URLs which can be configured below.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				],
				[
					'id'        => 'cdn_url',
					'type'      => 'url',
					'name'      => 'CDN URL',
					'desc'      => 'Enter your CDN URL without the trailing backslash. Example: https://cdn.example.com',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				],
				[
					'id'          => 'cdn_directories',
					'type'        => 'text',
					'placeholder' => 'wp-content, wp-includes',
					'name'        => 'Included Directories',
					'desc'        => 'Enter any directories you would like to be included in CDN rewriting, separated by commas (,). Default: wp-content,wp-includes',
					'help_link'   => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				],
				[
					'id'          => 'cdn_exclusions',
					'type'        => 'text',
					'placeholder' => '.php',
					'name'        => 'CDN Exclusions',
					'desc'        => 'Enter any directories or file extensions you would like to be excluded from CDN rewriting, separated by commas (,). Default: .php',
					'help_link'   => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/cdn-rewrite'
						)
					),
				],
			],
			'advanced'    => [
				[
					'id'        => 'enable_navigation_menu_cache',
					'type'      => 'checkbox',
					'name'      => 'Enable Menu Cache',
					'desc'      => 'Enables the Navigation Menu Cache which will provide you the ability to cache all the menus on your WordPress site to reduce the time taken by outputting the menu\'s.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/navigation-menu-cache'
						)
					),
				],
				[
					'id'        => 'enable_assets_manager',
					'type'      => 'checkbox',
					'name'      => 'Enable Assets Manager',
					'desc'      => 'Enables the Assets Manager which will provide you the ability to enable or disable CSS and JS files on per-page basis.',
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
					'data_type' => 'one_per_line',
					'name'      => 'DNS Prefetch',
					'desc'      => 'Resolve domain names before a user clicks. Format: //domain.tld (one per line)',
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
					'name'      => 'Preconnect',
					'desc'      => 'Preconnect allows the browser to set up early connections before an HTTP request, eliminating roundtrip latency and saving time for users. Format: scheme://domain.tld (one per line)',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/preconnect'
						)
					),
				],
				[
					'id'        => 'remove_data_on_uninstall',
					'type'      => 'checkbox',
					'name'      => 'Remove Data on Uninstall',
					'desc'      => 'When enabled, this will cause all the options data to be removed from your database when the plugin is uninstalled.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/clean-uninstall'
						)
					),
				],
			],
			'woocommerce' => [
				[
					'id'        => 'disable_woocommerce_assets',
					'type'      => 'checkbox',
					'name'      => 'Disable Default Assets',
					'desc'      => 'Disables WooCommerce default scripts and styles except on product, cart, and checkout pages.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-woocommerce-assets'
						)
					),
				],
				[
					'id'        => 'disable_woocommerce_cart_fragmentation',
					'type'      => 'checkbox',
					'name'      => 'Disable Cart Fragmentation',
					'desc'      => 'Completely disables WooCommerce cart fragmentation script.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-woocommerce-cart-fragmentation'
						)
					),
				],
				[
					'id'        => 'disable_woocommerce_status',
					'type'      => 'checkbox',
					'name'      => 'Disable Status Meta-box',
					'desc'      => 'Disables WooCommerce status meta-box from the WP Admin Dashboard.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-woocommerce-status'
						)
					),
				],
				[
					'id'        => 'disable_woocommerce_widgets',
					'type'      => 'checkbox',
					'name'      => 'Disable Widgets',
					'desc'      => 'Disables all WooCommerce widgets.',
					'help_link' => esc_url(
						add_query_arg(
							$utm_args,
							'https://performwp.com/docs/disable-widgets'
						)
					),
				],
			],

		];

		$this->render_fields( $fields );
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
		ob_start();

		$check_our_documentation = esc_html__( 'Check our documentation', 'perform' );
		?>
		<div class="performwp-sidebar-section">
			<a href="https://performwp.com/docs/" title="<?php echo $check_our_documentation; ?>" target="_blank">
				<img src="<?php echo PERFORM_PLUGIN_URL . 'assets/dist/images/check-our-documentation.svg'; ?>" alt="<?php echo $check_our_documentation; ?>"/>
			</a>
		</div>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Save Admin Settings.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function save_settings() {
		$posted_data = Helpers::clean( $_POST );
		$settings    = Helpers::get_settings();

		$new_settings = wp_parse_args( $posted_data, $settings );

		$new_settings['dns_prefetch'] = ! empty( $new_settings['dns_prefetch'] ) ? explode( "\n", $new_settings['dns_prefetch'] ) : '';
		$new_settings['preconnect']   = ! empty( $new_settings['preconnect'] ) ? explode( "\n", $new_settings['preconnect'] ) : '';

		unset( $new_settings['perform_settings_barrier'], $new_settings['_wp_http_referer'], $new_settings['action'] );

		$is_saved = update_option( 'perform_settings', $new_settings, false );

		if ( $is_saved ) {
			wp_send_json_success( [
				'type'    => 'success',
				'message' => esc_html__( 'Settings saved successfully.', 'perform' ),
			] );
		} else {
			wp_send_json_error( [
				'type'    => 'error',
				'message' => esc_html__( 'Unable to save the settings. Please try again.', 'perform' ),
			] );
		}
	}
}
