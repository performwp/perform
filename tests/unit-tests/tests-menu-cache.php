<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\MenuCache\MenuCache;

final class Tests_Menu_Cache extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'enable_navigation_menu_cache' => 1,
			],
		];
		unset( $GLOBALS['perform_test_filters'], $GLOBALS['perform_test_is_block_theme'] );
		$GLOBALS['perform_test_transients']        = [];
		$GLOBALS['perform_test_is_admin']          = false;
		$GLOBALS['perform_test_is_user_logged_in'] = false;
		$GLOBALS['perform_test_locale']            = 'en_US';
		$GLOBALS['wp_query']                       = (object) [ 'query_vars_hash' => 'query-a' ];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_filters'], $GLOBALS['perform_test_is_block_theme'], $GLOBALS['perform_test_transients'], $GLOBALS['perform_test_is_admin'], $GLOBALS['perform_test_is_user_logged_in'], $GLOBALS['perform_test_locale'], $GLOBALS['wp_query'] );
	}

	public function test_menu_cache_does_not_load_for_block_themes_by_default() {
		$GLOBALS['perform_test_is_block_theme'] = true;

		$menu_cache = new MenuCache();

		$this->assertFalse( $menu_cache->should_load() );
	}

	public function test_menu_cache_filter_can_opt_in_hybrid_block_themes() {
		$GLOBALS['perform_test_is_block_theme'] = true;
		$GLOBALS['perform_test_filters']        = [
			'perform_menu_cache_supports_current_theme' => true,
		];

		$menu_cache = new MenuCache();

		$this->assertTrue( $menu_cache->should_load() );
	}

	public function test_default_signature_is_stable_across_irrelevant_query_vars() {
		$menu_cache = new MenuCache();
		$args       = $this->get_menu_args();

		$this->assertSame( '<nav>Primary</nav>', $menu_cache->cache_nav_menu( '<nav>Primary</nav>', $args ) );
		$GLOBALS['wp_query']->query_vars_hash = 'query-b';

		$this->assertSame( '<nav>Primary</nav>', $menu_cache->cache_nav_menu_output( null, $this->get_menu_args() ) );
	}

	public function test_query_sensitive_filter_preserves_compatibility_for_dynamic_walkers() {
		$GLOBALS['perform_test_filters']['perform_menu_cache_query_sensitive'] = true;
		$menu_cache = new MenuCache();
		$menu_cache->cache_nav_menu( '<nav>Page A</nav>', $this->get_menu_args() );

		$GLOBALS['wp_query']->query_vars_hash = 'query-b';
		$this->assertNull( $menu_cache->cache_nav_menu_output( null, $this->get_menu_args() ) );
	}

	public function test_tracking_is_bounded_and_evicts_the_oldest_variant() {
		$menu_cache = new MenuCache();
		$first_key  = '';

		for ( $index = 0; $index <= MenuCache::TRACKED_SIGNATURE_LIMIT; ++$index ) {
			$args             = $this->get_menu_args();
			$args->menu_class = 'menu-' . $index;
			$menu_cache->cache_nav_menu( '<nav>' . $index . '</nav>', $args );
			if ( 0 === $index ) {
				$keys      = array_keys( $GLOBALS['perform_test_transients'] );
				$first_key = (string) current(
					array_filter(
						$keys,
						static function ( $key ) {
							return 0 === strpos( $key, 'perform_menu_cache_' ) && false === strpos( $key, 'menuid_' );
						}
					)
				);
			}
		}

		$tracked = json_decode( $GLOBALS['perform_test_transients']['perform_menu_cache_menuid_42'], true );
		$this->assertCount( MenuCache::TRACKED_SIGNATURE_LIMIT, $tracked );
		$this->assertArrayNotHasKey( $first_key, $GLOBALS['perform_test_transients'] );
	}

	public function test_malformed_tracking_transient_fails_quietly_and_recovers() {
		$GLOBALS['perform_test_transients']['perform_menu_cache_menuid_42'] = '{not-json';
		$menu_cache = new MenuCache();

		$menu_cache->cache_nav_menu( '<nav>Primary</nav>', $this->get_menu_args() );
		$tracked = json_decode( $GLOBALS['perform_test_transients']['perform_menu_cache_menuid_42'], true );

		$this->assertIsArray( $tracked );
		$this->assertCount( 1, $tracked );
	}

	public function test_menu_update_purges_every_valid_tracked_variant() {
		$first  = md5( 'first' );
		$second = md5( 'second' );
		$GLOBALS['perform_test_transients'][ 'perform_menu_cache_' . $first ]  = 'one';
		$GLOBALS['perform_test_transients'][ 'perform_menu_cache_' . $second ] = 'two';
		$GLOBALS['perform_test_transients']['perform_menu_cache_menuid_42']    = wp_json_encode( [ $first, 'invalid', $second ] );

		( new MenuCache() )->update_nav_menu_cache( 42, [] );

		$this->assertArrayNotHasKey( 'perform_menu_cache_' . $first, $GLOBALS['perform_test_transients'] );
		$this->assertArrayNotHasKey( 'perform_menu_cache_' . $second, $GLOBALS['perform_test_transients'] );
		$this->assertSame( [], json_decode( $GLOBALS['perform_test_transients']['perform_menu_cache_menuid_42'], true ) );
	}

	private function get_menu_args() {
		return (object) [
			'menu'            => (object) [
				'term_id' => 42,
				'name'    => 'Primary',
			],
			'theme_location'  => 'primary',
			'menu_class'      => 'menu',
			'menu_id'         => 'primary-menu',
			'container'       => 'nav',
			'container_class' => 'site-navigation',
			'depth'           => 2,
			'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
			'item_spacing'    => 'preserve',
			'fallback_cb'     => false,
			'walker'          => null,
		];
	}
}
