/* eslint-env jest */

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

window.performwpSettings = { adminAssetAuditNonce: 'asset-nonce' };
const AdminAssetAuditPanel = require( './AdminAssetAuditPanel' ).default;

describe( 'AdminAssetAuditPanel', () => {
	beforeEach( () => {
		global.fetch = jest.fn();
		window.confirm = jest.fn( () => true );
	} );

	it( 'explains disabled mode without presenting collected data', () => {
		render( <AdminAssetAuditPanel initialSnapshot={ { enabled: false, screens: [], repeated: [] } } /> );
		expect( screen.getByText( 'Audit mode is off' ) ).toBeInTheDocument();
		expect( screen.getByText( /registers no asset-capture hooks/ ) ).toBeInTheDocument();
	} );

	it( 'shows repeated assets as evidence rather than a disable recommendation', () => {
		render(
			<AdminAssetAuditPanel
				initialSnapshot={ {
					enabled: true,
					screens: [ { id: 'dashboard', assets: [] } ],
					repeated: [
						{
							type: 'script',
							handle: 'sample-admin',
							source: 'sample-plugin',
							confidence: 'high',
							screenCount: 4,
						},
					],
				} }
			/>
		);
		expect( screen.getByText( /not proof that an asset is unnecessary/ ) ).toBeInTheDocument();
		expect( screen.getByText( 'sample-admin' ) ).toBeInTheDocument();
		expect( screen.getByText( 'sample-plugin' ) ).toBeInTheDocument();
	} );

	it( 'clears the current site snapshot after confirmation', async () => {
		global.fetch.mockResolvedValue( {
			ok: true,
			json: async () => ( {
				success: true,
				data: { message: 'Admin asset audit cleared.', snapshot: { enabled: true, screens: [], repeated: [] } },
			} ),
		} );
		render(
			<AdminAssetAuditPanel
				initialSnapshot={ { enabled: true, screens: [ { id: 'dashboard', assets: [] } ], repeated: [] } }
			/>
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Clear audit' } ) );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Admin asset audit cleared.' ) );
		expect( global.fetch.mock.calls[ 0 ][ 1 ].body.toString() ).toContain( 'perform_clear_admin_asset_audit' );
	} );
} );
