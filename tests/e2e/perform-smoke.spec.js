const { test, expect, request } = require( '@playwright/test' );

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
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toBeVisible();
	await expect( page.getByRole( 'tab', { name: 'Dashboard' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Perform overview' } ) ).toBeVisible();
	await expect( page.getByText( 'Runtime diagnostics currently marked ready.' ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Runtime Diagnostics' } ) ).toBeVisible();

	await page.getByRole( 'tab', { name: 'General' } ).click();
	await expect( page.getByText( 'General Settings' ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Runtime Diagnostics' } ) ).toHaveCount( 0 );
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
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toHaveCount( 0 );

	await openAdminPage(
		page,
		'/wp-admin/options-general.php?page=perform_cache_observability&redirect_to=https%3A%2F%2Fattacker.example.com'
	);
	await expect( page ).toHaveURL( /options-general\.php\?page=perform_settings&tab=cache-stats$/ );
	await expect( page.getByRole( 'heading', { name: 'Perform Cache Observability' } ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Purge Site Page Cache' } ).click();
	await expect( page ).toHaveURL( /page=perform_settings&tab=cache-stats&perform_cache_purged=1/ );
	await expect( page.getByText( 'The local page-cache generation has been invalidated.' ) ).toBeVisible();
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
} );
