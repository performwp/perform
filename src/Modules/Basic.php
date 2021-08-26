<?php
/**
 * Perform | Basic
 *
 * @package Perform
 * @subpackage Modules
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules;

use Perform\Includes\Helpers;
use function woocommerce_is_account_page;
use function woocommerce_is_checkout;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic Settings Module Class.
 *
 * @since 2.0.0
 */
class Basic {

	/**
	 * Admin Settings
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @var $settings
	 */
	public $settings;

	/**
	 * Constructor.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		$this->settings = Helpers::get_settings();

		// Disable Emoji's.
		if ( $this->settings['disable_emojis'] ) {
			add_action( 'init', [ $this, 'disable_emojis' ] );
		}

		// Disable Embeds.
		if ( $this->settings['disable_embeds'] ) {
			add_action( 'init', [ $this, 'disable_embeds' ], 9999 );
		}

		// Remove Query Strings.
		if ( $this->settings['remove_query_strings'] ) {
			add_action( 'init', [ $this, 'remove_query_strings' ] );
		}

		// Disable XMLRPC.
		if ( $this->settings['disable_xmlrpc'] ) {
			$this->disable_xmlrpc();
		}

		// Remove jQuery Migrate.
		if ( $this->settings['remove_jquery_migrate'] ) {
			add_filter( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ], 99 );
		}

		// Hide WP Version.
		if ( $this->settings['hide_wp_version'] ) {
			$this->hide_wp_version();
		}

		// Remove wlwmanifest link.
		if ( $this->settings['remove_wlwmanifest_link'] ) {
			$this->remove_wlwmanifest_link();
		}

		// Remove RSD link.
		if ( $this->settings['remove_rsd_link'] ) {
			$this->remove_rsd_link();
		}

		// Remove Shortlink.
		if ( $this->settings['remove_shortlink'] ) {
			$this->remove_shortlink();
		}

		// Disable RSS Feeds.
		if ( $this->settings['disable_rss_feeds'] ) {
			add_action( 'template_redirect', [ $this, 'disable_rss_feeds' ], 1 );
		}

		// Disable RSS Feed Links.
		if ( $this->settings['disable_feed_links'] ) {
			$this->disable_rss_feed_links();
		}

		// Disable Self Pingbacks.
		if ( $this->settings['disable_self_pingbacks'] ) {
			add_action( 'pre_ping', [ $this, 'disable_self_pingbacks' ], 99 );
		}

		// Remove Rest API Links.
		if ( $this->settings['remove_rest_api_links'] ) {
			$this->remove_rest_api_links();
		}

		// Disable Dashicons.
		if ( $this->settings['disable_dashicons'] ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'disable_dashicons' ] );
		}

		// Disable Password Strength Meter.
		if ( $this->settings['disable_password_strength_meter'] ) {
			add_action( 'wp_print_scripts', [ $this, 'disable_password_strength_meter' ], 100 );
		}

		// Disable Heartbeat.
		$this->disable_heartbeat();

		// Disable Heartbeat.
		if ( 'disable_everywhere' !== Helpers::get_option( 'disable_heartbeat', 'perform_common' ) ) {
			add_filter( 'heartbeat_settings', [ $this, 'heartbeat_frequency' ] );
		}

		// Limit Post Revisions.
		if ( $this->settings['limit_post_revisions'] ) {
			add_action( 'wp_print_scripts', [ $this, 'disable_password_strength_meter' ], 100 );
		}

		// DNS Prefetch.
		add_action( 'wp_head', [ $this, 'dns_prefetch' ], 1 );

		// Preconnect.
		add_action( 'wp_head', [ $this, 'preconnect' ], 1 );
	}

	/**
	 * Remove jQuery Migrate
	 *
	 * @todo Remove this functionality if this is permanently removed by WordPress.
	 *
	 * @param object $scripts  List of scripts used.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_jquery_migrate( $scripts ) {
		// Bailout, if accessed via admin.
		if ( is_admin() ) {
			return;
		}

		// Remove jQuery first which is loaded with jQuery Migrate JS.
		$scripts->remove( 'jquery' );

		// Add jQuery again without loading the jQuery Migrate JS.
		$scripts->add( 'jquery', false, [ 'jquery-core' ], '1.12.4' );
	}

	/**
	 * Hide WordPress Version.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function hide_wp_version() {
		// Remove WP Generator using action hook.
		remove_action( 'wp_head', 'wp_generator' );

		// Return empty string to the generator using filter hook.
		add_filter( 'the_generator', __return_empty_string() );
	}

	/**
	 * Remove Shortlink.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_shortlink() {
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}

	/**
	 * Remove RSD Link.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_rsd_link() {
		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Remove wlwmanifest Link.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_wlwmanifest_link() {
		remove_action( 'wp_head', 'wlwmanifest_link' );
	}

	/**
	 * Remove Query Strings.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_query_strings() {
		// Bailout, if accessed via admin.
		if ( is_admin() ) {
			return;
		}

		add_filter( 'script_loader_src', [ $this, 'process_query_string_removal' ], 15 );
		add_filter( 'style_loader_src', [ $this, 'process_query_string_removal' ], 15 );
	}

	/**
	 * This function is used to split the url based on the text "ver" to remove the query strings.
	 *
	 * @param string $url URL.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function process_query_string_removal( $url ) {
		$output = preg_split( '/(&ver|\?ver)/', $url );

		return $output[0];
	}

	/**
	 * Disable Self Pingbacks.
	 *
	 * @param array $links List of links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_self_pingbacks( $links ) {
		$home = get_option( 'home' );

		foreach ( $links as $key => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $key ] );
			}
		}
	}

	/**
	 * Disable Emoji's.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_emojis() {
		// Remove actions to disable emojis.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		// Remove filters to disable emojis.
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		// Add filter to disable emojis.
		add_filter( 'tiny_mce_plugins', [ $this, 'disable_emojis_from_tinymce' ] );
		add_filter( 'wp_resource_hints', [ $this, 'disable_emojis_from_dns_prefetch' ], 10, 2 );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * This function is used to disable emoji's from TinyMCE.
	 *
	 * @param array $plugins List of plugins.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_emojis_from_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, [ 'wpemoji' ] );
		}

		return [];
	}

	/**
	 * This function is used to disable SVG Emoji URL from DNS Prefetch.
	 *
	 * @param array  $urls         List of URLs.
	 * @param string $relationType Relation with the URLs.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_emojis_from_dns_prefetch( $urls, $relationType ) {
		if ( 'dns-prefetch' === $relationType ) {
			$svgUrl = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2.2.1/svg/' );
			$urls   = array_diff( $urls, [ $svgUrl ] );
		}

		return $urls;
	}

	/**
	 * Disable Embeds.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_embeds() {
		global $wp;
		$wp->public_query_vars = array_diff( $wp->public_query_vars, [ 'embed' ] );

		// Remove filters to disable embeds.
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );

		// Remove Filters to disable embeds.
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

		// Add filters to disable embeds.
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_filter( 'tiny_mce_plugins', [ $this, 'disable_embeds_from_tinymce' ] );
		add_filter( 'rewrite_rules_array', [ $this, 'disable_embeds_from_rewrites' ] );
	}

	/**
	 * This function will disable embeds from tinymce.
	 *
	 * @param array $plugins List of tinymce plugins.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_embeds_from_tinymce( $plugins ) {
		return array_diff( $plugins, [ 'wpembed' ] );
	}

	/**
	 * This function will remove embeds from rewrites.
	 *
	 * @param array $rules List of rewrite rules.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_embeds_from_rewrites( $rules ) {
		foreach ( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}

		return $rules;
	}

	/**
	 * Disable XMLRPC.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_xmlrpc() {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'pings_open', '__return_false', 9999 );
		add_filter( 'wp_headers', [ $this, 'remove_xpingback' ] );
	}

	/**
	 * This function is used to remove headers related to X Pingback.
	 *
	 * @param array $headers List of headers.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function remove_xpingback( $headers ) {
		unset( $headers['X-Pingback'], $headers['x-pingback'] );

		return $headers;
	}

	/**
	 * Disable Dashicons.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_dashicons() {
		// Bailout, if user is logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		wp_dequeue_style( 'dashicons' );
		wp_deregister_style( 'dashicons' );
	}

	/**
	 * Disable RSS Feeds.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_rss_feeds() {
		// Bailout, if it is not a feed or 404 page.
		if ( ! is_feed() || is_404() ) {
			return;
		}

		$feed = filter_input_array( INPUT_GET, 'feed' );

		// Check for "feed" query parameter.
		if ( ! empty( $feed ) ) {
			wp_redirect( esc_url_raw( remove_query_arg( 'feed' ) ), 301 );
			exit;
		}

		// Unset "wp_query" feed variable.
		if ( 'old' !== get_query_var( 'feed' ) ) {
			set_query_var( 'feed', '' );
		}

		// Allow WordPress redirect to the proper URL.
		redirect_canonical();

		// Display error message, if redirect fails.
		$error_message = sprintf(
			'%1$s <a href="%2$s">%3$s</a>',
			esc_html__( 'No feed available, please visit', 'perform' ),
			esc_url( home_url( '/' ) ),
			esc_html__( 'Home', 'perform' )
		);
		wp_die( $error_message );
	}

	/**
	 * Disable RSS Feed Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_rss_feed_links() {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	/**
	 * Remove REST API Links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_rest_api_links() {
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
	}

	/**
	 * Disable Password Strength Meter.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_password_strength_meter() {
		global $wp;

		$action   = filter_input( INPUT_GET, 'action' );
		$wp_check = isset( $wp->query_vars['lost-password'] ) || ( ! empty( $action ) && 'lostpassword' === $action ) || is_page( 'lost_password' );
		$wc_check = ( class_exists( 'WooCommerce' ) && ( is_account_page() || is_checkout() ) );

		if ( ! $wp_check && ! $wc_check ) {
			if ( wp_script_is( 'zxcvbn-async', 'enqueued' ) ) {
				wp_dequeue_script( 'zxcvbn-async' );
			}

			if ( wp_script_is( 'password-strength-meter', 'enqueued' ) ) {
				wp_dequeue_script( 'password-strength-meter' );
			}

			if ( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
				wp_dequeue_script( 'wc-password-strength-meter' );
			}
		}
	}

	/**
	 * Disable Heartbeat API.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_heartbeat() {
		$is_heartbeat_disabled = Helpers::get_option( 'disable_heartbeat', 'perform_common' );

		if ( 'disable_everywhere' === $is_heartbeat_disabled ) {
			wp_deregister_script( 'heartbeat' );
		} elseif ( 'allow_posts' === $is_heartbeat_disabled ) {
			global $pagenow;

			if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
				wp_deregister_script( 'heartbeat' );
			}
		}
	}

	/**
	 * Limit Heartbeat Frequency.
	 *
	 * @param array $settings List of settings.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function heartbeat_frequency( $settings ) {
		$heartbeat_frequency = Helpers::get_option( 'heartbeat_frequency', 'perform_common' );

		// Set Heartbeat API frequency based on your needs.
		if ( ! empty( $heartbeat_frequency ) ) {
			$settings['interval'] = $heartbeat_frequency;
		}

		return $settings;
	}

	/**
	 * DNS Prefetch.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function dns_prefetch() {
		ob_start();
		$dns_prefetch = ! empty( $this->settings['dns_prefetch'] ) ? $this->settings['dns_prefetch'] : [];

		if ( ! empty( $dns_prefetch ) && is_array( $dns_prefetch ) ) {
			foreach ( $dns_prefetch as $url ) {
				?>
				<link rel="dns-prefetch" href="<?php echo esc_url( $url ); ?>"/>
				<?php
			}
		}

		// Trim whitespace from start and end along with between HTML tags.
		echo Helpers::compress_html( ob_get_clean() );
	}

	/**
	 * Preconnect.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function preconnect() {
		ob_start();
		$preconnect = ! empty( $this->settings['preconnect'] ) ? $this->settings['preconnect'] : '';

		if ( ! empty( $preconnect ) && count( $preconnect ) > 0 ) {
			foreach ( $preconnect as $url ) {
				?>
				<link rel="preconnect" href="<?php echo esc_url( $url ); ?>"/>
				<?php
			}
		}

		// Trim whitespace from start and end along with between HTML tags.
		echo Helpers::compress_html( ob_get_clean() );
	}
}
