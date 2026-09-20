/* eslint-env jest */

import '@testing-library/jest-dom';
import { act, fireEvent, render, screen } from '@testing-library/react';
import SettingsNav from './SettingsNav';

describe( 'SettingsNav', () => {
	it( 'groups advanced diagnostics behind one primary tab and a report selector', async () => {
		render(
			<SettingsNav
				tabs={ {
					general: 'General',
					inventory: 'Site Inventory',
					database: 'Database',
					'cache-stats': 'Cache Stats',
				} }
				fields={ {} }
				dashboard={ {} }
			/>
		);

		expect( screen.getByRole( 'tab', { name: 'Diagnostics' } ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'tab', { name: 'Site Inventory' } ) ).not.toBeInTheDocument();

		await act( async () => {
			fireEvent.click( screen.getByRole( 'tab', { name: 'Diagnostics' } ) );
		} );
		const reportSelector = screen.getByRole( 'combobox', { name: 'Diagnostic report' } );
		expect( reportSelector ).toHaveValue( 'inventory' );
		expect( screen.getByText( 'Private, local reports for this WordPress site' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'tablist', { name: 'Diagnostic tools' } ) ).not.toBeInTheDocument();

		await act( async () => {
			fireEvent.change( reportSelector, { target: { value: 'database' } } );
		} );
		expect( reportSelector ).toHaveValue( 'database' );
	} );
} );
