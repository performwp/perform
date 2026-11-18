import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	CircleStackIcon,
	CloudIcon,
	ComputerDesktopIcon,
	GlobeAltIcon,
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
	if ( auditReady ) {
		objectCache = databaseAudit.objectCache?.persistent
			? __( 'Detected', 'perform' )
			: __( 'Not detected', 'perform' );
	}

	return (
		<div className="perform-workspace">
			<WorkspaceHeader
				title={ __( 'Cleanup', 'perform' ) }
				description={ __(
					'Remove unnecessary WordPress extras and review database items that may be adding avoidable work.',
					'perform'
				) }
				status={
					auditReady
						? __( 'Database evidence is ready', 'perform' )
						: __( 'Database check available', 'perform' )
				}
				statusType={ auditReady ? 'success' : 'neutral' }
				actions={
					<Button variant="primary" onClick={ () => onNavigate( 'database' ) }>
						{ auditReady ? __( 'Review database', 'perform' ) : __( 'Run database check', 'perform' ) }
					</Button>
				}
			/>
			<MetricStrip
				label={ __( 'Database health', 'perform' ) }
				metrics={ [
					{ icon: CircleStackIcon, label: __( 'Autoloaded size', 'perform' ), value: totalSize },
					{ label: __( 'Autoloaded options', 'perform' ), value: optionCount },
					{ label: __( 'Persistent object cache', 'perform' ), value: objectCache },
				] }
				note={ __(
					'This release audits database pressure but does not remove data automatically.',
					'perform'
				) }
			/>
			<section className="perform-section-heading">
				<h2>{ __( 'Reduce WordPress extras', 'perform' ) }</h2>
				<p>
					{ __(
						'Open a category to review each optimization. Existing controls and saved values are unchanged.',
						'perform'
					) }
				</p>
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
							'Perform keeps potentially disruptive controls visible and explained. Database cleanup will require preview and recovery support before it is offered.',
							'perform'
						) }
					</p>
				</div>
			</div>
		</div>
	);
};

export default CleanupWorkspace;
