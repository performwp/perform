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
		$stored_settings = Helpers::get_settings();
		$this->settings  = ! empty( $settings ) ? $settings : ( is_array( $stored_settings ) ? $stored_settings : [] );
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

			if ( ! is_subclass_of( $module_class, ModuleInterface::class ) ) {
				do_action( 'perform_module_invalid', $module_class, ModuleInterface::class );
				continue;
			}

			try {
				// Modules extending AbstractModule support settings injection.
				if ( is_subclass_of( $module_class, AbstractModule::class ) ) {
					$module = new $module_class( $this->settings );
				} else {
					$module = new $module_class();
				}
			} catch ( \Throwable $e ) {
				/**
				 * Fires when a module cannot be instantiated.
				 *
				 * @param string     $module_class Module class name.
				 * @param string     $message      Error message.
				 * @param \Throwable $e            Exception/error object.
				 */
				do_action( 'perform_module_load_error', $module_class, $e->getMessage(), $e );

				continue;
			}

			if ( $module->should_load() ) {
				$module->register();
			}
		}
	}
}
