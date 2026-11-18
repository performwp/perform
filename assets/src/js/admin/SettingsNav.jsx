import { useMemo, useState } from '@wordpress/element';
import AssetsDeliveryWorkspace from './AssetsDeliveryWorkspace';
import CacheWorkspace from './CacheWorkspace';
import CleanupWorkspace from './CleanupWorkspace';
import DashboardPanel from './DashboardPanel';
import InsightsWorkspace from './InsightsWorkspace';
import PreferencesWorkspace from './PreferencesWorkspace';
import { getWorkspaceForTab, INSIGHT_REPORTS } from './navigation';

const SETTINGS = window.performwpSettings || {};

const SettingsNav = ( {
	fields: propFields,
	tabs: propTabs,
	dashboard,
	diagnostics,
	databaseAudit,
	siteInventory,
	systemHealth,
	adminPerformance,
	cronPressure,
	adminAssetAudit,
	pluginImpact,
	actionScheduler,
	activeTab: propActiveTab,
	insightsNavigation,
	onTabChange: propOnTabChange,
	fieldValues: propFieldValues,
	onFieldChange: propOnFieldChange,
} ) => {
	const fields = useMemo( () => propFields || SETTINGS.fields || {}, [ propFields ] );
	const tabs = useMemo( () => propTabs || SETTINGS.tabs || {}, [ propTabs ] );
	const commerceAvailable = Object.prototype.hasOwnProperty.call( tabs, 'woocommerce' );
	const [ internalActiveTab, setInternalActiveTab ] = useState( 'dashboard' );
	const activeTab = propActiveTab ?? internalActiveTab;
	const onTabChange = propOnTabChange ?? setInternalActiveTab;
	const activeWorkspace = getWorkspaceForTab( activeTab );
	const [ internalFieldValues, setInternalFieldValues ] = useState( {} );
	const fieldValues = propFieldValues ?? internalFieldValues;
	const onFieldChange =
		propOnFieldChange ??
		( ( id, value ) =>
			setInternalFieldValues( ( previous ) => ( {
				...previous,
				[ id ]: value,
			} ) ) );
	let activeReport = null;
	if ( 'inventory' !== activeTab && INSIGHT_REPORTS.includes( activeTab ) ) {
		activeReport = activeTab;
	}

	let content;
	if ( 'dashboard' === activeWorkspace ) {
		content = (
			<DashboardPanel
				dashboard={ dashboard }
				diagnostics={ diagnostics }
				databaseAudit={ databaseAudit }
				siteInventory={ siteInventory }
				onNavigate={ onTabChange }
			/>
		);
	} else if ( 'cache' === activeWorkspace ) {
		content = (
			<CacheWorkspace
				sections={ fields.cache || [] }
				dashboard={ dashboard }
				fieldValues={ fieldValues }
				onFieldChange={ onFieldChange }
				onNavigate={ onTabChange }
				showActivity={ 'cache-stats' === activeTab }
			/>
		);
	} else if ( 'assets' === activeWorkspace ) {
		content = (
			<AssetsDeliveryWorkspace
				assetSections={ fields.assets || [] }
				cdnSections={ fields.cdn || [] }
				cacheSections={ fields.cache || [] }
				dashboard={ dashboard }
				fieldValues={ fieldValues }
				onFieldChange={ onFieldChange }
			/>
		);
	} else if ( 'bloat' === activeWorkspace ) {
		content = (
			<CleanupWorkspace
				sections={ fields.bloat || [] }
				databaseAudit={ databaseAudit }
				fieldValues={ fieldValues }
				onFieldChange={ onFieldChange }
				onNavigate={ onTabChange }
			/>
		);
	} else if ( 'inventory' === activeWorkspace ) {
		content = (
			<InsightsWorkspace
				key={ insightsNavigation }
				activeReport={ activeReport }
				showRuntime={ 'diagnostics' === activeTab }
				diagnostics={ diagnostics }
				databaseAudit={ databaseAudit }
				siteInventory={ siteInventory }
				systemHealth={ systemHealth }
				adminPerformance={ adminPerformance }
				cronPressure={ cronPressure }
				adminAssetAudit={ adminAssetAudit }
				pluginImpact={ pluginImpact }
				actionScheduler={ actionScheduler }
				onNavigate={ onTabChange }
			/>
		);
	} else {
		content = (
			<PreferencesWorkspace
				generalSections={ fields.general || [] }
				advancedSections={ fields.advanced || [] }
				commerceSections={ fields.woocommerce || [] }
				commerceAvailable={ commerceAvailable }
				fieldValues={ fieldValues }
				onFieldChange={ onFieldChange }
			/>
		);
	}

	return (
		<main className="perform-settings-content" id="perform-settings-content">
			{ content }
		</main>
	);
};

export default SettingsNav;
