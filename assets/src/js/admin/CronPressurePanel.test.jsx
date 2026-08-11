/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

window.performwpSettings = { cronPressureNonce: 'cron-nonce' };

const CronPressurePanel = require( './CronPressurePanel' ).default;

const readyAudit = {
	status: 'ready',
	wpCronDisabled: true,
	totals: { scanned: 12, due: 3, overdue: 2, recurring: 8, peakCluster: 4, truncated: false },
	guidance: { status: 'review', heading: 'Some scheduled work needs review', message: 'Compare again.' },
	frequentHooks: [ { hook: 'perform_cache_preload', count: 5 } ],
};

describe( 'CronPressurePanel', () => {
	afterEach( () => {
		delete global.fetch;
		jest.restoreAllMocks();
	} );

	it( 'shows bounded aggregate evidence without claiming a harmful hook', () => {
		render( <CronPressurePanel initialAudit={ readyAudit } /> );

		expect( screen.getByText( 'Built-in WP-Cron spawning is disabled' ) ).toBeInTheDocument();
		expect( screen.getByText( 'perform_cache_preload' ) ).toBeInTheDocument();
		expect( screen.getByText( /excludes hook arguments, payloads, URLs, and user data/ ) ).toBeInTheDocument();
		expect( screen.getByText( /does not prove that a hook is harmful/ ) ).toBeInTheDocument();
	} );

	it( 'refreshes the snapshot with visible progress', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( { success: true, data: { message: 'Refreshed.', audit: readyAudit } } ),
		} );

		render( <CronPressurePanel initialAudit={ { status: 'not-run' } } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Run check' } ) );

		expect( screen.getByRole( 'button', { name: 'Checking scheduled tasks…' } ) ).toBeDisabled();
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Refreshed.' ) );
		expect( global.fetch ).toHaveBeenCalledWith( window.ajaxurl, expect.objectContaining( { method: 'POST' } ) );
	} );

	it( 'requires confirmation before clearing the saved snapshot', async () => {
		jest.spyOn( window, 'confirm' ).mockReturnValue( true );
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				success: true,
				data: { message: 'Cleared.', audit: { status: 'not-run' } },
			} ),
		} );

		render( <CronPressurePanel initialAudit={ readyAudit } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear snapshot' } ) );

		expect( window.confirm ).toHaveBeenCalledWith(
			'Clear the saved scheduled-task diagnostic? No cron events will be changed.'
		);
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Cleared.' ) );
	} );
} );
