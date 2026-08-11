/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

window.performwpSettings = {
	siteInventoryUrl: 'https://example.com/wp-json/perform/v1/site-inventory',
	restNonce: 'rest-nonce',
};

const SiteInventoryPanel = require( './SiteInventoryPanel' ).default;

const readyInventory = {
	status: 'ready',
	generatedAt: 1700000000,
	isStale: false,
	isMultisite: false,
	environment: {
		wordpress: { state: 'measured-locally', value: '7.0.3' },
		php: { state: 'measured-locally', value: '8.3.0' },
		database: { state: 'not-available', value: '' },
		perform: { state: 'measured-locally', value: '1.8.0' },
	},
	plugins: {
		state: 'measured-locally',
		installedCount: 2,
		activeCount: 1,
		items: [
			{ slug: 'perform', name: 'Perform', version: '1.8.0', active: true },
			{ slug: 'inactive', name: 'Inactive Plugin', version: '1.0.0', active: false },
		],
	},
	theme: { state: 'measured-locally', name: 'Test Theme' },
	postTypes: {
		totalCount: 1,
		items: [
			{
				name: 'book',
				label: 'Books',
				contentCount: 42,
				public: true,
				ownership: { label: 'Custom or extension', confidence: 'unknown' },
			},
		],
	},
	configuration: { showOnFront: 'page' },
	perform: { enabledCount: 2 },
	signals: {
		localInventory: 'measured-locally',
		pluginIdentity: 'measured-locally',
		labPerformance: 'needs-separate-test',
		fieldMetrics: 'needs-separate-test',
	},
	collection: { durationMs: 12.34, bounded: true },
};

describe( 'SiteInventoryPanel', () => {
	afterEach( () => {
		delete global.fetch;
		jest.restoreAllMocks();
	} );

	it( 'explains the manual local-only empty state', () => {
		render( <SiteInventoryPanel initialInventory={ { status: 'not-run' } } /> );

		expect( screen.getByRole( 'button', { name: 'Generate inventory' } ) ).toBeEnabled();
		expect( screen.getByText( 'Generate the first inventory' ) ).toBeInTheDocument();
		expect( screen.getByText( /contains no post content/ ) ).toBeInTheDocument();
	} );

	it( 'distinguishes local facts from signals that need separate testing', () => {
		render( <SiteInventoryPanel initialInventory={ readyInventory } /> );

		expect( screen.getByText( '12.34 ms' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Core Web Vitals field data' ) ).toBeInTheDocument();
		expect( screen.getAllByText( 'Needs a separate test' ) ).toHaveLength( 2 );
		expect( screen.getByText( 'Books' ) ).toBeInTheDocument();
		expect( screen.queryByText( /harmful/i ) ).toBeInTheDocument();
	} );

	it( 'announces progress and replaces the snapshot after refresh', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				message: 'Site inventory refreshed.',
				inventory: { ...readyInventory, plugins: { ...readyInventory.plugins, activeCount: 3 } },
			} ),
		} );

		render( <SiteInventoryPanel initialInventory={ readyInventory } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Refresh inventory' } ) );

		expect( screen.getByRole( 'button', { name: 'Generating inventory…' } ) ).toBeDisabled();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Generating the local site inventory…' );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Site inventory refreshed.' ) );
		expect( global.fetch ).toHaveBeenCalledWith(
			'https://example.com/wp-json/perform/v1/site-inventory',
			expect.objectContaining( {
				method: 'POST',
				headers: { 'X-WP-Nonce': 'rest-nonce' },
			} )
		);
	} );

	it( 'shows a recoverable REST error', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( { message: 'Synthetic inventory failure.' } ),
		} );

		render( <SiteInventoryPanel initialInventory={ readyInventory } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Refresh inventory' } ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Synthetic inventory failure.' );
		expect( screen.getByRole( 'button', { name: 'Refresh inventory' } ) ).toBeEnabled();
	} );
} );
