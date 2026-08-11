import { TabPanel } from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import CacheStatsPanel from './CacheStatsPanel';
import AdminPerformanceMonitorPanel from './AdminPerformanceMonitorPanel';
import DashboardPanel from './DashboardPanel';
import DatabaseAuditPanel from './DatabaseAuditPanel';
import SiteInventoryPanel from './SiteInventoryPanel';
import SettingsFieldRow from './settings/SettingsFieldRow';

const SETTINGS = window.performwpSettings || {};

const SettingsNav = ( {
	tabs: propTabs,
	fields: propFields,
	dashboard,
	diagnostics,
	databaseAudit,
	siteInventory,
	systemHealth,
	adminPerformance,
	activeTab: propActiveTab,
	onTabChange: propOnTabChange,
	fieldValues: propFieldValues,
	onFieldChange: propOnFieldChange,
} ) => {
	const tabs = useMemo( () => propTabs || SETTINGS.tabs || {}, [ propTabs ] );
	const fields = useMemo( () => propFields || SETTINGS.fields || {}, [ propFields ] );
	const tabKeys = useMemo( () => [ 'dashboard', ...Object.keys( tabs ) ], [ tabs ] );
	const [ internalActiveTab, setInternalActiveTab ] = useState( tabKeys[ 0 ] || '' );
	const activeTab = propActiveTab ?? internalActiveTab;
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

	useEffect( () => {
		const selectedTab = tabPanelRef.current?.querySelector( '[role="tab"][aria-selected="true"]' );
		selectedTab?.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
	}, [ activeTab ] );

	const tabPanelTabs = useMemo(
		() =>
			tabKeys.map( ( slug ) => ( {
				name: slug,
				title: 'dashboard' === slug ? 'Dashboard' : tabs[ slug ],
			} ) ),
		[ tabKeys, tabs ]
	);

	if ( ! tabKeys.length ) {
		return null;
	}

	return (
		<div ref={ tabPanelRef }>
			<TabPanel
				key={ activeTab }
				className="perform-settings-tab-panel"
				tabs={ tabPanelTabs }
				initialTabName={ activeTab }
				onSelect={ ( tabName ) => {
					if ( tabName !== activeTab ) {
						onTabChange( tabName );
					}
				} }
			>
				{ ( selectedTab ) => {
					const selectedTabName = selectedTab?.name || activeTab;
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
					} else if ( 'cache-stats' === selectedTabName ) {
						content = <CacheStatsPanel />;
					}

					return <div className="perform-settings-content">{ content }</div>;
				} }
			</TabPanel>
		</div>
	);
};

export default SettingsNav;
