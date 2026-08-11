const { test, expect, request } = require( '@playwright/test' );
const { mkdir, readFile } = require( 'node:fs/promises' );

async function dismissPluginOnboarding( page ) {
	const skipButton = page.getByText( 'Skip', { exact: true } );
	if ( await skipButton.isVisible() ) {
		await skipButton.click();
		await page.waitForLoadState( 'domcontentloaded' );
		return true;
	}

	return false;
}

async function openAdminPage( page, path ) {
	await page.goto( path );
	if ( await dismissPluginOnboarding( page ) ) {
		await page.goto( path );
	}
}

async function login(
	page,
	username = process.env.WP_USERNAME || 'admin',
	password = process.env.WP_PASSWORD || 'password'
) {
	if ( 'admin' === username ) {
		await page.goto( '/wp-admin/' );
		if ( await page.locator( '#wpadminbar' ).isVisible() ) {
			await dismissPluginOnboarding( page );
			return;
		}
	}

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( password );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	await dismissPluginOnboarding( page );
}

test( 'settings save, Assets Manager, and page cache smoke paths work', async ( { page, baseURL } ) => {
	await login( page );

	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings' );
	await expect( page.locator( '#perform-settings-page' ) ).toBeVisible();
	await expect( page.getByRole( 'tab', { name: 'Dashboard' } ) ).toBeVisible();
	await expect( page.getByText( 'Performance health' ) ).toBeVisible();
	await expect( page.getByText( 'Core Web Vitals need a separate test' ) ).toBeVisible();
	await expect( page.getByText( /unverified speed score/ ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Runtime Diagnostics' } ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toHaveCount( 0 );
	await mkdir( 'test-results/proof', { recursive: true } );
	await page.screenshot( { path: 'test-results/proof/performance-health-dashboard-desktop.png', fullPage: true } );

	await page.getByRole( 'tab', { name: 'General' } ).click();
	await expect( page.getByText( 'General Settings' ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Runtime Diagnostics' } ) ).toHaveCount( 0 );
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toBeVisible();
	await expect( page ).toHaveURL( /[?&]tab=general(?:&|$)/ );

	await page.getByRole( 'tab', { name: 'Assets' } ).click();
	await page.getByRole( 'checkbox', { name: 'Enable Assets Manager' } ).check();
	await page.getByRole( 'textbox', { name: 'Preconnect' } ).fill( `//ci-${ Date.now() }.example.com` );

	await page.getByRole( 'tab', { name: 'Cache', exact: true } ).click();
	await page.getByRole( 'checkbox', { name: 'Enable Full-Page Cache' } ).check();

	const saveButton = page.getByRole( 'button', { name: 'Save Settings' } );
	await expect( saveButton ).toBeEnabled();
	await saveButton.click();
	await expect( page.getByText( /Settings saved/ ) ).toBeVisible();
	await expect( page ).toHaveURL( /[?&]tab=cache(?:&|$)/ );

	await page.goto( '/?perform' );
	await expect( page.locator( '#perform-assets-manager' ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Assets Manager' } ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Reset' } ) ).toBeVisible();

	const anonymous = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Cookie: 'playground_auto_login_already_happened=1',
		},
	} );
	await anonymous.get( '/' );
	const cachedResponse = await anonymous.get( '/' );
	expect( cachedResponse.headers()[ 'x-perform-cache' ] ).toMatch( /HIT|STALE/ );
	await anonymous.dispose();
} );

test( 'settings field rows stack beneath their descriptions at narrow widths', async ( { page } ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	await login( page );
	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings' );
	await expect( page.getByText( 'Core Web Vitals need a separate test' ) ).toBeVisible();
	await mkdir( 'test-results/proof', { recursive: true } );
	await page.screenshot( { path: 'test-results/proof/performance-health-dashboard-mobile.png', fullPage: true } );
	const dashboardHasHorizontalOverflow = await page.evaluate(
		() => document.documentElement.scrollWidth > document.documentElement.clientWidth
	);
	expect( dashboardHasHorizontalOverflow ).toBeFalsy();

	for ( const tabName of [ 'General', 'Bloat', 'Assets', 'CDN', 'Cache', 'Advanced' ] ) {
		await page.getByRole( 'tab', { name: tabName, exact: true } ).click();
		const row = page.locator( '.perform-settings-field' ).first();
		await expect( row ).toBeVisible();
		const isStacked = await row.evaluate( ( element ) => {
			const copy = element.querySelector( '.perform-settings-field__copy' ).getBoundingClientRect();
			const control = element.querySelector( '.perform-settings-field__control' ).getBoundingClientRect();

			return control.top >= copy.bottom;
		} );
		expect( isStacked ).toBeTruthy();
	}
} );

