import { TabPanel } from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import CacheStatsPanel from './CacheStatsPanel';
import AdminPerformanceMonitorPanel from './AdminPerformanceMonitorPanel';
import DashboardPanel from './DashboardPanel';
import DatabaseAuditPanel from './DatabaseAuditPanel';
import SiteInventoryPanel from './SiteInventoryPanel';
import CronPressurePanel from './CronPressurePanel';
import AdminAssetAuditPanel from './AdminAssetAuditPanel';
import PluginImpactPanel from './PluginImpactPanel';
import ActionSchedulerPanel from './ActionSchedulerPanel';
import SettingsFieldRow from './settings/SettingsFieldRow';

const SETTINGS = window.performwpSettings || {};
const DIAGNOSTIC_TAB_KEYS = [
	'inventory',
	'database',
	'admin-monitor',
	'admin-assets',
	'plugin-impact',
	'scheduled-tasks',
	'action-scheduler',
	'cache-stats',
];

const SettingsNav = ( {
	tabs: propTabs,
	fields: propFields,
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
	onTabChange: propOnTabChange,
	fieldValues: propFieldValues,
	onFieldChange: propOnFieldChange,
} ) => {
	const tabs = useMemo( () => propTabs || SETTINGS.tabs || {}, [ propTabs ] );
	const fields = useMemo( () => propFields || SETTINGS.fields || {}, [ propFields ] );
	const diagnosticTabKeys = useMemo(
		() => DIAGNOSTIC_TAB_KEYS.filter( ( slug ) => Object.prototype.hasOwnProperty.call( tabs, slug ) ),
		[ tabs ]
	);
	const tabKeys = useMemo( () => {
		const keys = [
			'dashboard',
			...Object.keys( tabs ).filter( ( slug ) => ! DIAGNOSTIC_TAB_KEYS.includes( slug ) ),
		];
		if ( diagnosticTabKeys.length > 0 ) {
			keys.push( 'diagnostics' );
		}
		return keys;
	}, [ tabs, diagnosticTabKeys ] );
	const [ internalActiveTab, setInternalActiveTab ] = useState( tabKeys[ 0 ] || '' );
	const activeTab = propActiveTab ?? internalActiveTab;
	const activePrimaryTab = diagnosticTabKeys.includes( activeTab ) ? 'diagnostics' : activeTab;
	const onTabChange = propOnTabChange ?? setInternalActiveTab;
	const [ internalFieldValues, setInternalFieldValues ] = useState( {} );
	const fieldValues = propFieldValues ?? internalFieldValues;
	const onFieldChange =
		propOnFieldChange ??
		( ( id, value ) =>
			setInternalFieldValues( ( previous ) => ( {
				...previous,
				[ id ]: value,
			} ) ) );
	const tabPanelRef = useRef( null );
	const handleDiagnosticKeyDown = ( event, index ) => {
		let nextIndex = index;
		if ( 'ArrowRight' === event.key ) {
			nextIndex = ( index + 1 ) % diagnosticTabKeys.length;
		} else if ( 'ArrowLeft' === event.key ) {
			nextIndex = ( index - 1 + diagnosticTabKeys.length ) % diagnosticTabKeys.length;
		} else if ( 'Home' === event.key ) {
			nextIndex = 0;
		} else if ( 'End' === event.key ) {
			nextIndex = diagnosticTabKeys.length - 1;
		} else {
			return;
		}

		event.preventDefault();
		onTabChange( diagnosticTabKeys[ nextIndex ] );
		event.currentTarget.parentElement?.querySelectorAll( '[role="tab"]' )[ nextIndex ]?.focus();
	};

	useEffect( () => {
		const selectedTab = tabPanelRef.current?.querySelector( '[role="tab"][aria-selected="true"]' );
		selectedTab?.scrollIntoView?.( { block: 'nearest', inline: 'nearest' } );
	}, [ activeTab ] );

	const tabPanelTabs = useMemo(
		() =>
			tabKeys.map( ( slug ) => {
				let title = tabs[ slug ];
				if ( 'dashboard' === slug ) {
					title = 'Dashboard';
				} else if ( 'diagnostics' === slug ) {
					title = 'Diagnostics';
				}
				return { name: slug, title };
			} ),
		[ tabKeys, tabs ]
	);

	if ( ! tabKeys.length ) {
		return null;
	}

	return (
		<div ref={ tabPanelRef }>
			<TabPanel
				key={ activePrimaryTab }
				className="perform-settings-tab-panel"
				tabs={ tabPanelTabs }
				initialTabName={ activePrimaryTab }
				onSelect={ ( tabName ) => {
					let nextTab = tabName;
					if ( 'diagnostics' === tabName ) {
						nextTab = diagnosticTabKeys.includes( activeTab ) ? activeTab : diagnosticTabKeys[ 0 ];
					}
					if ( nextTab && nextTab !== activeTab ) {
						onTabChange( nextTab );
					}
				} }
			>
				{ ( selectedTab ) => {
					let selectedTabName = selectedTab?.name || activeTab;
					if ( 'diagnostics' === selectedTab?.name ) {
						selectedTabName = diagnosticTabKeys.includes( activeTab ) ? activeTab : diagnosticTabKeys[ 0 ];
					}
					const sections = fields[ selectedTabName ] || [];
					let content = (
						<div className="perform-settings-sections">
							{ sections.map( ( section, index ) => (
								<section
									key={ `${ selectedTabName }-${ index }` }
									className="perform-settings-section"
									aria-labelledby={ `${ selectedTabName }-section-${ index }` }
								>
									<header className="perform-settings-section__header">
										<h2 id={ `${ selectedTabName }-section-${ index }` }>{ section.title }</h2>
										{ section.description && <p>{ section.description }</p> }
									</header>
									{ section.fields?.length > 0 && (
										<div className="perform-settings-section__fields">
											{ section.fields.map( ( field ) => (
												<SettingsFieldRow
													key={ field.id }
													field={ field }
													value={ fieldValues[ field.id ] }
													onChange={ onFieldChange }
												/>
											) ) }
										</div>
									) }
								</section>
							) ) }
						</div>
					);

					if ( 'dashboard' === selectedTabName ) {
						content = (
							<DashboardPanel
								dashboard={ dashboard }
								diagnostics={ diagnostics }
								databaseAudit={ databaseAudit }
								siteInventory={ siteInventory }
								onNavigate={ onTabChange }
							/>
						);
					} else if ( 'database' === selectedTabName ) {
						content = <DatabaseAuditPanel initialAudit={ databaseAudit } />;
					} else if ( 'inventory' === selectedTabName ) {
						content = (
							<SiteInventoryPanel initialInventory={ siteInventory } systemHealth={ systemHealth } />
						);
					} else if ( 'admin-monitor' === selectedTabName ) {
						content = (
							<AdminPerformanceMonitorPanel
								initialSnapshot={ adminPerformance }
								onNavigate={ onTabChange }
							/>
						);
					} else if ( 'scheduled-tasks' === selectedTabName ) {
						content = <CronPressurePanel initialAudit={ cronPressure } />;
					} else if ( 'admin-assets' === selectedTabName ) {
						content = (
							<AdminAssetAuditPanel initialSnapshot={ adminAssetAudit } onNavigate={ onTabChange } />
						);
					} else if ( 'plugin-impact' === selectedTabName ) {
						content = <PluginImpactPanel report={ pluginImpact } onNavigate={ onTabChange } />;
					} else if ( 'action-scheduler' === selectedTabName ) {
						content = <ActionSchedulerPanel initialAudit={ actionScheduler } />;
					} else if ( 'cache-stats' === selectedTabName ) {
						content = <CacheStatsPanel />;
					}

					return (
						<>
							{ diagnosticTabKeys.includes( selectedTabName ) && (
								<div className="perform-diagnostic-tabs" role="tablist" aria-label="Diagnostic tools">
									{ diagnosticTabKeys.map( ( slug, index ) => (
										<button
											key={ slug }
											type="button"
											role="tab"
											aria-selected={ slug === selectedTabName }
											tabIndex={ slug === selectedTabName ? 0 : -1 }
											className="perform-diagnostic-tabs__item"
											onClick={ () => onTabChange( slug ) }
											onKeyDown={ ( event ) => handleDiagnosticKeyDown( event, index ) }
										>
											{ tabs[ slug ] }
										</button>
									) ) }
								</div>
							) }
							<div className="perform-settings-content">{ content }</div>
						</>
					);
				} }
			</TabPanel>
		</div>
	);
};

export default SettingsNav;
