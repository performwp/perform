<?php
/**
 * Abstract Module
 *
 * Lightweight base for small setting modules.
 *
 * @since 2.0.0
 */

namespace Perform\Modules;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AbstractModule implements ModuleInterface {
    /**
     * Settings array.
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Option key this module looks for in the settings array.
     * Child classes can override.
     *
     * @var string|null
     */
    protected static $option_key = null;

    /**
     * Constructor.
     *
     * @param array $settings Optional settings to use (injected by loader).
     */
    public function __construct( array $settings = [] ) {
        $this->settings = ! empty( $settings ) ? $settings : Helpers::get_settings();
    }

    /**
     * Default should_load implementation: checks the configured option key.
     *
     * @return bool
     */
    public function should_load(): bool {
        if ( null === static::$option_key ) {
            return true;
        }

        return ! empty( $this->settings[ static::$option_key ] );
    }

    /**
     * Get this module's setting value from the injected settings first,
     * then fall back to persisted settings for backwards compatibility.
     *
     * @return mixed|null
     */
    protected function get_setting() {
        if ( null === static::$option_key ) {
            return null;
        }

        if ( is_array( $this->settings ) && array_key_exists( static::$option_key, $this->settings ) ) {
            return $this->settings[ static::$option_key ];
        }

        return Helpers::get_option( static::$option_key, 'perform_settings', null );
    }

    /**
     * Child classes must implement register() to attach hooks.
     *
     * @return void
     */
    abstract public function register(): void;
}
