import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

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
		delete window.performwpSettings.autoloadManagerNonce;
		delete global.fetch;
		jest.restoreAllMocks();
	} );

	it( 'refreshes measured totals after a successful one-click change', async () => {
		window.performwpSettings.autoloadManagerNonce = 'manager-nonce';
		global.fetch = jest.fn().mockImplementation( async ( url, options ) => {
			const action = options.body.get( 'action' );
			let data = {
				audit: {
					...readyAudit,
					totals: { count: 41, sizeBytes: 700000 },
				},
			};
			if ( action === 'perform_list_autoload_options' ) {
				data = {
					supported: true,
					items: [
						{
							name: 'large_option',
							sizeBytes: 2048,
							enabled: true,
							token: 'token',
						},
					],
					total: 1,
					pageSize: 25,
				};
			} else if ( action === 'perform_change_option_autoload' ) {
				data = { message: 'Updated.', byteDelta: -2048 };
			}
			return { ok: true, json: async () => ( { success: true, data } ) };
		} );
		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );
		fireEvent.click(
			await screen.findByRole( 'button', {
				name: 'Disable autoload for large_option',
			} )
		);
		await waitFor( () => expect( screen.getByText( '41' ) ).toBeInTheDocument() );
		expect(
			global.fetch.mock.calls.filter(
				( call ) => call[ 1 ].body.get( 'action' ) === 'perform_refresh_autoload_options_audit'
			)
		).toHaveLength( 1 );
		expect( await screen.findAllByRole( 'table' ) ).toHaveLength( 1 );
	} );

	it( 'marks totals uncertain without auditing again after a failed write', async () => {
		window.performwpSettings.autoloadManagerNonce = 'manager-nonce';
		global.fetch = jest.fn().mockImplementation( async ( url, options ) => {
			const action = options.body.get( 'action' );
			if ( action === 'perform_change_option_autoload' ) {
				return {
					ok: false,
					json: async () => ( {
						success: false,
						data: { message: 'Stale token.' },
					} ),
				};
			}
			return {
				ok: true,
				json: async () => ( {
					success: true,
					data: {
						supported: true,
						items: [
							{
								name: 'large_option',
								sizeBytes: 2048,
								enabled: true,
								token: 'token',
							},
						],
						total: 1,
						pageSize: 25,
					},
				} ),
			};
		} );
		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );
		fireEvent.click(
			await screen.findByRole( 'button', {
				name: 'Disable autoload for large_option',
			} )
		);
		await screen.findByText( /Totals may be out of date/ );
		await waitFor( () => expect( global.fetch ).toHaveBeenCalledTimes( 3 ) );
		expect(
			global.fetch.mock.calls.some(
				( call ) => call[ 1 ].body.get( 'action' ) === 'perform_refresh_autoload_options_audit'
			)
		).toBe( false );
	} );

	it( 'clears a superseded running audit message when a mutation fails', async () => {
		window.performwpSettings.autoloadManagerNonce = 'manager-nonce';
		let finishAudit;
		global.fetch = jest.fn().mockImplementation( ( url, options ) => {
			const action = options.body.get( 'action' );
			if ( action === 'perform_refresh_autoload_options_audit' ) {
				return new Promise( ( resolve ) => {
					finishAudit = resolve;
				} );
			}
			if ( action === 'perform_change_option_autoload' ) {
				return Promise.resolve( {
					ok: false,
					json: async () => ( {
						success: false,
						data: { message: 'Stale token.' },
					} ),
				} );
			}
			return Promise.resolve( {
				ok: true,
				json: async () => ( {
					success: true,
					data: {
						supported: true,
						items: [
							{
								name: 'large_option',
								sizeBytes: 2048,
								enabled: true,
								token: 'token',
							},
						],
						total: 1,
						pageSize: 25,
					},
				} ),
			} );
		} );
		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );
		const disable = await screen.findByRole( 'button', {
			name: 'Disable autoload for large_option',
		} );
		fireEvent.click( screen.getByRole( 'button', { name: 'Refresh audit' } ) );
		expect( screen.getByText( 'Running the database audit…' ) ).toBeVisible();
		fireEvent.click( disable );
		await screen.findByText( /Stale token/ );
		expect( screen.queryByText( 'Running the database audit…' ) ).not.toBeInTheDocument();
		expect( screen.getByRole( 'button', { name: 'Refresh audit' } ) ).toBeEnabled();
		await act( async () =>
			finishAudit( {
				ok: true,
				json: async () => ( {
					success: true,
					data: { audit: { ...readyAudit, totals: { count: 50 } } },
				} ),
			} )
		);
		expect( screen.getByText( '42' ) ).toBeVisible();
		expect( screen.getByText( /Totals may be out of date/ ) ).toBeVisible();
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
		expect( screen.queryByRole( 'button', { name: /delete/i } ) ).not.toBeInTheDocument();
	} );

	it( 'announces progress and replaces the snapshot after refresh', async () => {
		global.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				success: true,
				data: {
					message: 'Database audit refreshed.',
					audit: {
						...readyAudit,
						totals: { count: 50, sizeBytes: 700000 },
					},
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
			json: async () => ( {
				success: false,
				data: { message: 'Synthetic audit failure.' },
			} ),
		} );

		render( <DatabaseAuditPanel initialAudit={ readyAudit } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Refresh audit' } ) );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Synthetic audit failure.' );
		expect( screen.getByRole( 'button', { name: 'Refresh audit' } ) ).toBeEnabled();
	} );
} );
