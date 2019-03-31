<?php
/**
 * Admin Filters.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Admin Footer
 * @author     Mehul Gohil
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add rating links to the admin dashboard.
 *
 * @since 1.0.0
 *
 * @param string $footer_text The existing footer text
 *
 * @return string
 */
function perform_admin_rate_us( $footer_text ) {

	$current_screen = get_current_screen();

	if ( true == stristr( $current_screen->base, 'perform' ) ) {

		$rate_text = sprintf(
			/* translators: %s: Link to 5 star rating */
			__( 'If you like <strong>Perform</strong> please leave us a %s rating. It takes a minute and helps a lot. Thanks in advance!', 'perform' ),
			'<a href="https://wordpress.org/support/plugin/perform/reviews/?filter=5#postform" target="_blank" class="perform-rating-link" style="text-decoration:none;" data-rated="' . esc_attr__( 'Thanks :)', 'perform' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
		);

		return $rate_text;
	} else {
		return $footer_text;
	}
}

add_filter( 'admin_footer_text', 'perform_admin_rate_us' );
