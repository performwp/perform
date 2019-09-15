<?php
/**
 * Perform Functions
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This function will be called on init and will load modules on init based on the settings.
 *
 * @since 1.0.0
 */
function perform_load_modules_on_init() {

	/**
	 * Disable Emoji's
	 *
	 * @since 1.0.0
	 */
	$is_emojis_disabled = perform_get_option( 'disable_emojis', 'perform_common' );

	if ( $is_emojis_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-emojis.php';

		// Init Module.
		new Perform_Disable_Emojis();
	}

	/**
	 * Disable Embed's
	 *
	 * @since 1.0.0
	 */
	$is_embeds_disabled = perform_get_option( 'disable_embeds', 'perform_common' );

	if ( $is_embeds_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-embeds.php';

		// Init Module.
		new Perform_Disable_Embeds();
	}

	/**
	 * Remove Query Strings.
	 *
	 * @since 1.0.0
	 */
	$is_query_strings_removed = perform_get_option( 'remove_query_strings', 'perform_common' );

	if ( $is_query_strings_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-query-strings.php';

		// Init Module.
		new Perform_Remove_Query_Strings();
	}

	/**
	 * Disable XML-RPC.
	 *
	 * @since 1.0.0
	 */
	$is_xmlrpc_disabled = perform_get_option( 'disable_xmlrpc', 'perform_common' );

	if ( $is_xmlrpc_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-xmlrpc.php';

		// Init Module.
		new Perform_Disable_XMLRPC();
	}

	/**
	 * Remove jQuery Migrate.
	 *
	 * @since 1.0.0
	 */
	$is_migrate_js_removed = perform_get_option( 'remove_jquery_migrate', 'perform_common' );

	if ( $is_migrate_js_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-jquery-migrate.php';

		// Init Module.
		new Perform_Remove_jQuery_Migrate();
	}

	/**
	 * Hide WP Version.
	 *
	 * @since 1.0.0
	 */
	$is_wp_version_hidden = perform_get_option( 'hide_wp_version', 'perform_common' );

	if ( $is_wp_version_hidden ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-hide-wp-version.php';

		// Init Module.
		new Perform_Hide_WP_Version();
	}

	/**
	 * Remove wlwmanifest link.
	 *
	 * @since 1.0.0
	 */
	$is_wlwmanifest_link_removed = perform_get_option( 'remove_wlwmanifest_link', 'perform_common' );

	if ( $is_wlwmanifest_link_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-wlwmanifest-link.php';

		// Init Module.
		new Perform_Remove_WLWManifest_Link();
	}

	/**
	 * Remove RSD link.
	 *
	 * @since 1.0.0
	 */
	$is_rsd_link_removed = perform_get_option( 'remove_rsd_link', 'perform_common' );

	if ( $is_rsd_link_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-rsd-link.php';

		// Init Module.
		new Perform_Remove_RSD_Link();
	}

	/**
	 * Remove Shortlink.
	 *
	 * @since 1.0.0
	 */
	$is_shortlink_removed = perform_get_option( 'remove_wlwmanifest_link', 'perform_common' );

	if ( $is_shortlink_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-shortlink.php';

		// Init Module.
		new Perform_Remove_Shortlink();
	}

	/**
	 * Disable RSS Feeds.
	 *
	 * @since 1.0.0
	 */
	$is_rss_feeds_disabled = perform_get_option( 'disable_rss_feeds', 'perform_common' );

	if ( $is_rss_feeds_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-rss-feeds.php';

		// Init Module.
		new Perform_Disable_RSS_Feeds();
	}

	/**
	 * Remove RSS Feed Links.
	 *
	 * @since 1.0.0
	 */
	$is_rss_feed_links_removed = perform_get_option( 'remove_feed_links', 'perform_common' );

	if ( $is_rss_feed_links_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-rss-feed-links.php';

		// Init Module.
		new Perform_Remove_RSS_Feed_Links();
	}

	/**
	 * Disable Self Pingbacks.
	 *
	 * @since 1.0.0
	 */
	$is_self_pingbacks_disabled = perform_get_option( 'disable_self_pingbacks', 'perform_common' );

	if ( $is_self_pingbacks_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-self-pingbacks.php';

		// Init Module.
		new Perform_Disable_Self_Pingbacks();
	}

	/**
	 * Remove REST API Links.
	 *
	 * @since 1.0.0
	 */
	$is_rest_api_links_removed = perform_get_option( 'remove_rest_api_links', 'perform_common' );

	if ( $is_rest_api_links_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-rest-api-links.php';

		// Init Module.
		new Perform_Remove_Rest_API_Links();
	}

	/**
	 * Disable Dashicons.
	 *
	 * @since 1.0.0
	 */
	$is_dashicons_disabled = perform_get_option( 'disable_dashicons', 'perform_common' );

	if ( $is_dashicons_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-dashicons.php';

		// Init Module.
		new Perform_Disable_Dashicons();
	}

	/**
	 * Disable Password Strength Meter.
	 *
	 * @since 1.0.0
	 */
	$is_password_strength_meter_disabled = perform_get_option( 'disable_password_strength_meter', 'perform_common' );

	if ( $is_password_strength_meter_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-password-strength-meter.php';

		// Init Module.
		new Perform_Disable_Password_Strength_Meter();
	}

	/**
	 * Disable Heartbeat API.
	 *
	 * @since 1.0.0
	 */
	$is_heartbeat_disabled = perform_get_option( 'disable_heartbeat', 'perform_common' );
	if ( ! empty( $is_heartbeat_disabled ) ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-heartbeat-manager.php';

		// Init Module.
		new Perform_Heartbeat_Manager();

	}

	/**
	 * Set Post Revisions Limit.
	 *
	 * @since 1.0.0
	 */
	$post_revisions_limit = perform_get_option( 'limit_post_revisions', 'perform_common' );
	if ( ! empty( $post_revisions_limit ) ) {
		Perform()->config->exists( 'constant', 'WP_POST_REVISIONS' ) ?
			Perform()->config->update( 'constant', 'WP_POST_REVISIONS', $post_revisions_limit ) :
			Perform()->config->add( 'constant', 'WP_POST_REVISIONS', $post_revisions_limit );
	}

	/**
	 * Set Autosave Interval.
	 *
	 * @since 1.0.0
	 */
	$autosave_interval = perform_get_option( 'autosave_interval', 'perform_common' );
	if ( ! empty( $autosave_interval ) ) {
		define( 'AUTOSAVE_INTERVAL', $autosave_interval );
	}

	// Load WooCommerce Manager module when WooCommerce is active.
	if ( perform_is_woocommerce_active() ) {
		/**
		 * Load WooCommerce Modules using WooCommerce Manager.
		 *
		 * @since 1.0.0
		 */
		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-woocommerce-manager.php';

		// Init Module.
		new Perform_WooCommerce_Manager();
	}
}

