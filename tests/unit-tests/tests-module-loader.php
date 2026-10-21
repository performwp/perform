<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\AbstractModule;
use Perform\Modules\Loader;
use Perform\Modules\ModuleInterface;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Focused module doubles keep this loader contract test self-contained.
final class Perform_Test_Enabled_Module extends AbstractModule {
	protected static $option_key = 'enabled_module';

	public static $registrations = 0;

	public function register(): void {
		++self::$registrations;
	}
}

final class Perform_Test_Disabled_Module extends AbstractModule {
	protected static $option_key = 'disabled_module';

	public static $registrations = 0;

	public function register(): void {
		++self::$registrations;
	}
}

final class Perform_Test_Throwing_Module implements ModuleInterface {
	public function __construct() {
		throw new RuntimeException( 'Expected constructor failure.' );
	}

	public function should_load(): bool {
		return true;
	}

	public function register(): void {}
}

final class Perform_Test_Invalid_Module {}

final class Tests_Module_Loader extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_actions']             = [];
		$GLOBALS['perform_test_options']             = [];
		$GLOBALS['perform_test_module_events']       = [];
		Perform_Test_Enabled_Module::$registrations  = 0;
		Perform_Test_Disabled_Module::$registrations = 0;

		add_action(
			'perform_module_invalid',
			static function ( $module_class ) {
				$GLOBALS['perform_test_module_events'][] = [ 'invalid', $module_class ];
			}
		);
		add_action(
			'perform_module_load_error',
			static function ( $module_class ) {
				$GLOBALS['perform_test_module_events'][] = [ 'load_error', $module_class ];
			}
		);
	}

	public function test_registers_enabled_module_only_once(): void {
		$loader = new Loader( [ 'enabled_module' => 1 ] );

		$loader->register_modules( [ Perform_Test_Enabled_Module::class ] );
		$loader->register_modules( [ Perform_Test_Enabled_Module::class ] );

		$this->assertSame( 1, Perform_Test_Enabled_Module::$registrations );
	}

	public function test_does_not_register_disabled_module(): void {
		$loader = new Loader( [ 'disabled_module' => 0 ] );

		$loader->register_modules( [ Perform_Test_Disabled_Module::class ] );

		$this->assertSame( 0, Perform_Test_Disabled_Module::$registrations );
	}

	public function test_reports_invalid_module_without_registering_it(): void {
		$loader = new Loader();

		$loader->register_modules( [ Perform_Test_Invalid_Module::class ] );

		$this->assertContains( [ 'invalid', Perform_Test_Invalid_Module::class ], $GLOBALS['perform_test_module_events'] );
	}

	public function test_reports_constructor_failure_and_continues(): void {
		$loader = new Loader( [ 'enabled_module' => 1 ] );

		$loader->register_modules(
			[
				Perform_Test_Throwing_Module::class,
				Perform_Test_Enabled_Module::class,
			]
		);

		$this->assertContains( [ 'load_error', Perform_Test_Throwing_Module::class ], $GLOBALS['perform_test_module_events'] );
		$this->assertSame( 1, Perform_Test_Enabled_Module::$registrations );
	}
}
// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound
