import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import ActionSchedulerPanel from './ActionSchedulerPanel';

describe( 'ActionSchedulerPanel', () => {
	beforeEach( () => {
		window.performwpSettings = { actionSchedulerNonce: 'nonce' };
		window.ajaxurl = '/wp-admin/admin-ajax.php';
		window.confirm = jest.fn( () => true );
	} );

	it( 'handles an absent Action Scheduler installation cleanly', () => {
		render( <ActionSchedulerPanel initialAudit={ { status: 'not-available' } } /> );
		expect( screen.getByText( 'Action Scheduler is not available' ) ).toBeInTheDocument();
		expect( screen.getByText( /never runs, cancels, or deletes/ ) ).toBeInTheDocument();
	} );

	it( 'shows queue counts and bounded hook evidence without cleanup actions', () => {
		render(
			<ActionSchedulerPanel
				initialAudit={ {
					status: 'ready',
					counts: { pending: 12, failed: 4 },
					oldestPending: { available: true, ageSeconds: 7200 },
					storage: { actionCount: 40, logCount: 90 },
					topHooks: [ { name: 'safe_hook', count: 12 } ],
					topGroups: [],
				} }
			/>
		);
		expect( screen.getByText( 'safe_hook' ) ).toBeInTheDocument();
		expect( screen.getByText( '2 hours' ) ).toBeInTheDocument();
		expect( screen.getByText( /Failed-action trend is not shown/ ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'button', { name: /delete|cleanup|cancel/i } ) ).not.toBeInTheDocument();
	} );

	it( 'uses singular units for a one-hour or one-day pending age', () => {
		const { rerender } = render(
			<ActionSchedulerPanel
				initialAudit={ { status: 'ready', oldestPending: { available: true, ageSeconds: 3600 } } }
			/>
		);
		expect( screen.getByText( '1 hour' ) ).toBeInTheDocument();
		rerender(
			<ActionSchedulerPanel
				key="day"
				initialAudit={ { status: 'ready', oldestPending: { available: true, ageSeconds: 86400 } } }
			/>
		);
		expect( screen.getByText( '1 day' ) ).toBeInTheDocument();
	} );

	it( 'surfaces refresh failures and restores actions', async () => {
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				ok: false,
				json: () =>
					Promise.resolve( {
						success: false,
						data: { message: 'Synthetic failure' },
					} ),
			} )
		);
		render( <ActionSchedulerPanel initialAudit={ { status: 'not-run' } } /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Run check' } ) );
		await waitFor( () => expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'Synthetic failure' ) );
		expect( screen.getByRole( 'button', { name: 'Run check' } ) ).toBeEnabled();
	} );
} );