add_action( 'init', 'perform_load_modules_on_init', 0 );

/**
 * Load SSL Manager.
 *
 * @since 1.0.0
 */
function perform_load_ssl_manager() {

	// Load Module.
	require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-ssl-manager.php';

	// Init Module.
	new Perform_SSL_Manager();

}

add_action( 'wp', 'perform_load_ssl_manager', 40, 3 );

/**
 * Load DNS Prefetch.
 *
 * @since 1.0.0
 */
function perform_load_dns_prefetch() {

	$dns_prefetch = perform_get_option( 'dns_prefetch', 'perform_advanced' );
	if ( ! empty( $dns_prefetch ) && is_array( $dns_prefetch ) ) {
		foreach ( $dns_prefetch as $url ) {
			echo "<link rel='dns-prefetch' href='{$url}'>" . "\n";
		}
	}

}

add_action( 'wp_head', 'perform_load_dns_prefetch', 1 );

/**
 * Load Preconnect.
 *
 * @since 1.0.0
 */
function perform_load_preconnect() {

	$preconnect = perform_get_option( 'preconnect', 'perform_advanced' );
	if ( ! empty( $preconnect ) && is_array( $preconnect ) ) {
		foreach ( $preconnect as $url ) {
			echo "<link rel='preconnect' href='{$url}' crossorigin>" . "\n";
		}
	}

}

add_action( 'wp_head', 'perform_load_preconnect', 1 );


/**
 * Load Assets Manager.
 *
 * @since 1.1.0
 *
 * @return void
 */
$is_assets_manager_enabled = perform_get_option( 'enable_assets_manager', 'perform_advanced' );

if ( $is_assets_manager_enabled ) {

	require_once PERFORM_PLUGIN_DIR . 'includes/modules/assets-manager/functions.php';
}

/**
 * Load Navigation Menu Cache.
 *
 * @since 1.2.0
 *
 * @return void
 */
$is_nav_menu_cache_enabled = perform_get_option( 'enable_navigation_menu_cache', 'perform_advanced' );

if ( $is_nav_menu_cache_enabled ) {

	require_once PERFORM_PLUGIN_DIR . 'includes/modules/cache-manager/class-perform-menu-cache.php';
}
