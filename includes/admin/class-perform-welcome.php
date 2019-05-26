<?php
/**
 * Perform - Welcome Screen.
 *
 * @since 1.2.1
 *
 * @package    Perform
 * @subpackage Welcome Screen
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perform_Welcome Class
 *
 * A general class for Welcome and Credits pages.
 *
 * @since 1.2.1
 */
class Perform_Welcome {

	/**
	 * Minimum Capability
	 *
	 * @since  1.2.1
	 * @access public
	 *
	 * @var string The capability users should have to view the page
	 */
	public $minimum_capability = 'manage_options';

	/**
	 * Get things started
	 *
	 * @since  1.2.1
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

		$excluded_pages = array(
			'perform-credits',
			'perform-changelog',
		);

		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $excluded_pages, true ) ) {
			remove_all_actions( 'admin_notices' );
		}

		add_action( 'admin_menu', array( $this, 'admin_menus' ) );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_init', array( $this, 'welcome' ) );
	}

	/**
	 * Register the Dashboard Pages which are later hidden but these pages
	 * are used to render the Welcome and Credits pages.
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return void
	 */
	public function admin_menus() {
		list( $display_version ) = explode( '-', PERFORM_VERSION );

		// Changelog Page.
		add_dashboard_page(
			esc_html__( 'What\'s New', 'perform' ),
			esc_html__( 'What\'s New', 'perform' ),
			$this->minimum_capability,
			'perform-changelog',
			array( $this, 'changelog_screen' )
		);

		// Credits Page.
		add_dashboard_page(
			/* translators: %s: Perform version */
			sprintf( esc_html__( 'Perform %s - Credits', 'perform' ), $display_version ),
			esc_html__( 'The people that build Perform', 'perform' ),
			$this->minimum_capability,
			'perform-credits',
			array( $this, 'credits_screen' )
		);
	}

	/**
	 * Hide Individual Dashboard Pages
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return void
	 */
	public function admin_head() {

		remove_submenu_page( 'index.php', 'perform-changelog' );
		remove_submenu_page( 'index.php', 'perform-getting-started' );
		remove_submenu_page( 'index.php', 'perform-credits' );

	}

