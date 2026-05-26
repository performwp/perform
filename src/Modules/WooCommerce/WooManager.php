<?php
/**
 * Perform - WooCommerce Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Modules/WooCommerce_Manager
 * @author     PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\WooCommerce;

use Perform\Includes\Helpers;
use Perform\Modules\ModuleInterface;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooManager
 *
 * @since 1.0.0
 */
class WooManager implements ModuleInterface {

	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		if ( ! Helpers::is_woocommerce_active() ) {
			return false;
		}

		return (
			Helpers::get_option( 'disable_woocommerce_assets', 'perform_settings' ) ||
			Helpers::get_option( 'disable_woocommerce_cart_fragmentation', 'perform_settings' ) ||
			Helpers::get_option( 'disable_woocommerce_status', 'perform_settings' ) ||
			Helpers::get_option( 'disable_woocommerce_widgets', 'perform_settings' )
		);
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		/**
		 * Disable Default WooCommerce Assets.
		 *
		 * @since 1.0.0
		 */
		$disable_assets = Helpers::get_option( 'disable_woocommerce_assets', 'perform_settings' );
		if ( $disable_assets ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'disable_assets' ], 99 );
		}

		/**
		 * Disable Complete Cart Fragmentation.
		 *
		 * @since 1.0.0
		 */
		$disable_cart_fragmentation = Helpers::get_option( 'disable_woocommerce_cart_fragmentation', 'perform_settings' );
		if ( $disable_cart_fragmentation ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'disable_cart_fragmentation' ], 99 );
		}

		/**
		 * Disable WooCommerce Status Meta-box.
		 *
		 * @since 1.0.0
		 */
		$disable_status_metabox = Helpers::get_option( 'disable_woocommerce_status', 'perform_settings' );
		if ( $disable_status_metabox ) {
			add_action( 'wp_dashboard_setup', [ $this, 'disable_status_metabox' ] );
		}

		/**
		 * Disable Default WooCommerce Widgets.
		 *
		 * @since 1.0.0
		 */
		$disable_widgets = Helpers::get_option( 'disable_woocommerce_widgets', 'perform_settings' );
		if ( $disable_widgets ) {
			add_action( 'widgets_init', [ $this, 'disable_widgets' ], 99 );
		}
	}

	/**
	 * Disable WooCommerce Assets.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_assets() {
		if ( ! $this->has_woocommerce_conditionals() ) {
			return;
		}

		if (
			! is_woocommerce() &&
			! is_cart() &&
			! is_checkout() &&
			! is_account_page() &&
			! is_product() &&
			! is_product_category() &&
			! is_shop()
		) {
			// Dequeue WooCommerce default styles.
			wp_dequeue_style( 'woocommerce-general' );
			wp_dequeue_style( 'woocommerce-layout' );
			wp_dequeue_style( 'woocommerce-smallscreen' );
			wp_dequeue_style( 'woocommerce_frontend_styles' );
			wp_dequeue_style( 'woocommerce_fancybox_styles' );
			wp_dequeue_style( 'woocommerce_chosen_styles' );
			wp_dequeue_style( 'woocommerce_prettyPhoto_css' );

			// Dequeue WooCommerce default scripts.
			wp_dequeue_script( 'wc_price_slider' );
			wp_dequeue_script( 'wc-single-product' );
			wp_dequeue_script( 'wc-add-to-cart' );
			wp_dequeue_script( 'wc-checkout' );
			wp_dequeue_script( 'wc-add-to-cart-variation' );
			wp_dequeue_script( 'wc-single-product' );
			wp_dequeue_script( 'wc-cart' );
			wp_dequeue_script( 'wc-chosen' );
			wp_dequeue_script( 'woocommerce' );
			wp_dequeue_script( 'prettyPhoto' );
			wp_dequeue_script( 'prettyPhoto-init' );
			wp_dequeue_script( 'jquery-blockui' );
			wp_dequeue_script( 'jquery-placeholder' );
			wp_dequeue_script( 'fancybox' );
			wp_dequeue_script( 'jqueryui' );
		}
	}

	/**
	 * Disable Cart Fragmentation.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_cart_fragmentation() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
	}

	/**
	 * Disable Status Metabox.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_status_metabox() {
		remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
	}

	/**
	 * Disable Default WooCommerce Widgets.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_widgets() {
		$widgets = [
			'WC_Widget_Products',
			'WC_Widget_Product_Categories',
			'WC_Widget_Product_Tag_Cloud',
			'WC_Widget_Cart',
			'WC_Widget_Layered_Nav',
			'WC_Widget_Layered_Nav_Filters',
			'WC_Widget_Price_Filter',
			'WC_Widget_Product_Search',
			'WC_Widget_Recently_Viewed',
			'WC_Widget_Rating_Filter',
			'WC_Widget_Top_Rated_Products',
			'WC_Widget_Recent_Reviews',
		];

		foreach ( $widgets as $widget ) {
			if ( class_exists( $widget ) ) {
				unregister_widget( $widget );
			}
		}
	}

	/**
	 * Determine whether WooCommerce conditional helpers are available.
	 *
	 * @return bool
	 */
	private function has_woocommerce_conditionals(): bool {
		$conditionals = [
			'is_woocommerce',
			'is_cart',
			'is_checkout',
			'is_account_page',
			'is_product',
			'is_product_category',
			'is_shop',
		];

		$available_conditionals = array_filter( $conditionals, 'function_exists' );

		return count( $conditionals ) === count( $available_conditionals );
	}
}
