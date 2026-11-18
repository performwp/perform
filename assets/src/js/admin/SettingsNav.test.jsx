import '@testing-library/jest-dom';
import { fireEvent, render, screen, within } from '@testing-library/react';
import SettingsNav from './SettingsNav';

const renderSettingsNav = () =>
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
			diagnostics={ {
				summary: { ready: 1 },
				items: [
					{
						id: 'runtime-check',
						label: 'Runtime check',
						status: 'ready',
						message: 'Checked locally.',
					},
				],
			} }
		/>
	);

describe( 'SettingsNav', () => {
	it( 'returns from the local site profile when Insights navigation is selected again', () => {
		const view = render(
			<SettingsNav
				tabs={ { inventory: 'Site Inventory' } }
				fields={ {} }
				activeTab="inventory"
				insightsNavigation={ 0 }
			/>
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Generate site profile' } ) );
		expect( screen.getByRole( 'button', { name: 'Back to insights' } ) ).toBeInTheDocument();
		view.rerender(
			<SettingsNav
				tabs={ { inventory: 'Site Inventory' } }
				fields={ {} }
				activeTab="inventory"
				insightsNavigation={ 1 }
			/>
		);
		expect( screen.getByRole( 'heading', { name: 'Technical reports' } ) ).toBeInTheDocument();
	} );
	it( 'opens all diagnostic reports through the Insights workspace and selects a report', () => {
		renderSettingsNav();

		fireEvent.click( screen.getByRole( 'button', { name: 'View insights' } ) );
		const reports = screen.getByRole( 'heading', { name: 'Technical reports' } ).closest( 'section' );
		[
			'Site profile',
			'Database options',
			'Admin experience',
			'Admin assets',
			'Plugin signals',
			'Scheduled tasks',
			'Action queues',
		].forEach( ( title ) => {
			expect(
				within( reports ).getByRole( 'button', {
					name: new RegExp( `^${ title }` ),
				} )
			).toBeInTheDocument();
		} );

		fireEvent.click(
			within( reports ).getByRole( 'button', {
				name: /^Database options/,
			} )
		);
		expect( screen.getByRole( 'heading', { name: 'Autoloaded options audit' } ) ).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'button', { name: 'Back to insights' } ) );
		expect( screen.getByRole( 'heading', { name: 'Technical reports' } ) ).toBeInTheDocument();
	} );

	it( 'opens runtime diagnostics from a dashboard recommendation', () => {
		renderSettingsNav();

		fireEvent.click( screen.getByRole( 'button', { name: 'View runtime checks' } ) );
		const runtime = screen.getByText( 'Runtime cache and delivery checks' ).closest( 'details' );
		expect( runtime ).toHaveAttribute( 'open' );
		expect( within( runtime ).getByText( 'Runtime check' ) ).toBeInTheDocument();
	} );

	it( 'opens and leaves cache activity from a dashboard recommendation', () => {
		renderSettingsNav();

		fireEvent.click( screen.getByRole( 'button', { name: 'Review cache settings' } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'View cache activity' } ) );
		expect( screen.getByRole( 'heading', { name: 'Cache activity' } ) ).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'button', { name: 'Back to cache' } ) );
		expect( screen.getByRole( 'heading', { name: 'Page cache' } ) ).toBeInTheDocument();
	} );
} );
