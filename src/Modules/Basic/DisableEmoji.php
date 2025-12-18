<?php
/**
 * Disable Emoji Module
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

class DisableEmoji extends AbstractModule {
    protected static $option_key = 'disable_emojis';

    public function register(): void {
        // Register the main disabling action on init.
        add_action( 'init', [ $this, 'disable_emojis' ] );
    }

    public function disable_emojis() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );

        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

        add_filter( 'tiny_mce_plugins', [ $this, 'disable_emojis_from_tinymce' ] );
        add_filter( 'wp_resource_hints', [ $this, 'disable_emojis_from_dns_prefetch' ], 10, 2 );
        add_filter( 'emoji_svg_url', '__return_false' );
    }

    public function disable_emojis_from_tinymce( $plugins ) {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, [ 'wpemoji' ] );
        }

        return [];
    }

    public function disable_emojis_from_dns_prefetch( $urls, $relationType ) {
        if ( 'dns-prefetch' === $relationType ) {
            $svgUrl = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2.2.1/svg/' );
            $urls   = array_diff( $urls, [ $svgUrl ] );
        }

        return $urls;
    }
}
