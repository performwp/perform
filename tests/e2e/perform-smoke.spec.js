const { test, expect, request } = require( '@playwright/test' );

async function loginAsAdmin( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( process.env.WP_USERNAME || 'admin' );
	await page.locator( '#user_pass' ).fill( process.env.WP_PASSWORD || 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

test( 'settings save, Assets Manager, and page cache smoke paths work', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );

	await page.goto( '/wp-admin/options-general.php?page=perform_settings' );
	await expect( page.locator( '#perform-settings-page' ) ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Save Settings' } ) ).toBeVisible();
	await expect( page.getByText( 'General Settings' ) ).toBeVisible();

	await page.getByRole( 'tab', { name: 'Assets' } ).click();
	await page.getByRole( 'checkbox', { name: 'Enable Assets Manager' } ).check();

	await page.getByRole( 'tab', { name: 'Cache' } ).click();
	await page.getByRole( 'checkbox', { name: 'Enable Full-Page Cache' } ).check();

	await page.getByRole( 'button', { name: 'Save Settings' } ).click();
	await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();

	await page.goto( '/?perform' );
	await expect( page.locator( '#perform-assets-manager' ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Assets Manager' } ) ).toBeVisible();

	const anonymous = await request.newContext( { baseURL } );
	await anonymous.get( '/' );
	const cachedResponse = await anonymous.get( '/' );
	expect( cachedResponse.headers()[ 'x-perform-cache' ] ).toMatch( /HIT|STALE/ );
	await anonymous.dispose();
} );
