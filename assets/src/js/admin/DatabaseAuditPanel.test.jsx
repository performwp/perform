/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

window.performwpSettings = {
	databaseAuditNonce: 'audit-nonce',
};

const DatabaseAuditPanel = require( './DatabaseAuditPanel' ).default;

const readyAudit = {
	status: 'ready',
	generatedAt: 1700000000,
	expiresAt: 1700086400,
	isStale: false,
	isMultisite: false,
	totals: { count: 42, sizeBytes: 912345 },
	thresholdBytes: 800000,
	objectCache: { persistent: true },
	largestOptions: [
		{
			name: 'perform_settings',
			sizeBytes: 2048,
			autoload: 'auto-on',
			ownership: { label: 'Perform', confidence: 'high' },
		},
	],
};

describe( 'DatabaseAuditPanel', () => {
	afterEach( () => {
		delete global.fetch;
		jest.restoreAllMocks();
	} );

	it( 'explains the manual and privacy-safe empty state', () => {
		render( <DatabaseAuditPanel initialAudit={ { status: 'not-run' } } /> );

		expect( screen.getByRole( 'button', { name: 'Run audit' } ) ).toBeEnabled();
		expect( screen.getByText( 'Run the first local audit' ) ).toBeInTheDocument();
		expect( screen.getByText( /makes no external requests/ ) ).toBeInTheDocument();
	} );

	it( 'shows bounded results without option values or automatic actions', () => {
		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );

		expect( screen.getByText( '891.0 KB' ) ).toBeInTheDocument();
		expect( screen.getByText( 'perform_settings' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Review recommended' ) ).toBeInTheDocument();
		expect( screen.queryByText( /delete/i ) ).not.toBeInTheDocument();
	} );

	it( 'announces progress and replaces the snapshot after refresh', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				success: true,
				data: {
					message: 'Database audit refreshed.',
					audit: { ...readyAudit, totals: { count: 50, sizeBytes: 700000 } },
				},
			} ),
		} );

		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Refresh audit' } ) );

		expect( screen.getByRole( 'button', { name: 'Running audit…' } ) ).toBeDisabled();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Running the database audit…' );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Database audit refreshed.' ) );
		expect( screen.getByText( '50' ) ).toBeInTheDocument();
	} );

	it( 'shows a recoverable error when refresh fails', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( { success: false, data: { message: 'Synthetic audit failure.' } } ),
		} );

		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Refresh audit' } ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Synthetic audit failure.' );
		expect( screen.getByRole( 'button', { name: 'Refresh audit' } ) ).toBeEnabled();
	} );
} );
