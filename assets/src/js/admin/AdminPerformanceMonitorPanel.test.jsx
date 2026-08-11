/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

window.performwpSettings = {
	adminMonitorNonce: 'monitor-nonce',
};

const AdminPerformanceMonitorPanel = require( './AdminPerformanceMonitorPanel' ).default;

const readySnapshot = {
	enabled: true,
	status: 'ready',
	contexts: [
		{
			key: 'ajax:heartbeat:edit-content',
			type: 'ajax',
			identifier: 'heartbeat',
			capabilityBucket: 'edit-content',
			count: 4,
			maxDurationMs: 1400,
			averageDurationMs: 900,
			maxMemoryBytes: 140000000,
			maxQueryCount: 120,
			status: 'review',
			reasons: [ 'slow-request', 'high-memory', 'high-query-count' ],
		},
	],
};

describe( 'AdminPerformanceMonitorPanel', () => {
	afterEach( () => {
		delete global.fetch;
		jest.restoreAllMocks();
	} );

	it( 'explains disabled zero-overhead mode and routes to Advanced', () => {
		const onNavigate = jest.fn();
		render(
			<AdminPerformanceMonitorPanel
				initialSnapshot={ { enabled: false, contexts: [] } }
				onNavigate={ onNavigate }
			/>
		);

		expect( screen.getByText( 'Monitoring is off' ) ).toBeInTheDocument();
		expect( screen.getByText( /registers no request-capture hooks/ ) ).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'button', { name: 'Enable in Advanced' } ) );
		expect( onNavigate ).toHaveBeenCalledWith( 'advanced' );
	} );

	it( 'shows aggregate-only metrics and actionable threshold reasons', () => {
		render( <AdminPerformanceMonitorPanel initialSnapshot={ readySnapshot } /> );

		expect( screen.getByText( 'heartbeat' ) ).toBeInTheDocument();
		expect( screen.getByText( /Slow request, High memory, High query count/ ) ).toBeInTheDocument();
		expect( screen.getByText( /does not store full URLs/ ) ).toBeInTheDocument();
		expect( screen.queryByText( /SAVEQUERIES enabled/i ) ).not.toBeInTheDocument();
	} );

	it( 'requires confirmation and announces clear progress', async () => {
		jest.spyOn( window, 'confirm' ).mockReturnValue( true );
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				success: true,
				data: {
					message: 'Admin performance data cleared.',
					snapshot: { enabled: true, status: 'empty', contexts: [] },
				},
			} ),
		} );

		render( <AdminPerformanceMonitorPanel initialSnapshot={ readySnapshot } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear collected data' } ) );

		expect( screen.getByRole( 'button', { name: 'Clearing…' } ) ).toBeDisabled();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Clearing admin performance data…' );
		await waitFor( () =>
			expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Admin performance data cleared.' )
		);
		expect( screen.getByText( 'Collecting the first admin contexts' ) ).toBeInTheDocument();
	} );

	it( 'restores the clear action after a request failure', async () => {
		jest.spyOn( window, 'confirm' ).mockReturnValue( true );
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( { success: false, data: { message: 'Synthetic clear failure.' } } ),
		} );

		render( <AdminPerformanceMonitorPanel initialSnapshot={ readySnapshot } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear collected data' } ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Synthetic clear failure.' );
		expect( screen.getByRole( 'button', { name: 'Clear collected data' } ) ).toBeEnabled();
	} );
} );
