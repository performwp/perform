import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';
import AssetsDeliveryWorkspace from './AssetsDeliveryWorkspace';
import CacheWorkspace from './CacheWorkspace';
import CleanupWorkspace from './CleanupWorkspace';

describe( 'workspace copy', () => {
	it( 'describes the database button as navigation, not an audit that has already run', () => {
		const onNavigate = jest.fn();
		render( <CleanupWorkspace fieldValues={ {} } onNavigate={ onNavigate } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Open database audit' } ) );
		expect( onNavigate ).toHaveBeenCalledWith( 'database' );
		expect( screen.queryByText( /Database cleanup will require/ ) ).not.toBeInTheDocument();
	} );

	it( 'uses a complete singular message for one configured asset rule', () => {
		render(
			<AssetsDeliveryWorkspace dashboard={ { assetsManager: { disabledJsHandles: 1 } } } fieldValues={ {} } />
		);

		expect( screen.getByText( '1 asset rule configured' ) ).toBeVisible();
	} );

	it( 'describes cache exclusions as configurable rules', () => {
		render(
			<CacheWorkspace
				sections={ [ { fields: [ { id: 'cache_bypass_exact_paths', name: 'Paths', type: 'text' } ] } ] }
				fieldValues={ {} }
			/>
		);

		expect( screen.getByText( 'Cache exclusions' ) ).toBeVisible();
		expect( screen.getByText( /Configure bypass rules/ ) ).toBeVisible();
	} );
} );
