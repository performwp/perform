/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';
import DashboardPanel, { buildHealthCards } from './DashboardPanel';

const dashboard = {
	links: {
		docs: 'https://performwp.com/docs/',
		support: 'https://wordpress.org/support/plugin/perform/',
	},
	cache: { hasStats: false },
	assetsManager: {},
	server: {
		opcache: { state: 'not-available', enabled: null },
		compression: { state: 'needs-separate-test', phpEnabled: false },
	},
	changelog: [ 'Measured product update.' ],
};

const diagnostics = {
	summary: { ready: 5, warning: 1, 'needs-attention': 0 },
	items: [
		{
			id: 'dynamic-cache-exclusions',
			status: 'warning',
			label: 'Dynamic request exclusions',
		},
	],
};

const readyInventory = {
	status: 'ready',
	plugins: { activeCount: 3 },
	postTypes: { totalCount: 8 },
	perform: { items: [ { id: 'enable_page_cache' }, { id: 'enable_cdn' } ] },
	patterns: { store: false },
};

describe( 'DashboardPanel', () => {
	it( 'fails closed when local evidence is unavailable', () => {
		const cards = buildHealthCards( {
			dashboard,
			diagnostics: {},
			databaseAudit: { status: 'not-run' },
			siteInventory: { status: 'not-run' },
		} );

		expect( cards.find( ( card ) => 'inventory' === card.id ).status ).toBe( 'not-measured' );
		expect( cards.find( ( card ) => 'database' === card.id ).status ).toBe( 'not-measured' );
		expect( cards.find( ( card ) => 'runtime' === card.id ).status ).toBe( 'not-measured' );
		expect( cards.find( ( card ) => 'server' === card.id ).status ).toBe( 'not-measured' );
	} );

	it( 'uses measured local evidence for status and avoids an invented score', () => {
		render(
			<DashboardPanel
				dashboard={ dashboard }
				diagnostics={ diagnostics }
				databaseAudit={ {
					status: 'ready',
					totals: { sizeBytes: 900000 },
					thresholdBytes: 800000,
					objectCache: { persistent: false },
				} }
				siteInventory={ readyInventory }
			/>
		);

		expect( screen.getByText( /3 active plugins, 8 content types, and 2 Perform modules/ ) ).toBeInTheDocument();
		expect( screen.getByText( 'Core Web Vitals need a separate test' ) ).toBeInTheDocument();
		expect( screen.getByText( /never turns these checks into an unverified speed score/i ) ).toBeInTheDocument();
		expect( screen.getByText( /Response compression: needs a separate response test/ ) ).toBeInTheDocument();
	} );

	it( 'shows store-safety guidance only when a store pattern is measured', () => {
		const absent = buildHealthCards( {
			dashboard,
			diagnostics,
			databaseAudit: { status: 'not-run' },
			siteInventory: readyInventory,
		} );
		const present = buildHealthCards( {
			dashboard,
			diagnostics,
			databaseAudit: { status: 'not-run' },
			siteInventory: { ...readyInventory, patterns: { store: true } },
		} );

		expect( absent.find( ( card ) => 'store' === card.id ) ).toBeUndefined();
		expect( present.find( ( card ) => 'store' === card.id ).status ).toBe( 'review' );
	} );

	it( 'routes internal recommendations without making an automatic request', () => {
		const onNavigate = jest.fn();
		render(
			<DashboardPanel
				dashboard={ dashboard }
				diagnostics={ diagnostics }
				databaseAudit={ { status: 'not-run' } }
				siteInventory={ { status: 'not-run' } }
				onNavigate={ onNavigate }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Generate inventory' } ) );
		expect( onNavigate ).toHaveBeenCalledWith( 'inventory' );
	} );
} );
