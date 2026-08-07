/* eslint-env jest */

import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

window.performwpSettings = {
	cacheActivity: {
		actionUrl: 'https://example.com/wp-admin/admin-post.php',
		exportNonce: 'export-nonce',
		clearNonce: 'clear-nonce',
	},
};

const CacheStatsPanel = require( './CacheStatsPanel' ).default;

describe( 'CacheStatsPanel', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<template id="perform-cache-stats-template">
				<section><h2>Perform Cache Observability</h2></section>
			</template>
		`;
		window.confirm = jest.fn( () => true );
		window.URL.createObjectURL = jest.fn( () => 'blob:cache-activity' );
		window.URL.revokeObjectURL = jest.fn();
		jest.spyOn( window.HTMLAnchorElement.prototype, 'click' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		delete global.fetch;
		jest.restoreAllMocks();
	} );

	it( 'disables conflicting actions while exporting', async () => {
		let resolveFetch;
		global.fetch = jest.fn(
			() =>
				new Promise( ( resolve ) => {
					resolveFetch = resolve;
				} )
		);

		render( <CacheStatsPanel /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Export CSV' } ) );

		expect( screen.getByRole( 'button', { name: 'Exporting…' } ) ).toBeDisabled();
		expect( screen.getByRole( 'button', { name: 'Clear activity' } ) ).toBeDisabled();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Exporting…' );

		await act( async () => {
			resolveFetch( {
				ok: true,
				blob: async () => new Blob( [ 'Metric,Item,Value' ] ),
			} );
		} );
	} );

	it( 'requires confirmation before clearing activity', () => {
		window.confirm = jest.fn( () => false );
		global.fetch = jest.fn();

		render( <CacheStatsPanel /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear activity' } ) );

		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	it( 'shows clear progress and disables conflicting actions', () => {
		global.fetch = jest.fn( () => new Promise( () => {} ) );

		render( <CacheStatsPanel /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear activity' } ) );

		expect( screen.getByRole( 'button', { name: 'Clearing…' } ) ).toBeDisabled();
		expect( screen.getByRole( 'button', { name: 'Export CSV' } ) ).toBeDisabled();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Clearing…' );
	} );

	it( 'announces export failures and restores the actions', async () => {
		global.fetch = jest.fn().mockResolvedValue( { ok: false } );

		render( <CacheStatsPanel /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Export CSV' } ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Cache activity could not be exported.' );
		expect( screen.getByRole( 'button', { name: 'Export CSV' } ) ).toBeEnabled();
		expect( screen.getByRole( 'button', { name: 'Clear activity' } ) ).toBeEnabled();
	} );

	it( 'announces a localized non-JSON clear failure and allows a successful retry', async () => {
		const onClearSuccess = jest.fn();
		global.fetch = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: false,
				json: async () => {
					throw new SyntaxError( "Unexpected token '<'" );
				},
			} )
			.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					success: true,
					data: { redirect: 'https://example.com/wp-admin/options-general.php?tab=cache-stats' },
				} ),
			} );

		render( <CacheStatsPanel onClearSuccess={ onClearSuccess } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear activity' } ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Cache activity could not be cleared.' );
		expect( screen.getByRole( 'button', { name: 'Export CSV' } ) ).toBeEnabled();
		expect( screen.getByRole( 'button', { name: 'Clear activity' } ) ).toBeEnabled();

		fireEvent.click( screen.getByRole( 'button', { name: 'Clear activity' } ) );
		await waitFor( () =>
			expect( onClearSuccess ).toHaveBeenCalledWith(
				'https://example.com/wp-admin/options-general.php?tab=cache-stats'
			)
		);
		expect( global.fetch ).toHaveBeenCalledTimes( 2 );
	} );
} );
