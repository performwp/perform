<?php
/**
 * Modules Loader
 *
 * Encapsulates module instantiation and lifecycle handling.
 *
 * @since 2.0.0
 */

namespace Perform\Modules;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loader {
    /**
     * Settings array passed from plugin bootstrap.
     *
     * @var array
     */
    private $settings = [];

    public function __construct( array $settings = [] ) {
        $this->settings = $settings ?: Helpers::get_settings() ?: [];
    }

    /**
     * Register an array of module class names.
     *
     * @param array $modules List of fully-qualified class names.
     *
     * @return void
     */
    public function register_modules( array $modules ) {
        foreach ( $modules as $module_class ) {
            if ( ! class_exists( $module_class ) ) {
                continue;
            }

            try {
                $ref  = new \ReflectionClass( $module_class );
                $ctor = $ref->getConstructor();
                if ( $ctor && $ctor->getNumberOfParameters() > 0 ) {
                    $module = $ref->newInstance( $this->settings );
                } else {
                    $module = $ref->newInstance();
                }
            } catch ( \Throwable $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf( 'Perform: failed to instantiate %s — %s', $module_class, $e->getMessage() ) );
                }
                continue;
            }

            // If the module implements ModuleInterface, use its lifecycle.
            if ( $module instanceof ModuleInterface ) {
                if ( $module->should_load() ) {
                    $module->register();
                }
                continue;
            }

            // Backwards compatibility: if an older module exposes register(), call it.
            if ( method_exists( $module, 'register' ) ) {
                $module->register();
            }
        }
    }
}
