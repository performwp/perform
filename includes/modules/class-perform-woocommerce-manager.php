<?php
/**
 * Perform Module - WooCommerce Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage WooCommerce Manager
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_WooCommerce_Manager
 *
 * @since 1.0.0
 */
class Perform_WooCommerce_Manager {

	/**
	 * Perform_WooCommerce_Manager constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

		// Bailout, if WooCommerce is not active.
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return;
		}

		/**
		 * Disable Default WooCommerce Assets.
		 *
		 * @since 1.0.0
		 */
		$disable_assets = perform_get_option( 'disable_woocommerce_assets', 'perform_woocommerce' );
		if ( $disable_assets ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_assets' ), 99 );
		}

		/**
		 * Disable Complete Cart Fragmentation.
		 *
		 * @since 1.0.0
		 */
		$disable_cart_fragmentation = perform_get_option( 'disable_woocommerce_cart_fragmentation', 'perform_woocommerce' );
		if ( $disable_cart_fragmentation ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_cart_fragmentation' ), 99 );
		}

		/**
		 * Disable WooCommerce Status Meta-box.
		 *
		 * @since 1.0.0
		 */
		$disable_status_metabox = perform_get_option( 'disable_woocommerce_status', 'perform_woocommerce' );
		if ( $disable_status_metabox ) {
			add_action( 'wp_dashboard_setup', array( $this, 'disable_status_metabox' ) );
		}

		/**
		 * Disable Default WooCommerce Widgets.
		 *
		 * @since 1.0.0
		 */
		$disable_widgets  = perform_get_option( 'disable_woocommerce_widgets', 'perform_woocommerce' );
		if ( $disable_widgets ) {
			add_action( 'widgets_init', array( $this, 'disable_widgets' ), 99 );
		}
	}

	public function disable_assets() {

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

	public function disable_cart_fragmentation() {

		wp_dequeue_script( 'wc-cart-fragments' );

	}

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

		unregister_widget( 'WC_Widget_Products' );
		unregister_widget( 'WC_Widget_Product_Categories' );
		unregister_widget( 'WC_Widget_Product_Tag_Cloud' );
		unregister_widget( 'WC_Widget_Cart' );
		unregister_widget( 'WC_Widget_Layered_Nav' );
		unregister_widget( 'WC_Widget_Layered_Nav_Filters' );
		unregister_widget( 'WC_Widget_Price_Filter' );
		unregister_widget( 'WC_Widget_Product_Search' );
		unregister_widget( 'WC_Widget_Recently_Viewed' );

	}
}
