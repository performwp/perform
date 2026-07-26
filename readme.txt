=== Perform - Optimize Performance ===
Contributors: performwp, mehul0810, ankur0812
Tags: performance, caching, cdn, assets, optimize
Donate link: https://www.buymeacoffee.com/mehulgohil
Requires at least: 4.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Optimize WordPress performance with asset controls, page caching, CDN rewriting, and cleanup tools.

== Description ==

Perform helps site owners reduce unnecessary frontend work in WordPress. It combines asset controls, cache features, CDN rewriting, and small cleanup modules in one settings area.

Version 1.7.0 focuses on safer, easier-to-understand controls for real sites: a dashboard overview, dashboard-only runtime diagnostics, page cache exclusion and lifecycle controls, an Assets Manager reset action, consistent settings layouts, and Cache Stats integrated into the main settings tabs.

Key capabilities:

- Inspect scripts and styles on the current page with the Assets Manager.
- Disable selected CSS or JavaScript globally or keep it enabled for specific pages.
- Enable full-page caching with cache validation, stale regeneration, preload scheduling, and cache stats.
- Add DNS prefetch and preconnect hints for external resources.
- Rewrite static asset URLs to a CDN when a CDN URL is configured.
- Cache classic WordPress navigation menus on non-block themes.
- Remove common frontend extras such as emojis, embeds, query strings, feed links, REST links, shortlinks, and jQuery Migrate.
- Adjust Heartbeat, autosave, post revision, and self-pingback behavior.
- Apply WooCommerce-specific asset and cart fragment controls when WooCommerce is active.

How Perform works:

- Most modules are disabled until you turn them on.
- Asset changes are reversible from the Assets Manager.
- Existing settings from older Perform versions are preserved during migration.
- Developers can customize selected behavior through Perform filters.

== Installation ==

1. Upload the `perform` folder to the `/wp-content/plugins/` directory, or install Perform from the WordPress plugin directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Perform to review and enable the modules you want to use.

== FAQ ==

= Will Perform break my theme or plugins? =
Perform is conservative by default. Asset unloading, page cache, CDN rewriting, and integration-specific controls only run when you enable them. If an asset change causes a layout or behavior issue, re-enable that asset from the Assets Manager.

= Is this compatible with other caching plugins? =
Perform can run alongside many host-level and plugin-level caching setups, but avoid enabling two full-page caches for the same page response unless you understand the cache order. Clear all caches after changing cache, CDN, or asset settings.

= Does Menu Cache work with block themes? =
Menu Cache is designed for classic themes that render menus through `wp_nav_menu()`. Block themes generally do not need this module because navigation is rendered through block theme paths.

= Does Assets Manager scan the whole site? =
Assets Manager scans the current frontend page while you are logged in as an administrator. Use it page by page for safer asset decisions, then test important templates before applying broad changes.

= Are settings preserved when updating to 1.7.0? =
Yes. Perform 1.7.0 preserves existing public option keys and migrates legacy settings into the current settings shape where needed.

== Support ==

Project site and release updates: https://performwp.com/
For help and troubleshooting, use our WordPress.org support forum: https://wordpress.org/support/plugin/perform
Contributions and bug reports welcome on GitHub: https://github.com/performwp/perform

== Changelog ==

= 1.7.0 - 2026-07-26 =
- Added a dashboard overview for faster at-a-glance site status.
- Added a dashboard-only runtime diagnostics panel for environment and plugin inspection.
- Added page cache exclusion controls for URLs and content that should bypass cache.
- Added an Assets Manager reset action for safer repeated scans.
- Standardized the General, Bloat, Assets, CDN, Cache, and Advanced settings with shared responsive field layouts and consistent accessible iconography.
- Moved Cache Stats into the main Perform settings tabs while preserving the previous URL for administrators.
- Added clear progress, success, failure, and retry feedback for Cache Stats CSV export and activity clearing.
- Improved full-page cache invalidation after content, comment, taxonomy, settings, theme, and plugin changes, with bounded cleanup and CDN retry handling.
- Hardened sitemap preload traversal and CDN module load gating.
- Improved internal module loading and Assets Manager settings persistence without changing existing setting contracts.
- Aligned the dashboard and public metadata surfaces with version 1.7.0 and refreshed WordPress.org readme wording.

= 1.6.0 - 2026-05-22 =
- Redesigned Assets Manager with a more resilient scanner interface and WordPress-native admin controls.
- Added full-page cache controls with safer cache writes, response validation, stale regeneration, preload scheduling, and observability stats.
- Improved settings storage compatibility by preserving existing option keys while migrating legacy settings into the consolidated settings shape.
- Improved release validation with PHPUnit, PHPStan, JavaScript/CSS linting, production build checks, Playwright smoke coverage, and Node 24 tooling.
- Changed Menu Cache to run on classic themes by default, with a developer filter for hybrid themes that still render classic menus.
- Fixed Assets Manager save handling for current-page exceptions, missing option indexes, and admin-only frontend overlay assets.
- Fixed public feed compatibility when hiding the WordPress version.
- Fixed uninstall cleanup so multisite removals include Perform runtime cache and Assets Manager options.

= 1.5.1 - 2025-12-06 =
- Added compatibility to WordPress 6.9
- Upgraded Freemius SDK to 2.13
- Upgraded WPCS to 3.3
- Resolved load text domain warning

= 1.5.0 - 2025-11-01 =
- Upgraded Settings UI to look and feel premium.
- Optimized code around settings screen.

= 1.4.1 - 2025-04-26 =
- Added Freemius integration.
- Moved to PostCSS build and wp-scripts.
- Added PHPStan static analysis.

= 1.3.1 - 2024-11-13 =
- WordPress 6.7 compatibility.
- Raised minimum PHP version to 7.4.

= 1.3.0 - 2020-12-31 =
- Modernized codebase and namespaces.

= 1.2.3 - 2019-12-31 =
- Fix: Handle writable wp-config scenarios.

= 1.2.2 - 2019-12-27 =
- Fix: CDN rewrite bug and UI tweaks.

= 1.2.1 - 2019-06-22 =
- Fix: Welcome redirect after activation.

= 1.2.0 - 2019-04-30 =
- Added Menu Caching.

== Upgrade Notice ==

= 1.7.0 =
Review Assets Manager, cache exclusions, Cache Stats, and runtime diagnostics after updating. Cache Stats is now under Settings > Perform; the previous administrator URL redirects there. Settings use a consistent responsive layout, and Cache Stats export and clear actions now report their progress and outcome.

== Screenshots ==

1. General Settings Screen
2. Frontend cleanup and performance toggle settings
3. Assets Manager scanner and per-page asset controls
4. CDN and resource hint settings

== Contributors ==

performwp, mehul0810, ankur0812
