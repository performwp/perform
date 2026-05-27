=== Perform - Optimize Performance ===
Contributors: performwp, mehul0810, ankur0812
Tags: performance, caching, cdn, assets, optimize
Donate link: https://www.buymeacoffee.com/mehulgohil
Requires at least: 4.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Make WordPress faster with simple controls for caching, assets, CDN rewriting, WooCommerce cleanup, and common frontend extras.

== Description ==

Perform helps site owners improve WordPress performance without editing code. It gives you one settings area where you can turn on the optimizations you need, test the result, and turn changes off again if something does not look right.

Use Perform to reduce extra frontend work, cache pages, clean up common WordPress output, and make selected WooCommerce pages lighter. Most features stay off until you enable them, so you can make changes gradually.

What Perform can help with:

- Cache pages so repeat visits can load faster.
- Review loaded scripts and styles with the Assets Manager, then disable files that are safe to remove.
- Add DNS prefetch and preconnect hints for external resources.
- Rewrite static file URLs to a CDN when you have a CDN URL configured.
- Remove common frontend extras such as emojis, embeds, query strings, feed links, REST links, shortlinks, and jQuery Migrate.
- Adjust Heartbeat, autosave, post revisions, and self-pingbacks.
- Reduce selected WooCommerce scripts and cart fragment behavior when WooCommerce is active.
- Cache classic WordPress navigation menus on sites using classic themes.

Designed for safer day-to-day use:

- Most modules are disabled until you turn them on.
- Asset changes are reversible from the Assets Manager.
- The Assets Manager scans the current page while you are logged in as an administrator.
- Existing settings from older Perform versions are preserved during updates.
- Developers can customize selected behavior through Perform filters.

== Installation ==

1. Upload the `perform` folder to the `/wp-content/plugins/` directory, or install Perform from the WordPress plugin directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Perform and enable the modules you want to use.

== FAQ ==

= Will Perform change my site as soon as I activate it? =
No. Most modules are disabled until you choose to enable them from Settings > Perform.

= Do I need to know code to use Perform? =
No. The settings are built for site owners and administrators. Some advanced options, such as the Assets Manager and CDN rewriting, should still be tested on important pages after you enable them.

= What should I do if an asset change affects my layout or a button stops working? =
Open the Assets Manager for that page and re-enable the script or style that caused the issue. Asset changes are reversible.

= Is this compatible with other caching plugins? =
Perform can run alongside many host-level and plugin-level caching setups, but avoid enabling two full-page caches for the same page response unless you understand the cache order. Clear all caches after changing cache, CDN, or asset settings.

= Does Menu Cache work with block themes? =
Menu Cache is designed for classic themes that render menus through `wp_nav_menu()`. Block themes generally do not need this module because navigation is rendered through block theme paths.

= Does Assets Manager scan the whole site? =
No. Assets Manager scans the current frontend page while you are logged in as an administrator. Use it page by page for safer asset decisions, then test important templates before applying broad changes.

= Are settings preserved when updating? =
Yes. Perform preserves existing settings during updates and migrates older settings into the current settings shape where needed.

== Support ==

For help and troubleshooting, use our WordPress.org support forum: https://wordpress.org/support/plugin/perform
Contributions and bug reports welcome on GitHub: https://github.com/performwp/perform

== Changelog ==

= 1.6.1 - 2026-05-27 =
- Rewrote the public readme copy to be clearer for non-technical users.
- Updated plugin version metadata for the 1.6.1 patch release branch.
- Simplified the public readme to focus on Perform setup, features, support, and update guidance.

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

= 1.6.1 =
This is a documentation-focused patch release. No settings or site behavior change is required after updating.

= 1.6.0 =
Review your Assets Manager and cache settings after updating. Perform 1.6.0 adds the redesigned scanner, page cache controls, and safer release validation.

Back up your site before changing performance settings on production, then test important pages after enabling cache or asset controls.

== Screenshots ==

1. General Settings Screen
2. Bloat Settings Screen
3. Assets Settings Screen
4. CDN Settings Screen

== Contributors ==

performwp, mehul0810, ankur0812
