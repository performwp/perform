<?php
/**
 * Perform Module - Change Login URL.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Change_Login_URL
 *
 * @since 1.0.0
 */
class Perform_Change_Login_URL {
	
	/**
	 * Perform_Change_Login_URL constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		// Add required action hooks.
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded', 2 ) );
		add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );
		add_action( 'setup_theme', array( $this, 'disable_customize_php', 1 ) );
		
		// Add required filter hooks.
		add_filter( 'site_url', array( $this, 'site_url', 10, 4 ) );
		add_filter( 'network_site_url', array( $this, 'network_site_url', 10, 3 ) );
		add_filter( 'wp_redirect', array( $this, 'wp_redirect', 10, 2 ) );
		add_filter( 'site_option_welcome_email', array( $this, 'welcome_email' ) );
		
		// Remove required action hook.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
		
		
	}
	
	/**
	 * This function will prepare site url based on updated login url.
	 *
	 * @param string $url     URL.
	 * @param string $path    Path.
	 * @param string $scheme  Scheme.
	 * @param int    $blog_id WP Blog ID.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function site_url( $url, $path, $scheme, $blog_id ) {
		return $this->filter_wp_login( $url, $scheme );
	}
	
	/**
	 * This function will prepare network site url based on updated login url.
	 *
	 * @param string $url    URL.
	 * @param string $path   Path.
	 * @param string $scheme Scheme.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function network_site_url( $url, $path, $scheme ) {
		return $this->filter_wp_login($url, $scheme);
	}
	
	/**
	 * This function will redirect to specific URL based on updated login url.
	 *
	 * @param string $location URL to redirect to.
	 * @param string $status   Status.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function wp_redirect( $location, $status ) {
		return $this->filter_wp_login( $location );
	}
	
	/**
	 * This function will help filter WP login URL based on the admin settings.
	 *
	 * @param string $url    URL.
	 * @param string $scheme Scheme. Default: NULL.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function filter_wp_login( $url, $scheme = null ) {
		
		// wp-login.php Being Requested.
		if( false !== strpos( $url, 'wp-login.php' ) ) {
			
			// Set HTTPS Scheme if SSL.
			if( is_ssl() ) {
				$scheme = 'https';
			}
			
			// Check for Query String and Craft New Login URL.
			$query_string = explode('?', $url);
			
			if ( isset( $query_string[1] ) ) {
				parse_str( $query_string[1], $query_string );
				$url = add_query_arg( $query_string, $this->login_url( $scheme ) );
			} else {
				$url = $this->login_url( $scheme );
			}
		}
		
		// Return Finished Login URL.
		return $url;
	}
	
	/**
	 * This function is used to prepare login URL.
	 *
	 * @param string $scheme Scheme.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function login_url( $scheme = null ) {
		
		// Return Full New Login URL Based on Permalink Structure.
		if ( get_option('permalink_structure' ) ) {
			return $this->trailingslashit( home_url( '/', $scheme ) . $this->login_slug());
		}
		else {
			return home_url( '/', $scheme ) . '?' . $this->login_slug();
		}
	}
	
	/**
	 * This function is used to prepare permalink trailing slash based on the change in login url.
	 *
	 * @param string $string Text.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function trailingslashit( $string ) {
		
		// Check for Permalink Trailing Slash and Add to String.
		if( ( substr( get_option( 'permalink_structure' ), -1, 1 ) ) === '/' ) {
			return trailingslashit( $string );
		}
		else {
			return untrailingslashit( $string );
		}
	}
	
	/**
	 * This function is used to prepare login slug based on the change in login url.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool|string
	 */
	public function login_slug() {
		
		$login_url = perform_get_option( 'login_url', 'perform_common' );

		// Return Login URL Slug if Available.
		if ( ! empty( $login_url ) ) {
			return $login_url;
		}

		return false;
	}
	
	/**
	 * This function is used to do required changes on plugins_loaded action hook.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function plugins_loaded() {

		global $pagenow, $is_wp_login;

		$server_data = filter_input_array( INPUT_SERVER );

		// Parse Requested URI.
		$url  = wp_parse_url( $server_data['REQUEST_URI'] );
		$path = untrailingslashit( $url['path'] );
		$slug = $this->login_slug();
		
		// Non Admin wp-login.php URL.
		if (
			! is_admin() &&
			(
				strpos( rawurldecode( $server_data['REQUEST_URI'] ), 'wp-login.php' ) !== false ||
				site_url( 'wp-login', 'relative' ) === $path
			)
		) {
			
			// Set Flag.
			$is_wp_login = true;
			
			// Prevent Redirect to Hidden Login.
			$server_data['REQUEST_URI'] = $this->trailingslashit( '/' . str_repeat( '-/', 10 ) );
			$pagenow = 'index.php';
		} elseif (
			$path === home_url($slug, 'relative') ||
			(
				! get_option('permalink_structure') &&
				isset( $_GET[ $slug ] ) &&
				empty( $_GET[ $slug ] )
			)
		) {
			
			// Override Current Page w/ wp-login.php.
			$pagenow = 'wp-login.php';
		}
	}
	
	/**
	 * This function is used to do required changes on wp_loaded action hook.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wp_loaded() {
		
		global $pagenow;
		global $is_wp_login;
		
		// Parse Requested URI.
		$URI = parse_url($_SERVER['REQUEST_URI']);
		
		// Disable Normal WP-Admin.
		if(is_admin() && !is_user_logged_in() && !defined('DOING_AJAX') && $pagenow !== 'admin-post.php' && (isset($_GET) && empty($_GET['adminhash']) && empty($_GET['newuseremail']))) {
			wp_die(__('This has been disabled.', 'perform'), 403);
		}
		
		// Requesting Hidden Login Form - Path Mismatch.
		if($pagenow === 'wp-login.php' && $URI['path'] !== $this->trailingslashit($URI['path']) && get_option('permalink_structure')) {
			
			// Local Redirect to Hidden Login URL.
			$URL = $this->trailingslashit( $this->login_url()) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
			wp_safe_redirect($URL);
			die();
		} elseif($is_wp_login) {
			wp_die(__('This has been disabled.', 'perform'), 403);
		}
		// Requesting Hidden Login Form.
		elseif($pagenow === 'wp-login.php') {
			
			// Declare Global Variables.
			global $error, $interim_login, $action, $user_login;
			
			// User Already Logged In.
			if(is_user_logged_in() && !isset($_REQUEST['action'])) {
				wp_safe_redirect(admin_url());
				die();
			}
			
			//Include Login Form
			@require_once ABSPATH . 'wp-login.php';
			die();
		}
	}
	
	/**
	 * This function is used to disable customize.php.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_customize_php() {
		
		global $pagenow;
		
		// Disable customize.php from Redirecting to Login URL.
		if ( ! is_user_logged_in() && 'customize.php' === $pagenow ) {
			wp_die( __( 'This has been disabled.', 'perform' ), 403 );
		}
	}
	
	/**
	 * This function is used to send Welcome Email.
	 *
	 * @param string $text Welcome Text.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function welcome_email( $text ) {
		
		// Check for Custom Login URL and Replace.
		if ( ! empty( $this->login_slug() ) ) {
			$text = str_replace( array( 'wp-login.php', 'wp-admin' ), $this->trailingslashit( $this->login_slug() ), $text);
		}
		
		return $text;
	}
	
}