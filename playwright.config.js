const { defineConfig, devices } = require( '@playwright/test' );

const baseURL = process.env.PERFORM_E2E_BASE_URL || process.env.WP_BASE_URL || 'http://localhost:8888';

module.exports = defineConfig( {
	testDir: './tests/e2e',
	testMatch: /.*\.spec\.js/,
	timeout: 60 * 1000,
	expect: {
		timeout: 15 * 1000,
	},
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'line' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
			},
		},
	],
} );
