/* eslint-env jest */

import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import SettingsNav from './SettingsNav';

describe( 'SettingsNav', () => {
	it( 'groups advanced diagnostics behind one primary tab and supports arrow-key navigation', async () => {
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
		const inventoryTab = screen.getByRole( 'tab', { name: 'Site Inventory' } );
		expect( inventoryTab ).toHaveAttribute( 'aria-selected', 'true' );

		inventoryTab.focus();
		await act( async () => {
			fireEvent.keyDown( inventoryTab, { key: 'ArrowRight' } );
		} );
		await waitFor( () => {
			expect( screen.getByRole( 'tab', { name: 'Database' } ) ).toHaveAttribute( 'aria-selected', 'true' );
		} );
	} );
} );
