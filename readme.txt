=== Perform - Performance Optimization for WordPress ===
Contributors: performwp, mehul0810, ankur0812
Tags: performance optimization, asset cleanup, assets manager, disable bloat, cleanup
Donate link: https://www.buymeacoffee.com/mehulgohil
Requires at least: 4.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Boost your WordPress site's performance by removing unused assets, scripts, and features. A lightweight alternative to Perfmatters and Asset Cleanup.

== Description ==

🚀 **Speed Up Your WordPress Site With Just a Few Clicks**

Perform is a powerful yet user-friendly WordPress performance optimization plugin that helps you remove unnecessary bloat and optimize your site's loading speed. If you're familiar with plugins like Perfmatters or Asset Cleanup, you'll feel right at home with Perform.

= 🎯 Why Choose Perform? =

* **Simpler Than Alternatives:** Easy-to-use interface that doesn't overwhelm you with options
* **Zero Configuration:** Works out of the box with sensible defaults
* **Lightweight:** Won't slow down your admin panel like other optimization plugins
* **Asset Manager:** Selectively disable CSS/JS files on a per-page basis
* **No Conflicts:** Compatible with popular caching plugins and hosting providers

= 🔥 Key Features =

* **Assets Manager**
  * Disable unused CSS and JS files
  * Per-page optimization
  * Compatible with page builders

* **Speed Optimization**
  * Remove jQuery Migrate
  * Disable Emojis & Embeds
  * Remove Query Strings
  * Disable XML-RPC
  * DNS Prefetch & Preconnect
  * Navigation Menu Cache

* **WordPress Cleanup**
  * Disable WP Bloat
  * Remove Version Numbers
  * Optimize WooCommerce
  * Control Heartbeat API
  * Manage Post Revisions

* **Advanced Features**
  * SSL Manager
  * CDN Integration
  * WooCommerce Optimizer
  * Assets Preloading

= 🏆 Perfect For =

* Website owners looking for a Perfmatters alternative
* Developers who want granular control over assets
* Anyone struggling with slow WordPress sites
* WooCommerce store owners
* Agencies managing multiple WordPress sites

= 🤝 Compatible With =

* Popular caching plugins (WP Rocket, WP Super Cache, etc.)
* Major page builders (Elementor, Divi, etc.)
* WooCommerce
* Hosting providers with server-level caching
* Modern WordPress themes

= Connect with Perform - WordPress Plugin =

Stay in touch with us for important plugin news and updates:

* **[GitHub](https://github.com/performwp/perform/ "Visit the development of Perform")**

= Contribute to Perform - WordPress Plugin =

This plugin is proudly open source (GPL license) and we're always looking for more contributors. Whether you know another language, can code like no one's business, or just have an idea, we would love your help and input.

Here's a few ways you can contribute to Perform:

* Star/fork/watch the [Perform GitHub repository](https://github.com/performwp/perform "Visit the Perform GitHub Repo") to learn more about what issues we're tackling and the project is developing. If you've never worked with Github before, learn about [pull requests here](https://help.github.com/articles/about-pull-requests/) and submit one for Perform, we'd love to provide you our feedback.

* Translate Perform into your native language. The best place to do that is here on wordpress.org. Go to [https://translate.wordpress.org/](https://translate.wordpress.org/projects/wp-plugins/perform), then search for your language, click the "Plugins" tab, then search for "Perform". When you've submitted at least 95% of Perform's strings, the language moderators will review and approve your translations and then they will be available to all WordPress users for your native language.


== Installation ==

= Minimum Requirements =

* WordPress 4.8 or greater
* PHP version 7.4 or greater
* MySQL version 5.5 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Perform, log in to your WordPress dashboard, navigate to the Plugins menu and click "Add New".

In the search field type "Perform" and click Search Plugins. Once you have found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Frequently Asked Questions ==

= How can I get support for Perform? =

We work hard to the best support possible for Perform. The [WordPress.org Support Forum](https://wordpress.org/support/plugin/perform) is used for free community based support. We continually monitor the forum and do our best to ensure everyone gets a response.

= Does it work with caching plugins like WP Rocket? =
Yes, it does.

= Does it work with plugins like Autoptimize? =
Yes, it does.

= Does it work with hosting providers like FlyWheel, Kinsta, and hosts with server-level caching? =
Yes, it does.

== Upgrade Notice ==

Please make sure you make a backup of your database before updating any version to ensure that none of your data is lost.

== Changelog ==

= 1.4.0: unreleased, 2025 =
- Added Freemius
- Migrated from SASS to Postcss
- Used wp-scripts efficiently
- Add PHPStan
- Improved Automation for overall security and performance
- Improved Menu Cache
- Improved Overall UI for performance
- Improved Disable Cart Fragments

= 1.3.1: November 13th, 2024 =
- Added support for WordPress 6.7
- Bumped minimum PHP version support from 5.6 to 7.4
- Added some automations quality control and security

= 1.3.0: December 31st, 2020 =
- Moved to modern coding practices using namespaces

= 1.2.3: December 31st, 2019 =
- Resolved fatal error when `wp-config.php` file is not writable

= 1.2.2: December 27th, 2019 =
- Fix: cdn rewrite is not working [#16](https://github.com/mehul0810/perform/issues/16)
- UI Improvements for Assets Manager
- Simplified working of `wp-config.php` constants
- Security Improvements

= 1.2.1: June 22th, 2019 =
- Fix: redirect to welcome screen on plugin activation [#15](https://github.com/mehul0810/perform/issues/15)
- General Design Improvements & Tweaks
- User Experience Improvements for settings page

= 1.2.0: April 30th, 2019 =
- Fix: add quick access to assets manager from admin listing [#10](https://github.com/mehul0810/perform/issues/10)
- Feat: add menu cache [#11](https://github.com/mehul0810/issues/11)
- Fix: generalise styling for assets manager with all the themes [#12](https://github.com/mehul0810/perform/issues/12)

= 1.1.1: April 25th, 2019 =
- Fix: display assets manager link in admin bar [#6](https://github.com/mehul0810/perform/issues/6)

= 1.1.0: April 25th, 2019 =
- Feat: add support for assets manager [#1](https://github.com/mehul0810/perform/issues/1)
- Fix: settings page is not visible with multisite setup [#5](https://github.com/mehul0810/perform/issues/5)

= 1.0.1: March 31st, 2019 =
- Fix: incorrect linking for reviews in admin footer [#2](https://github.com/mehul0810/perform/issues/2)
- Fix: woocommerce tab should be visible when woocommerce is active [#3](https://github.com/mehul0810/perform/issues/3)
- Fix: disabling woocommerce widgets not working [#4](https://github.com/mehul0810/perform/issues/4)

= 1.0.0: March 23rd, 2019 =
- Initial Release. Yippee!
