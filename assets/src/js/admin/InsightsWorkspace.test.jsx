import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';
import InsightsWorkspace from './InsightsWorkspace';

const readyDiagnostics = {
	summary: { ready: 6, warning: 0, 'needs-attention': 0 },
	items: [ { id: 'page-cache-storage', status: 'ready' } ],
};

describe( 'InsightsWorkspace cache and delivery status', () => {
	it( 'does not show a green result when diagnostics are unavailable', () => {
		const { container } = render( <InsightsWorkspace /> );
		const cacheRow = screen.getByText( 'Cache and delivery setup' ).closest( '.perform-insight-status-row' );

		expect( cacheRow.querySelector( '.perform-status-badge' ) ).toHaveTextContent( 'Not measured' );
		expect( cacheRow.querySelector( '.perform-status-badge' ) ).toHaveAttribute( 'data-status', 'neutral' );
		expect(
			screen.getByRole( 'heading', { name: 'Cache and delivery checks are not measured' } )
		).toBeInTheDocument();
		expect( container.querySelector( '.perform-insight-status-row [data-status="success"]' ) ).toBeNull();
	} );

	it( 'reports cache and delivery evidence without implying hosting health', () => {
		render(
			<InsightsWorkspace
				diagnostics={ readyDiagnostics }
				systemHealth={ { summary: { good: 0, review: 0, actionRecommended: 3, unavailable: 0 } } }
			/>
		);

		expect( screen.getByText( 'Good' ).closest( '.perform-status-badge' ) ).toHaveAttribute(
			'data-status',
			'success'
		);
		expect( screen.queryByText( 'Hosting readiness' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'PHP, memory, storage, and compression checks' ) ).not.toBeInTheDocument();
	} );

	it( 'marks cache and delivery checks for review when diagnostics report warnings', () => {
		render(
			<InsightsWorkspace
				diagnostics={ {
					summary: { ready: 5, warning: 1, 'needs-attention': 0 },
					items: [ { id: 'cdn', status: 'warning' } ],
				} }
				systemHealth={ { summary: { good: 9, review: 0, actionRecommended: 0, unavailable: 0 } } }
			/>
		);

		expect( screen.getByText( 'Review' ).closest( '.perform-status-badge' ) ).toHaveAttribute(
			'data-status',
			'warning'
		);
	} );

	it( 'labels opening a profile accurately and warns when saved reports are stale', () => {
		render(
			<InsightsWorkspace
				siteInventory={ { status: 'ready', isStale: true } }
				databaseAudit={ { status: 'ready', isStale: true } }
			/>
		);
		const rows = screen.getAllByText( 'Refresh recommended' );
		expect( rows ).toHaveLength( 2 );
		rows.forEach( ( row ) => expect( row ).toHaveAttribute( 'data-status', 'warning' ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Open site profile' } ) );
		expect( screen.getByRole( 'button', { name: 'Refresh inventory' } ) ).toBeInTheDocument();
	} );
} );
