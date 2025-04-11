<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and starts the plugin.
 *
 * @since      1.0.0
 * @package    WordPress
 * @subpackage Perform
 * @author     Mehul Gohil
 * @link       https://performwp.com
 *
 * @wordpress-plugin
 *
 * Plugin Name: Perform - Optimize Performance
 * Plugin URI: https://performwp.com/
 * Description: This plugin adds toolset for performance and speed improvements to your WordPress sites.
 * Version: 1.3.1
 * Author: Mehul Gohil
 * Author URI: https://mehulgohil.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: perform
 * Domain Path: /languages
 */

 namespace Perform;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Constants.
require_once __DIR__ . '/config/constants.php';

// Automatically loads files used throughout the plugin.
require_once PERFORM_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin.
$plugin = new Plugin();
$plugin->register();
