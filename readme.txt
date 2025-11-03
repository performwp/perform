=== Perform – Performance Optimization for WordPress ===
Contributors: performwp, mehul0810, ankur0812
Tags: performance, caching, cdn, assets, optimize
Donate link: https://www.buymeacoffee.com/mehulgohil
Requires at least: 4.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Lightweight performance plugin to remove unused assets, optimize loading order, and speed up WordPress sites; ideal for WooCommerce, page builders and more.

== Description ==

Perform helps you speed up WordPress by removing unused CSS/JS, deferring or disabling scripts, and giving fine-grained control over asset loading per page.

The plugin is designed to be lightweight, beginner-friendly and developer-extensible. It focuses on practical optimizations that improve front-end load times and Core Web Vitals without complicated setup.

Key benefits (short):

- Reduce page size and HTTP requests by disabling unused assets per page.
- Improve Largest Contentful Paint and Time to Interactive via script deferring and selective loading.
- Reduce admin and server overhead, minimal CPU and memory footprint.

Features

- Assets Manager: selectively disable CSS and JS per page, post type or template.
- Remove jQuery Migrate, emojis, embeds and other unnecessary features.
- WooCommerce optimizations: control cart fragments, scripts and styles to speed up stores.
- CDN & preconnect: add DNS-prefetch, preconnect, and native CDN integration hooks.
- Menu caching and lightweight transient caching for faster navigation.
- Developer-friendly hooks and filters for custom integrations.

Other Plugins
- [OneCaptcha](https://onecaptcha.com): Connect popular captcha providers with WordPress forms for SPAM prevention
- [WP Theme Switcher](https://wpthemeswitcher.com): Use multiple themes on your WordPress site at once. Useful for theme migration projects.
- [WordPress Development Services](https://mehulgohil.com): Want to build something amazing in WordPress space. I'm here to help. Let's discuss!

== Screenshots ==

1. Settings overview: global optimization toggles.
2. Assets Manager: disable CSS/JS per page.

== Installation ==

1. Upload the `perform` folder to the `/wp-content/plugins/` directory, or install via the WordPress plugin directory if available.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Perform → Settings to review defaults (the plugin works well out-of-the-box).

== FAQ ==

= Will Perform break my theme or plugins? =
Perform is conservative by default: it only disables assets when you explicitly choose them in the Assets Manager. If you disable something and see issues, re-enable the asset. Changes are reversible.

= Is this compatible with caching plugins like WP Rocket? =
Yes. Perform works alongside caching plugins and most server-level caching solutions. Clear cache after making asset changes.

= Which page builders are supported? =
Full compatibility with majority of all the page builders.

== Support ==

For help and troubleshooting, use our WordPress.org support forum: https://wordpress.org/support/plugin/perform
Contributions and bug reports welcome on GitHub: https://github.com/performwp/perform

== Changelog ==

= 1.5.1 - 2025-11-15 =
- Fixed Preview Blueprint

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

Always backup your database before updating. Follow the changelog for breaking changes.

== Screenshots ==

1. General Settings Screen
2. Bloat Settings Screen
3. Assets Settings Screen
4. CDN Settings Screen

== Contributors ==

performwp, mehul0810, ankur0812


