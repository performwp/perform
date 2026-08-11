# Menu cache key contract

Perform's classic navigation-menu cache is enabled only for anonymous frontend requests on classic themes by default. The cache signature includes the menu ID, theme location, output-affecting `wp_nav_menu()` arguments, walker/callback class identity, and locale. Full query state is intentionally excluded so the same menu can be reused across ordinary pages.

Sites with a query-aware custom walker can opt into the legacy query-sensitive behavior:

```php
add_filter( 'perform_menu_cache_query_sensitive', '__return_true' );
```

Custom bounded segmentation can be supplied without exposing values in stored metadata:

```php
add_filter(
	'perform_menu_cache_key_segments',
	static function ( $segments ) {
		$segments['audience'] = 'public';
		return $segments;
	}
);
```

Perform tracks at most 100 signatures per menu. When a new variant exceeds that limit, the oldest tracked cache entry is evicted. Menu updates purge every valid tracked signature. Missing, malformed, or non-array tracking data is treated as an empty list without warnings.
