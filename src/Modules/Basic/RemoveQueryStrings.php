<?php
/**
 * Remove Query Strings Module
 *
 * @since 2.0.0
 */

namespace Perform\Modules\Basic;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RemoveQueryStrings extends AbstractModule {
    protected static $option_key = 'remove_query_strings';

    public function register(): void {
        // Bailout, if accessed via admin.
        if ( is_admin() ) {
            return;
        }

        add_filter( 'script_loader_src', [ $this, 'process_query_string_removal' ], 15 );
        add_filter( 'style_loader_src', [ $this, 'process_query_string_removal' ], 15 );
    }

    public function process_query_string_removal( $url ) {
        $output = preg_split( '/(&ver|\?ver)/', $url );
        return $output[0];
    }
}
