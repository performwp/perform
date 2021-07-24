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
				<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 408 76.372"><defs><linearGradient id="a" x1="0.941" y1="0.067" x2="0.074" y2="0.93" gradientUnits="objectBoundingBox"><stop offset="0" stop-color="#0046d1"/><stop offset="1" stop-color="#afc9ff"/></linearGradient><linearGradient id="b" x1="0.881" y1="0.125" x2="0.139" y2="0.883" xlink:href="#a"/><linearGradient id="c" x1="0.892" y1="0.121" x2="0.125" y2="0.897" xlink:href="#a"/></defs><text transform="translate(249 60)" fill="#222" font-size="62" font-family="Montserrat-Medium, Montserrat" font-weight="500"><tspan x="-158.534" y="0">PERFORM</tspan></text><g transform="translate(0 0.42)"><path d="M169.357,67.974,205.9,32.92,185.432,98.468Z" transform="translate(-134.396 -26.743)" fill="#c8d8ff"/><path d="M49.768,58.34,84,23.094,18.155,46.032Z" transform="translate(-14.408 -18.946)" fill="#fff"/><path d="M75.939,1.433a1.546,1.546,0,0,0-.051-.258,1.714,1.714,0,0,0-.109-.268c-.023-.048-.031-.1-.059-.145a1.016,1.016,0,0,0-.069-.078,1.713,1.713,0,0,0-.187-.21A1.614,1.614,0,0,0,75.25.291c-.028-.02-.046-.048-.074-.064-.046-.028-.1-.035-.145-.058a1.718,1.718,0,0,0-.276-.111,1.738,1.738,0,0,0-.243-.043A1.694,1.694,0,0,0,74.223,0a1.484,1.484,0,0,0-.268.045,1.521,1.521,0,0,0-.17.028L1.12,24.847a1.65,1.65,0,0,0-.058,3.1s17.841,5.311,30,17.062S49.682,74.956,49.682,74.956a1.654,1.654,0,0,0,1.516,1c.028,0,.055,0,.083,0A1.652,1.652,0,0,0,52.772,74.8L67.927,27.169,75.892,2.137a1.237,1.237,0,0,0,.021-.157,1.561,1.561,0,0,0,.038-.276A1.646,1.646,0,0,0,75.939,1.433ZM6.5,26.5,68.127,5.489,35.842,37.774ZM50.962,69.592,38.212,40.077,70.685,7.6Z" transform="translate(-0.001 0)" fill="#0046d1"/><path d="M25.941,240.4a1.65,1.65,0,0,0-2.335,0L.485,263.523a1.651,1.651,0,1,0,2.335,2.335l23.121-23.121A1.65,1.65,0,0,0,25.941,240.4Z" transform="translate(-0.001 -190.39)" fill="url(#a)"/><path d="M100.046,296.4l-11.56,11.56A1.651,1.651,0,1,0,90.82,310.3l11.56-11.56a1.651,1.651,0,1,0-2.335-2.335Z" transform="translate(-69.835 -234.83)" fill="url(#b)"/><path d="M1.653,222.782A1.647,1.647,0,0,0,2.82,222.3l11.56-11.56a1.651,1.651,0,1,0-2.335-2.335L.485,219.963a1.651,1.651,0,0,0,1.168,2.819Z" transform="translate(-0.001 -164.996)" fill="url(#c)"/></g></svg>
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
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Perform Settings', 'perform' ); ?>
			</h1>
			<?php $this->navigation_html(); ?>
			<?php $this->display_form(); ?>
		</div>
		<?php
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
