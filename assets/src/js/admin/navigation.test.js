import { getWorkspaceForTab, isSettingsWorkspace, WORKSPACES } from './navigation';

describe( 'Perform workspace navigation', () => {
	it( 'exposes the six approved top-level workspaces', () => {
		expect( WORKSPACES.map( ( workspace ) => workspace.name ) ).toEqual( [
			'dashboard',
			'cache',
			'assets',
			'bloat',
			'inventory',
			'general',
		] );
	} );

	it.each( [
		[ 'cache-stats', 'cache' ],
		[ 'cdn', 'assets' ],
		[ 'diagnostics', 'inventory' ],
		[ 'database', 'inventory' ],
		[ 'admin-monitor', 'inventory' ],
		[ 'admin-assets', 'inventory' ],
		[ 'plugin-impact', 'inventory' ],
		[ 'scheduled-tasks', 'inventory' ],
		[ 'action-scheduler', 'inventory' ],
		[ 'advanced', 'general' ],
		[ 'woocommerce', 'general' ],
	] )( 'maps the legacy %s route to %s', ( legacyRoute, workspace ) => {
		expect( getWorkspaceForTab( legacyRoute ) ).toBe( workspace );
	} );

	it( 'shows the save bar only for workspaces containing editable settings', () => {
		expect( isSettingsWorkspace( 'cache' ) ).toBe( true );
		expect( isSettingsWorkspace( 'cdn' ) ).toBe( true );
		expect( isSettingsWorkspace( 'database' ) ).toBe( false );
		expect( isSettingsWorkspace( 'dashboard' ) ).toBe( false );
	} );
} );
