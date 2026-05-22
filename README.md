<h1><p align="center">PerformWP - Web Performance Optimization WordPress Plugin :rocket:</p></h1>

<p align="center">This plugin has been developed with an intention to improve the performance of your WordPress website. It has been developed from the ground up for all your performance optimization needs.</p>

---

👉 Not a developer? Running WordPress? [Download PerformWP](https://wordpress.org/plugins/perform/) on WordPress.org.

![WordPress version](https://img.shields.io/wordpress/plugin/v/perform.svg) ![WordPress Rating](https://img.shields.io/wordpress/plugin/r/perform.svg) ![WordPress Downloads](https://img.shields.io/wordpress/plugin/dt/perform.svg) [![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://github.com/mehul0810/perform-for-wp/blob/master/license.txt) 

Welcome to the Perform GitHub repository. This is the code source and the center of active development. Here you can browse the source, look at open issues, and contribute to the project.
 
## Getting Started 

If you're looking to contribute or actively develop on Perform then skip ahead to the [Local Development](https://github.com/mehul0810/perform/#local-development) section below. The following is if you're looking to actively use the plugin on your WordPress site.

### Minimum Requirements

* WordPress 5.1 or greater
* PHP version 7.0 or greater
* MySQL version 5.6 or greater

### Automatic installation

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Perform, log in to your WordPress dashboard, navigate to the Plugins menu and click "Add New".

In the search field type "Perform" and click Search Plugins. Once you have found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

### Manual installation

The manual installation method involves downloading our donation plugin and uploading it to your server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).


### Support
This repository is not suitable for support. Please don't use GitHub issues for support requests. To get support please use the following channels:

* [WP.org Support Forums](https://wordpress.org/support/plugin/perform) - for all users

## Local Development 

To get started developing on the Perform WordPress Plugin you will need to perform the following steps:

1. Create a new WordPress site with `perform.test` as the URL

2. `cd` into your local plugins directory: `/path/to/wp-content/plugins/`

3. Clone this repository from GitHub into your plugins directory: `https://github.com/performwp/perform.git`

4. Use Node.js 24.x for project tooling. The repository includes `.nvmrc` and `.node-version` so local version managers can select the supported Active LTS line.

5. Run Composer to set up dependencies: `composer install`

6. Run npm to install the locked frontend dependencies: `npm ci`

7. Build the plugin assets: `npm run build`

8. Activate the plugin in WordPress

That's it. You're now ready to start development.

### NPM Commands

Perform relies on several npm commands to get you started:

* `npm run start` - Watches JS and CSS source files and rebuilds development assets.
* `npm run build` - Builds minified production assets for release.
* `npm run lint:js` - Lints JavaScript source and build configuration files.
* `npm run lint:css` - Lints CSS source files.
* `npm run plugin-zip` - Builds a plugin zip using the project distribution ignore rules.
* `npm run test:e2e` - Runs the Playwright smoke test suite against a running WordPress site.
* `npm run test:e2e:ci` - Starts `wp-env`, runs the Playwright smoke suite, and stops `wp-env`.

### Validation Commands

Use these commands before opening a pull request:

* `composer validate --no-check-publish`
* `composer lint`
* `composer check-cs`
* `composer phpstan`
* `composer test`
* `npm run lint`
* `npm run build`
* `npm run test:e2e:ci`

### Development Notes

* Ensure that you have `SCRIPT_DEBUG` enabled within your wp-config.php file. Here's a good example of wp-config.php for debugging:
    ```
     // Enable WP_DEBUG mode
    define( 'WP_DEBUG', true );
    
    // Enable Debug logging to the /wp-content/debug.log file
    define( 'WP_DEBUG_LOG', true );
   
    // Loads unminified core files
    define( 'SCRIPT_DEBUG', true );
    ```
* Commit the `package.lock` file. Read more about why [here](https://docs.npmjs.com/files/package-lock.json). 
* Your editor should recognize the `.eslintrc` and `.editorconfig` files within the Repo's root directory. Please only submit PRs following those coding style rulesets. 
* Read [CONTRIBUTING.md](https://github.com/mehul0810/perform/blob/master/CONTRIBUTING.md) - it contains more about contributing to Perform.
