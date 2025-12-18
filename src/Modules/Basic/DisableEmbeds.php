<?php
/**
 * Disable Embeds Module
 *
 * @since 2.0.0
 */

namespace Perform\Modules\Basic;

use Perform\Includes\Helpers;
use Perform\Modules\AbstractModule;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DisableEmbeds extends AbstractModule {
    protected static $option_key = 'disable_embeds';

    public function register(): void {
        add_action( 'init', [ $this, 'disable_embeds' ], 9999 );
    }

    public function disable_embeds() {
        global $wp;
        if ( isset( $wp->public_query_vars ) && is_array( $wp->public_query_vars ) ) {
            $wp->public_query_vars = array_diff( $wp->public_query_vars, [ 'embed' ] );
        }

        remove_action( 'rest_api_init', 'wp_oembed_register_route' );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );

        remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
        remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

        add_filter( 'embed_oembed_discover', '__return_false' );
        add_filter( 'tiny_mce_plugins', [ $this, 'disable_embeds_from_tinymce' ] );
        add_filter( 'rewrite_rules_array', [ $this, 'disable_embeds_from_rewrites' ] );
    }

    public function disable_embeds_from_tinymce( $plugins ) {
        return array_diff( $plugins, [ 'wpembed' ] );
    }

    public function disable_embeds_from_rewrites( $rules ) {
        foreach ( $rules as $rule => $rewrite ) {
            if ( false !== strpos( $rewrite, 'embed=true' ) ) {
                unset( $rules[ $rule ] );
            }
        }

        return $rules;
    }
}