test( 'Site Inventory refresh is private, bounded, and responsive', async ( { page } ) => {
	await login( page );
	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings&tab=inventory' );

	await expect( page.getByRole( 'tab', { name: 'Site Inventory' } ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page.getByText( 'Private and local' ) ).toBeVisible();
	await expect( page.getByText( /no post content, option values, private URLs/i ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'System health' } ) ).toBeVisible();
	await expect( page.getByText( 'PHP version' ) ).toBeVisible();
	await expect( page.getByText( 'Persistent object cache' ) ).toBeVisible();
	await expect( page.getByText( /Paths, credentials, request data/ ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: /Generate inventory|Refresh inventory/ } ) ).toBeVisible();
	await page.getByRole( 'button', { name: /Generate inventory|Refresh inventory/ } ).click();
	await expect( page.getByRole( 'status' ) ).toContainText( 'Site inventory refreshed.' );
	await expect( page.getByRole( 'heading', { name: 'Environment' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Installed plugins' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Registered content types' } ) ).toBeVisible();
	await expect( page.getByText( 'Core Web Vitals field data' ) ).toBeVisible();
	await expect( page.getByText( 'Needs a separate test' ).last() ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toHaveCount( 0 );

	await mkdir( 'test-results/proof', { recursive: true } );
	await page.screenshot( { path: 'test-results/proof/site-inventory-desktop.png', fullPage: true } );

	await page.setViewportSize( { width: 390, height: 844 } );
	await expect( page.locator( '.perform-site-inventory__summary' ) ).toBeVisible();
	await page.screenshot( { path: 'test-results/proof/site-inventory-mobile.png', fullPage: true } );
	const hasHorizontalOverflow = await page.evaluate(
		() => document.documentElement.scrollWidth > document.documentElement.clientWidth
	);
	expect( hasHorizontalOverflow ).toBeFalsy();
} );

