/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';

const PluginImpactPanel = require( './PluginImpactPanel' ).default;

describe( 'PluginImpactPanel', () => {
	it( 'requires inventory before presenting plugin claims', () => {
		const navigate = jest.fn();
		render(
			<PluginImpactPanel
				report={ { status: 'inventory-needed', items: [], adminAssetAudit: {} } }
				onNavigate={ navigate }
			/>
		);
		expect( screen.getByText( 'Site inventory is required' ) ).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'button', { name: 'Generate site inventory' } ) );
		expect( navigate ).toHaveBeenCalledWith( 'inventory' );
	} );

	it( 'separates measured asset presence from unavailable attribution', () => {
		render(
			<PluginImpactPanel
				report={ {
					status: 'ready',
					activeCount: 2,
					adminAssetAudit: { enabled: true, sampledScreens: 4 },
					items: [
						{
							slug: 'sample',
							name: 'Sample',
							version: '1.0',
							hasEvidence: true,
							adminAssets: { screenCount: 3, scriptCount: 2, styleCount: 1 },
						},
						{ slug: 'quiet', name: 'Quiet', hasEvidence: false, adminAssets: {} },
					],
				} }
			/>
		);
		expect( screen.getByText( 'Evidence, not a plugin ranking' ) ).toBeInTheDocument();
		expect( screen.getByText( /Zero means not observed in this sample/ ) ).toBeInTheDocument();
		expect( screen.getByText( 'Database query contribution by plugin' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Callback execution time by plugin' ) ).toBeInTheDocument();
		expect( screen.getAllByText( 'Measured locally' ) ).toHaveLength( 1 );
	} );

	it( 'handles an empty active-plugin inventory', () => {
		render(
			<PluginImpactPanel
				report={ {
					status: 'ready',
					activeCount: 0,
					items: [],
					adminAssetAudit: { enabled: false, sampledScreens: 0 },
				} }
			/>
		);
		expect( screen.getByText( 'No active plugins are available in the bounded inventory' ) ).toBeInTheDocument();
	} );
} );
