import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	CircleStackIcon,
	CloudIcon,
	ComputerDesktopIcon,
	GlobeAltIcon,
	ListBulletIcon,
	ServerStackIcon,
	ShieldCheckIcon,
} from '@heroicons/react/24/outline';
import { formatBytes } from './DashboardPanel';
import { MetricStrip, SettingsGroup, WorkspaceHeader } from './WorkspaceLayout';

const GROUP_ICONS = [ GlobeAltIcon, CloudIcon, ShieldCheckIcon, ComputerDesktopIcon ];

const CleanupWorkspace = ( { sections = [], databaseAudit = {}, fieldValues, onFieldChange, onNavigate } ) => {
	const auditReady = 'ready' === databaseAudit.status;
	const totalSize = auditReady ? formatBytes( databaseAudit.totals?.sizeBytes || 0 ) : '—';
	const optionCount = auditReady ? databaseAudit.totals?.count || 0 : '—';
	let objectCache = '—';
	let auditStatus = __( 'Database not checked', 'perform' );
	if ( auditReady ) {
		auditStatus = databaseAudit.isStale
			? __( 'Refresh recommended', 'perform' )
			: __( 'Database checked', 'perform' );
		objectCache = databaseAudit.objectCache?.persistent
			? __( 'Detected', 'perform' )
			: __( 'Not detected', 'perform' );
	}

	return (
		<div className="perform-workspace">
			<WorkspaceHeader
				title={ __( 'Cleanup', 'perform' ) }
				description={ __(
					'Choose which WordPress features to keep and review the options loaded on each request.',
					'perform'
				) }
				status={ auditStatus }
				statusType={ auditReady && ! databaseAudit.isStale ? 'success' : 'neutral' }
				actions={
					<Button variant="primary" onClick={ () => onNavigate( 'database' ) }>
						{ __( 'Open database audit', 'perform' ) }
					</Button>
				}
			/>
			<MetricStrip
				label={ __( 'Database health', 'perform' ) }
				metrics={ [
					{ icon: CircleStackIcon, label: __( 'Autoloaded size', 'perform' ), value: totalSize },
					{ icon: ListBulletIcon, label: __( 'Autoloaded options', 'perform' ), value: optionCount },
					{ icon: ServerStackIcon, label: __( 'Persistent object cache', 'perform' ), value: objectCache },
				] }
				note={ __(
					'The audit shows which options WordPress loads on each request. Running it does not change or delete them.',
					'perform'
				) }
			/>
			<section className="perform-section-heading">
				<h2>{ __( 'Reduce WordPress extras', 'perform' ) }</h2>
				<p>{ __( 'Open a category to review its settings before changing them.', 'perform' ) }</p>
			</section>
			<div className="perform-workspace-groups">
				{ sections.map( ( section, index ) => (
					<SettingsGroup
						key={ section.title }
						title={ section.title }
						description={ section.description }
						fields={ section.fields || [] }
						fieldValues={ fieldValues }
						onFieldChange={ onFieldChange }
						icon={ GROUP_ICONS[ index ] || ShieldCheckIcon }
						defaultOpen={ 0 === index }
					/>
				) ) }
			</div>
			<div className="perform-safety-callout">
				<ShieldCheckIcon aria-hidden="true" />
				<div>
					<strong>{ __( 'Review first, then optimize', 'perform' ) }</strong>
					<p>
						{ __(
							'Review an option and its owner before changing its autoload setting. Check the affected plugin afterward.',
							'perform'
						) }
					</p>
				</div>
			</div>
		</div>
	);
};

export default CleanupWorkspace;
