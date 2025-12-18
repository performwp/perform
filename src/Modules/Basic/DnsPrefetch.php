<?php
/**
 * Perform | DNS Prefetch Module
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
 * DNS Prefetch Module Class.
 *
 * @since 2.0.0
 */
class DnsPrefetch extends AbstractModule {
    protected static $option_key = 'dns_prefetch';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_head', [ $this, 'dns_prefetch' ], 1 );
	}

	/**
	 * DNS Prefetch.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function dns_prefetch() {
		$dns_prefetch = $this->get_setting();

		if ( ! empty( $dns_prefetch ) && is_array( $dns_prefetch ) ) {
			foreach ( $dns_prefetch as $url ) {
				?>
				<link rel="dns-prefetch" href="<?php echo esc_url( $url ); ?>"/>
				<?php
			}
		}
	}
}
