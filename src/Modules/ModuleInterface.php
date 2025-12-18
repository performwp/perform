<?php
/**
 * Module Interface
 *
 * Defines the lightweight module lifecycle contract used by Perform modules.
 *
 * @since 2.0.0
 */

namespace Perform\Modules;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ModuleInterface {
    /**
     * Determine whether the module should be loaded.
     *
     * Implementations should read settings/environment and return true
     * only when the module needs to register its hooks.
     *
     * @return bool
     */
    public function should_load(): bool;

    /**
     * Register hooks and filters for this module.
     *
     * This method should be idempotent and safe to call more than once.
     *
     * @return void
     */
    public function register(): void;
}
