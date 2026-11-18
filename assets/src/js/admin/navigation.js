import { __ } from '@wordpress/i18n';

export const WORKSPACES = [
	{ name: 'dashboard', title: __( 'Overview', 'perform' ) },
	{ name: 'cache', title: __( 'Cache', 'perform' ) },
	{ name: 'assets', title: __( 'Assets & CDN', 'perform' ) },
	{ name: 'bloat', title: __( 'Cleanup', 'perform' ) },
	{ name: 'inventory', title: __( 'Insights', 'perform' ) },
	{ name: 'general', title: __( 'Settings', 'perform' ) },
];

export const INSIGHT_REPORTS = [
	'inventory',
	'database',
	'admin-monitor',
	'admin-assets',
	'plugin-impact',
	'scheduled-tasks',
	'action-scheduler',
];

const WORKSPACE_BY_TAB = {
	dashboard: 'dashboard',
	cache: 'cache',
	'cache-stats': 'cache',
	assets: 'assets',
	cdn: 'assets',
	bloat: 'bloat',
	diagnostics: 'inventory',
	inventory: 'inventory',
	database: 'inventory',
	'admin-monitor': 'inventory',
	'admin-assets': 'inventory',
	'plugin-impact': 'inventory',
	'scheduled-tasks': 'inventory',
	'action-scheduler': 'inventory',
	general: 'general',
	advanced: 'general',
	woocommerce: 'general',
};

export const getWorkspaceForTab = ( tab ) => WORKSPACE_BY_TAB[ tab ] || 'dashboard';

export const isSettingsWorkspace = ( tab ) =>
	[ 'cache', 'assets', 'bloat', 'general' ].includes( getWorkspaceForTab( tab ) );
