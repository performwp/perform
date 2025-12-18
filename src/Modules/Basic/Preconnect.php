<?php
/**
 * Perform | Preconnect Module
 *
 * @package Perform
 * @subpackage Modules
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\Basic;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preconnect Module Class.
 *
 * @since 2.0.0
 */
class Preconnect extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static function get_option_key(): string {
		return 'preconnect';
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_head', [ $this, 'preconnect' ], 1 );
	}

	/**
	 * Preconnect.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function preconnect() {
		$preconnect = $this->get_setting();

		if ( ! empty( $preconnect ) && is_array( $preconnect ) ) {
			foreach ( $preconnect as $url ) {
				?>
				<link rel="preconnect" href="<?php echo esc_url( $url ); ?>"/>
				<?php
			}
		}
	}
}