test( 'Scheduled-task pressure is bounded, private, resettable, and responsive', async ( { page } ) => {
	await login( page );
	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings&tab=scheduled-tasks' );

	await expect( page.getByRole( 'tab', { name: 'Scheduled Tasks' } ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page.getByText( 'Read-only and bounded' ) ).toBeVisible();
	await expect( page.getByText( /excludes hook arguments, payloads, URLs, and user data/ ) ).toBeVisible();
	await page.getByRole( 'button', { name: /Run check|Refresh check/ } ).click();
	await expect( page.getByRole( 'status' ) ).toContainText( 'Scheduled-task diagnostic refreshed.' );
	await expect( page.getByText( 'Most frequent scheduled hooks' ) ).toBeVisible();
	await expect( page.getByText( /Frequency does not prove that a hook is harmful/ ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toHaveCount( 0 );

	await mkdir( 'test-results/proof', { recursive: true } );
	await page.screenshot( { path: 'test-results/proof/cron-pressure-desktop.png', fullPage: true } );
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.screenshot( { path: 'test-results/proof/cron-pressure-mobile.png', fullPage: true } );
	const hasHorizontalOverflow = await page.evaluate(
		() => document.documentElement.scrollWidth > document.documentElement.clientWidth
	);
	expect( hasHorizontalOverflow ).toBeFalsy();

	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await page.getByRole( 'button', { name: 'Clear snapshot' } ).click();
	await expect( page.getByRole( 'status' ) ).toContainText( 'Scheduled-task diagnostic cleared.' );
	await expect( page.getByRole( 'button', { name: 'Run check' } ) ).toBeVisible();
} );

test( 'Admin Performance Monitor is opt-in, bounded, private, clearable, and responsive', async ( { page } ) => {
	await login( page );
	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings&tab=advanced' );

	const monitorToggle = page.getByRole( 'checkbox', { name: 'Admin Performance Monitor' } );
	if ( ! ( await monitorToggle.isChecked() ) ) {
		await monitorToggle.check();
	}
	await page.getByRole( 'button', { name: 'Save Settings' } ).click();
	await expect( page.getByText( /Settings saved/ ) ).toBeVisible();

	await page.goto( '/wp-admin/edit.php' );
	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings&tab=admin-monitor' );
	await expect( page.getByRole( 'tab', { name: 'Admin Monitor' } ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page.getByText( 'Aggregate-only and per site' ) ).toBeVisible();
	await expect( page.getByText( /does not store full URLs, query arguments, request payloads/ ) ).toBeVisible();
	await expect( page.getByText( 'edit-post' ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toHaveCount( 0 );

	await mkdir( 'test-results/proof', { recursive: true } );
	await page.screenshot( { path: 'test-results/proof/admin-performance-monitor-desktop.png', fullPage: true } );
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.screenshot( { path: 'test-results/proof/admin-performance-monitor-mobile.png', fullPage: true } );
	const hasHorizontalOverflow = await page.evaluate(
		() => document.documentElement.scrollWidth > document.documentElement.clientWidth
	);
	expect( hasHorizontalOverflow ).toBeFalsy();

	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await page.getByRole( 'button', { name: 'Clear collected data' } ).click();
	await expect( page.getByRole( 'status' ) ).toContainText( 'Admin performance data cleared.' );
	await expect( page.getByText( 'Collecting the first admin contexts' ) ).toBeVisible();

	await page.getByRole( 'tab', { name: 'Advanced' } ).click();
	await page.getByRole( 'checkbox', { name: 'Admin Performance Monitor' } ).uncheck();
	await page.getByRole( 'button', { name: 'Save Settings' } ).click();
	await expect( page.getByText( /Settings saved/ ) ).toBeVisible();
} );

test( 'Cache Stats tab preserves legacy routing, actions, and keyboard access', async ( { page } ) => {
	await login( page );

	await openAdminPage( page, '/wp-admin/options-general.php?page=perform_settings' );
	const cacheStatsTab = page.getByRole( 'tab', { name: 'Cache Stats' } );
	await cacheStatsTab.focus();
	await expect( cacheStatsTab ).toBeFocused();
	await expect( cacheStatsTab ).toHaveCSS( 'outline-style', 'solid' );
	await page.keyboard.press( 'Enter' );

	await expect( cacheStatsTab ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page ).toHaveURL( /[?&]tab=cache-stats(?:&|$)/ );
	await expect( page.getByRole( 'heading', { name: 'Perform Cache Observability' } ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Purge Site Page Cache' } ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Export CSV' } ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Clear activity' } ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toHaveCount( 0 );

	const downloadPromise = page.waitForEvent( 'download' );
	await page.getByRole( 'button', { name: 'Export CSV' } ).click();
	const download = await downloadPromise;
	expect( download.suggestedFilename() ).toMatch( /^perform-cache-activity-\d{4}-\d{2}-\d{2}\.csv$/ );
	const csv = await readFile( await download.path(), 'utf8' );
	expect( csv ).toContain( 'Metric,Item,Value' );
	const hits = csv.match( /^Hits,,(\d+)$/m );
	expect( hits ).not.toBeNull();
	expect( Number( hits[ 1 ] ) ).toBeGreaterThanOrEqual( 120 );
	expect( csv ).toContain( '"Top Misses",/sample-page/,3' );
	await expect( page.getByRole( 'status' ) ).toContainText( 'Cache activity exported.' );

	await page.route( '**/wp-admin/admin-post.php', async ( route ) => {
		if ( route.request().postData()?.includes( 'action=perform_clear_cache_activity' ) ) {
			await route.fulfill( {
				status: 500,
				contentType: 'text/html',
				body: '<h1>Temporary upstream error</h1>',
			} );
			return;
		}

		await route.continue();
	} );
	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await page.getByRole( 'button', { name: 'Clear activity' } ).click();
	await expect( page.getByRole( 'alert' ) ).toContainText( 'Cache activity could not be cleared.' );
	await expect( page.getByRole( 'button', { name: 'Export CSV' } ) ).toBeEnabled();
	await expect( page.getByRole( 'button', { name: 'Clear activity' } ) ).toBeEnabled();
	await page.unroute( '**/wp-admin/admin-post.php' );

	await openAdminPage(
		page,
		'/wp-admin/options-general.php?page=perform_cache_observability&redirect_to=https%3A%2F%2Fattacker.example.com'
	);
	await expect( page ).toHaveURL( /options-general\.php\?page=perform_settings&tab=cache-stats$/ );
	await expect( page.getByRole( 'heading', { name: 'Perform Cache Observability' } ) ).toBeVisible();

	await openAdminPage(
		page,
		'/wp-admin/options-general.php?page=perform_cache_stats&redirect_to=https%3A%2F%2Fattacker.example.com'
	);
	await expect( page ).toHaveURL( /options-general\.php\?page=perform_settings&tab=cache-stats$/ );

	await page.getByRole( 'button', { name: 'Purge Site Page Cache' } ).click();
	await expect( page ).toHaveURL( /page=perform_settings&tab=cache-stats&perform_cache_purged=1/ );
	await expect( page.getByText( 'The local page-cache generation has been invalidated.' ) ).toBeVisible();

	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await page.getByRole( 'button', { name: 'Clear activity' } ).click();
	await expect( page ).toHaveURL( /perform_cache_activity_cleared=1/ );
	await expect( page.getByText( 'Cache activity cleared.' ) ).toBeVisible();
} );

test( 'lower-privilege users cannot access the legacy or canonical Cache Stats routes', async ( { page } ) => {
	await login( page );
	await page.context().clearCookies( { name: /^wordpress_/ } );
	await login( page, 'perform-editor', 'password' );

	const legacyResponse = await page.goto( '/wp-admin/options-general.php?page=perform_cache_observability' );
	expect( legacyResponse.status() ).not.toBe( 302 );
	await expect( page ).toHaveURL( /page=perform_cache_observability$/ );
	await expect( page.getByText( 'Sorry, you are not allowed to access this page.' ) ).toBeVisible();
	await expect( page.getByText( 'Perform Cache Observability' ) ).toHaveCount( 0 );

	const canonicalResponse = await page.goto( '/wp-admin/options-general.php?page=perform_settings&tab=cache-stats' );
	expect( canonicalResponse.status() ).not.toBe( 302 );
	await expect( page ).toHaveURL( /page=perform_settings&tab=cache-stats$/ );
	await expect( page.getByText( 'Sorry, you are not allowed to access this page.' ) ).toBeVisible();
	await expect( page.getByRole( 'tab', { name: 'Cache Stats' } ) ).toHaveCount( 0 );

	const protectedActions = [
		[ 'perform_purge_page_cache', 'You are not allowed to purge the page cache.' ],
		[ 'perform_export_cache_activity', 'You are not allowed to export cache activity.' ],
		[ 'perform_clear_cache_activity', 'You are not allowed to clear cache activity.' ],
		[ 'perform_retry_cloudflare_cleanup', 'You are not allowed to retry Cloudflare cleanup.' ],
		[ 'perform_acknowledge_cloudflare_residual', 'You are not allowed to acknowledge Cloudflare cleanup.' ],
	];

	for ( const [ action, denialMessage ] of protectedActions ) {
		const response = await page.request.post( '/wp-admin/admin-post.php', {
			form: {
				action,
				_wpnonce: 'invalid-for-editor-capability-check',
			},
		} );
		expect( response.status() ).not.toBe( 302 );
		expect( await response.text() ).toContain( denialMessage );
	}

	const adminMonitorResponse = await page.request.post( '/wp-admin/admin-ajax.php', {
		form: {
			action: 'perform_clear_admin_performance_monitor',
			nonce: 'invalid-for-editor-capability-check',
		},
	} );
	expect( adminMonitorResponse.status() ).toBe( 403 );
	expect( await adminMonitorResponse.json() ).toMatchObject( { success: false } );

	const cronAuditResponse = await page.request.post( '/wp-admin/admin-ajax.php', {
		form: {
			action: 'perform_refresh_cron_pressure_audit',
			nonce: 'invalid-for-editor-capability-check',
		},
	} );
	expect( cronAuditResponse.status() ).toBe( 403 );
	expect( await cronAuditResponse.json() ).toMatchObject( { success: false } );
} );
