<?php
/**
 * Module Registry
 *
 * Central source of truth for module class registration.
 *
 * @since 2.0.0
 */

namespace Perform\Modules;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registry {
	/**
	 * Get all module classes.
	 *
	 * @return array<int, string>
	 */
	public static function all() {
		return [
			Basic\DisableEmoji::class,
			Basic\DisableEmbeds::class,
			Basic\RemoveQueryStrings::class,
			Basic\DisableXmlrpc::class,
			Basic\RemoveJqueryMigrate::class,
			Basic\HideWpVersion::class,
			Basic\RemoveWlwmanifestLink::class,
			Basic\RemoveRsdLink::class,
			Basic\RemoveShortlink::class,
			Basic\DisableRssFeeds::class,
			Basic\DisableFeedLinks::class,
			Basic\DisableSelfPingbacks::class,
			Basic\RemoveRestApiLinks::class,
			Basic\DisableDashicons::class,
			Basic\DisablePasswordStrengthMeter::class,
			Basic\LimitPostRevisions::class,
			Basic\DnsPrefetch::class,
			Basic\Preconnect::class,
			Basic\Heartbeat::class,
			CDN\Cdn_Manager::class,
			Assets\Assets_Manager::class,
			SSL\Ssl_Manager::class,
			WooCommerce\Woocommerce_Manager::class,
			MenuCache\Menu_Cache::class,
		];
	}
}
