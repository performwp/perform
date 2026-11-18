import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import PreferencesWorkspace from './PreferencesWorkspace';

const commerceSections = [
	{
		fields: [
			{
				id: 'enable_woocommerce_manager',
				type: 'toggle',
				name: 'Enable WooCommerce Manager',
				desc: 'Manage store performance settings.',
			},
		],
	},
];

const renderWorkspace = ( commerceAvailable ) =>
	render(
		<PreferencesWorkspace
			commerceSections={ commerceSections }
			commerceAvailable={ commerceAvailable }
			fieldValues={ {} }
			onFieldChange={ jest.fn() }
		/>
	);

describe( 'PreferencesWorkspace', () => {
	it( 'hides WooCommerce controls when WooCommerce is inactive', () => {
		renderWorkspace( false );

		expect( screen.queryByText( 'Commerce' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Enable WooCommerce Manager' ) ).not.toBeInTheDocument();
	} );

	it( 'shows WooCommerce controls when WooCommerce is active', () => {
		renderWorkspace( true );

		expect( screen.getByText( 'Commerce' ) ).toBeVisible();
	} );
} );