	/**
	 * Navigation tabs
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return void
	 */
	public function tabs() {
		$selected = isset( $_GET['page'] ) ? perform_clean( $_GET['page'] ) : 'perform-changelog';
		?>
		<div class="nav-tab-wrapper perform-nav-tab-wrapper">
			<a class="nav-tab <?php echo $selected == 'perform-changelog' ? 'nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'perform-changelog' ), 'index.php' ) ) ); ?>">
				<?php esc_html_e( 'What\'s New', 'perform' ); ?>
			</a>
			<a class="nav-tab <?php echo $selected == 'perform-credits' ? 'nav-tab-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'perform-credits' ), 'index.php' ) ) ); ?>">
				<?php esc_html_e( 'Credits', 'perform' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * The header section for the welcome screen.
	 *
	 * @since  1.2.1
	 * @access publc
	 *
	 * @return mixed
	 */
	public function get_welcome_header() {

		// Badge for welcome page.
		list( $display_version ) = explode( '-', PERFORM_VERSION );

		$page = isset( $_GET['page'] ) ? perform_clean( $_GET['page'] ) : '';

		// Bailout, if page not exists.
		if ( empty( $page ) ) {
			return;
		}

		switch ( $page ) {

			case 'perform-changelog':
				$title   = sprintf( __( 'What\'s New in Perform %s', 'perform' ), $display_version );
				$content = __( 'Perform is regularly updated with new features and fixes to ensure that your site performance is up-to-date. We always recommend keeping Perform up to date with the latest version.', 'perform' );
				break;

			case 'perform-credits':
				$title   = sprintf( __( 'GitHub Contributors', 'perform' ) );
				$content = sprintf(
					/* translators: %s: https://github.com/mehul0810/perform */
					__( 'Perform is developed and maintained by Mehul Gohil and vibrant open source community. If you are interested in contributing please visit the <a href="%s" target="_blank">GitHub Repo</a>.', 'perform' ),
					esc_url( 'https://github.com/mehul0810/perform' )
				);

				break;

			default:
				$title   = get_admin_page_title();
				$content = '';
				break;

		}

		?>
		<div class="perform-welcome-header">

			<div class="perform-welcome-header-inner">

				<h1 class="perform-welcome-h1"><?php esc_html_e( $title ); ?></h1>

				<?php $this->social_media_elements(); ?>

				<p class="perform-welcome-text"><?php _e( $content ); ?></p>

				<?php $this->get_newsletter(); ?>

				<div class="perform-badge">
					<img src="<?php echo PERFORM_PLUGIN_URL . 'assets/dist/images/perform-icon.svg'; ?>"/>
					<p>
						<?php
						printf(
							/* translators: %s: Perform version */
							esc_html__( 'Version %s', 'perform' ),
							$display_version
						);
						?>
					</p>
				</div>

			</div>
		</div>

		<?php
	}

	/**
	 * Render Changelog Screen
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return void
	 */
	public function changelog_screen() {
		?>
		<div class="perform-welcome-wrap">

			<?php $this->get_welcome_header(); ?>

			<?php $this->tabs(); ?>

			<div class="perform-welcome-content-wrap perform-changelog-wrap">

				<p class="perform-welcome-content-intro"><?php printf( __( 'See what\'s new in version %1$s of Perform! If you feel we\'ve missed a fix or there\'s a feature you\'d like to see developed please <a href="%2$s" target="_blank">contact support</a>.', 'perform' ), PERFORM_VERSION, 'https://wordpress.org/support/plugin/perform/' ); ?></p>

				<div class="perform-changelog">
					<?php echo $this->parse_readme(); ?>
				</div>

			</div>

			<?php $this->support_widgets(); ?>

		</div>
		<?php
	}

	/**
	 * Render Credits Screen
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return void
	 */
	public function credits_screen() {
		?>
		<div class="wrap perform-welcome-wrap">

			<?php $this->get_welcome_header(); ?>

			<?php $this->tabs(); ?>

			<div class="perform-welcome-content-wrap perform-changelog-wrap">

				<p class="perform-welcome-content-intro">

					<?php
					printf(
						/* translators: %s: https://github.com/mehul0810/perform */
						__( 'Perform is developed & maintained by Mehul Gohil and vibrant open source community. If you are interested in contributing please visit the <a href="%s" target="_blank">GitHub Repo</a>.', 'perform' ),
						esc_url( 'https://github.com/mehul0810/perform' )
					);
					?>
				</p>

				<?php echo $this->contributors(); ?>

			</div>

		</div>
		<?php
	}


	/**
	 * Parse the Perform readme.txt file
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return string $readme HTML formatted readme file
	 */
	public function parse_readme() {

		$file = file_exists( PERFORM_PLUGIN_DIR . 'readme.txt' ) ? PERFORM_PLUGIN_DIR . 'readme.txt' : null;

		if ( ! $file ) {
			$readme = '<p>' . esc_html__( 'No valid changlog was found.', 'perform' ) . '</p>';
		} else {
			$readme = file_get_contents( $file );
			$readme = nl2br( esc_html( $readme ) );
			$readme = explode( '== Changelog ==', $readme );
			$readme = end( $readme );

			$readme = preg_replace( '/`(.*?)`/', '<code>\\1</code>', $readme );
			$readme = preg_replace( '/[\040]\*\*(.*?)\*\*/', ' <strong>\\1</strong>', $readme );
			$readme = preg_replace( '/[\040]\*(.*?)\*/', ' <em>\\1</em>', $readme );
			$readme = preg_replace( '/= (.*?) =/', '<h4>\\1</h4>', $readme );
			$readme = preg_replace( '/\[(.*?)\]\((.*?)\)/', '<a href="\\2">\\1</a>', $readme );
		}

		return $readme;
	}

	/**
	 * Render Contributors List
	 *
	 * @uses  Perform_Welcome::get_contributors()
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return string $contributor_list HTML formatted list of all the contributors for Perform
	 */
	public function contributors() {
		$contributors = $this->get_contributors();

		if ( empty( $contributors ) ) {
			return '';
		}

		$contributor_list = '<ul class="perform-contributor-group">';

		foreach ( $contributors as $contributor ) {
			$contributor_list .= '<li class="perform-contributor">';
			$contributor_list .= sprintf(
				'<a href="%1$s" target="_blank"><img src="%2$s" width="64" height="64" class="gravatar" alt="%3$s" /><span>%3$s</span></a>',
				esc_url( 'https://github.com/' . $contributor->login ),
				esc_url( $contributor->avatar_url ),
				esc_attr( $contributor->login )
			);
			$contributor_list .= '</li>';
		}

		$contributor_list .= '</ul>';

		return $contributor_list;
	}

	/**
	 * Retrieve list of contributors from GitHub.
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return array $contributors List of contributors.
	 */
	public function get_contributors() {
		$contributors = get_transient( 'perform_contributors' );

		if ( false !== $contributors ) {
			return $contributors;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/mehul0810/perform/contributors', array( 'sslverify' => false ) );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$contributors = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_array( $contributors ) ) {
			return array();
		}

		set_transient( 'perform_contributors', $contributors, HOUR_IN_SECONDS );

		return $contributors;
	}

	/**
	 * Social Media Like Buttons
	 *
	 * Various social media elements
	 *
	 * @since  1.2.1
	 * @access public
	 *
	 * @return mixed
	 */
	public function social_media_elements() {
		?>

		<div class="social-items-wrap">

			<a href="https://twitter.com/perform_wp" class="twitter-follow-button" data-show-count="false">
				<?php
				printf(
					/* translators: %s: Perform twitter user @perform_wp */
					esc_html_e( 'Follow %s', 'perform' ),
					'@perform_wp'
				);
				?>
			</a>
			<script>!function( d, s, id ) {
					var js, fjs = d.getElementsByTagName( s )[ 0 ], p = /^http:/.test( d.location ) ? 'http' : 'https';
					if ( !d.getElementById( id ) ) {
						js = d.createElement( s );
						js.id = id;
						js.src = p + '://platform.twitter.com/widgets.js';
						fjs.parentNode.insertBefore( js, fjs );
					}
				}( document, 'script', 'twitter-wjs' );
			</script>

		</div>
		<!--/.social-items-wrap -->
		<?php
	}

	/**
	 * Support widgets.
	 *
	 * @since  1.2.1
	 * @access public
	 *
	 * @return mixed
	 */
	public function support_widgets() {
		?>
		<div class="perform-welcome-widgets perform-clearfix">
			<div class="perform-welcome-widgets__inner">
				<div class="perform-welcome-widgets__col perform-welcome-widgets__support">
					<div class="perform-welcome-widgets__col-inner">
						<h3><?php esc_html_e( 'Support', 'perform' ); ?></h3>
						<p><?php esc_html_e( 'Inevitably questions arise while improving loading speed of your WordPress site.', 'perform' ); ?></p>

						<a href="https://wordpress.org/support/plugin/perform/" class="perform-welcome-widgets__link"
						   target="_blank"><?php esc_html_e( 'How support works', 'perform' ); ?></a>

					</div>
				</div>
				<div class="perform-welcome-widgets__col perform-welcome-widgets__documentation">
					<div class="perform-welcome-widgets__col-inner">
						<h3><?php esc_html_e( 'Documentation', 'perform' ); ?></h3>
						<p><?php esc_html_e( 'Learn the ins and outs of Perform with well organized and clearly written documentation.', 'perform' ); ?></p>
						<a href="https://performwp.com/docs/?utm_source=welcome-screen&utm_medium=getting-started" class="perform-welcome-widgets__link"
						   target="_blank"><?php esc_html_e( 'Check out the docs', 'perform' ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Newsletter
	 *
	 * Returns the main newsletter form
	 *
	 * @access public
	 * @since  1.2.1
	 */
	public function get_newsletter() {
		$current_user = wp_get_current_user();
		?>
		<div class="perform-newsletter-form-wrap">

			<p class="perform-newsletter-intro"><?php esc_html_e( 'Sign up for the below to stay informed about important updates, release notes, performance optimization tips, and more! We\'ll never spam you.', 'perform' ); ?></p>

			<form action="//gmail.us20.list-manage.com/subscribe/post?u=f564ff5d41e52fd42cccea8fc&amp;id=16caa16477"
				  method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form"
				  class="perform-newsletter-form validate"
				  target="_blank">
				<div class="perform-newsletter-confirmation">
					<p><?php esc_html_e( 'To complete your subscription, click the confirmation link in your email. Thank you!', 'perform' ); ?></p>
				</div>

				<table class="form-table perform-newsletter-form">
					<tr valign="middle">
						<td>
							<label for="mce-EMAIL"
								   class="screen-reader-text"><?php esc_html_e( 'Email Address (required)', 'perform' ); ?></label>
							<input type="email" name="EMAIL" id="mce-EMAIL"
								   placeholder="<?php esc_attr_e( 'Email Address (required)', 'perform' ); ?>"
								   class="required email" value="<?php echo $current_user->user_email; ?>" required>
						</td>
						<td>
							<label for="mce-FNAME"
								   class="screen-reader-text"><?php esc_html_e( 'First Name', 'perform' ); ?></label>
							<input type="text" name="FNAME" id="mce-FNAME"
								   placeholder="<?php esc_attr_e( 'First Name', 'perform' ); ?>" class=""
								   value="<?php echo $current_user->user_firstname; ?>" required>
						</td>
						<td>
							<label for="mce-LNAME"
								   class="screen-reader-text"><?php esc_html_e( 'Last Name', 'perform' ); ?></label>
							<input type="text" name="LNAME" id="mce-LNAME"
								   placeholder="<?php esc_attr_e( 'Last Name', 'perform' ); ?>" class=""
								   value="<?php echo $current_user->user_lastname; ?>">
						</td>
						<td>
							<input type="submit" name="subscribe" id="mc-embedded-subscribe"
								   class="button button-primary"
								   value="<?php esc_attr_e( 'Subscribe', 'perform' ); ?>">
						</td>
					</tr>
				</table>
			</form>

			<div style="position: absolute; left: -5000px;">
				<input type="text" name="b_3ccb75d68bda4381e2f45794c_12a081aa13" tabindex="-1" value="">
			</div>

		</div>

		<script type='text/javascript' src='//s3.amazonaws.com/downloads.mailchimp.com/js/mc-validate.js'></script>
		<script type='text/javascript'>(
				function( $ ) {
					window.fnames = new Array();
					window.ftypes = new Array();
					fnames[ 0 ] = 'EMAIL';
					ftypes[ 0 ] = 'email';
					fnames[ 1 ] = 'FNAME';
					ftypes[ 1 ] = 'text';
					fnames[ 2 ] = 'LNAME';
					ftypes[ 2 ] = 'text';

					$( 'form[name="mc-embedded-subscribe-form"]' ).removeAttr( 'novalidate' );

					//Successful submission
					$( 'form[name="mc-embedded-subscribe-form"]' ).on( 'submit', function() {

						var email_field = $( this ).find( '#mce-EMAIL' ).val();
						if ( !email_field ) {
							return false;
						}
						$( this ).find( '.perform-newsletter-confirmation' ).show();
						$( this ).find( '.perform-newsletter-form' ).hide();

					} );

				}( jQuery )
			);
			var $mcj = jQuery.noConflict( true );
		</script>
		<!--End mc_embed_signup-->
		<?php
	}

	/**
	 * Sends user to the Welcome page on first activation of Give.
	 *
	 * @access public
	 * @since  1.2.1
	 *
	 * @return void
	 */
	public function welcome() {

		// Bail if no activation redirect
		if ( ! get_transient( '_perform_activation_redirect' ) || wp_doing_ajax() ) {
			return;
		}

		// Delete the redirect transient.
		delete_transient( '_perform_activation_redirect' );

		// Bail if activating from network, or bulk.
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		$upgrade = get_option( 'perform_version_upgraded_from' );

		if ( ! $upgrade ) {
			// First time install.
			wp_safe_redirect( admin_url( 'index.php?page=perform-changelog' ) );
			exit;
		} else {
			// Upgrading the plugin.
			wp_safe_redirect( admin_url( 'index.php?page=perform-changelog' ) );
			exit;
		}
	}

}

new Perform_Welcome();
