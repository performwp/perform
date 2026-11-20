import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	ChartBarIcon,
	CheckCircleIcon,
	ChevronRightIcon,
	CircleStackIcon,
	ClockIcon,
	CodeBracketIcon,
	CpuChipIcon,
	ExclamationTriangleIcon,
	ServerStackIcon,
	Squares2X2Icon,
} from '@heroicons/react/24/outline';
import ActionSchedulerPanel from './ActionSchedulerPanel';
import AdminAssetAuditPanel from './AdminAssetAuditPanel';
import AdminPerformanceMonitorPanel from './AdminPerformanceMonitorPanel';
import CronPressurePanel from './CronPressurePanel';
import DatabaseAuditPanel from './DatabaseAuditPanel';
import DiagnosticsPanel from './DiagnosticsPanel';
import PluginImpactPanel from './PluginImpactPanel';
import SiteInventoryPanel from './SiteInventoryPanel';
import { formatBytes } from './DashboardPanel';
import { ReportBackButton, StatusBadge, WorkspaceHeader } from './WorkspaceLayout';

const REPORTS = [
	{
		slug: 'inventory',
		title: __( 'Site profile', 'perform' ),
		description: __( 'WordPress, hosting, plugins, content types, and enabled Perform features.', 'perform' ),
		icon: Squares2X2Icon,
	},
	{
		slug: 'database',
		title: __( 'Database options', 'perform' ),
		description: __( 'Autoloaded option size, likely ownership, and object-cache evidence.', 'perform' ),
		icon: CircleStackIcon,
	},
	{
		slug: 'admin-monitor',
		title: __( 'Admin experience', 'perform' ),
		description: __( 'Timing, memory, and query counts sampled from WordPress admin requests.', 'perform' ),
		icon: ChartBarIcon,
	},
	{
		slug: 'admin-assets',
		title: __( 'Admin assets', 'perform' ),
		description: __( 'Script and style handles observed across sampled admin screens.', 'perform' ),
		icon: CodeBracketIcon,
	},
	{
		slug: 'plugin-impact',
		title: __( 'Plugin signals', 'perform' ),
		description: __( 'Admin assets observed for active plugins in sampled screens.', 'perform' ),
		icon: CpuChipIcon,
	},
	{
		slug: 'scheduled-tasks',
		title: __( 'Scheduled tasks', 'perform' ),
		description: __( 'Overdue and clustered WordPress cron work.', 'perform' ),
		icon: ClockIcon,
	},
	{
		slug: 'action-scheduler',
		title: __( 'Action queues', 'perform' ),
		description: __( 'Action Scheduler queue counts for commerce and other extensions.', 'perform' ),
		icon: ServerStackIcon,
	},
];

const InsightStatusRow = ( { title, description, status, statusType = 'success' } ) => (
	<div className="perform-insight-status-row">
		<span className="perform-icon-badge" data-status={ statusType } aria-hidden="true">
			{ 'success' === statusType ? <CheckCircleIcon /> : <ExclamationTriangleIcon /> }
		</span>
		<div>
			<strong>{ title }</strong>
			<p>{ description }</p>
		</div>
		<StatusBadge status={ statusType }>{ status }</StatusBadge>
	</div>
);

const InsightsWorkspace = ( {
	activeReport,
	showRuntime = false,
	diagnostics = {},
	databaseAudit = {},
	siteInventory = {},
	systemHealth = {},
	adminPerformance = {},
	cronPressure = {},
	adminAssetAudit = {},
	pluginImpact = {},
	actionScheduler = {},
	onNavigate,
} ) => {
	const [ localReport, setLocalReport ] = useState( null );
	const selectedReport = activeReport || localReport;

	if ( selectedReport ) {
		let report;
		if ( 'database' === selectedReport ) {
			report = <DatabaseAuditPanel initialAudit={ databaseAudit } />;
		} else if ( 'admin-monitor' === selectedReport ) {
			report = <AdminPerformanceMonitorPanel initialSnapshot={ adminPerformance } onNavigate={ onNavigate } />;
		} else if ( 'admin-assets' === selectedReport ) {
			report = <AdminAssetAuditPanel initialSnapshot={ adminAssetAudit } onNavigate={ onNavigate } />;
		} else if ( 'plugin-impact' === selectedReport ) {
			report = <PluginImpactPanel report={ pluginImpact } onNavigate={ onNavigate } />;
		} else if ( 'scheduled-tasks' === selectedReport ) {
			report = <CronPressurePanel initialAudit={ cronPressure } />;
		} else if ( 'action-scheduler' === selectedReport ) {
			report = <ActionSchedulerPanel initialAudit={ actionScheduler } />;
		} else {
			report = <SiteInventoryPanel initialInventory={ siteInventory } systemHealth={ systemHealth } />;
		}

		return (
			<div className="perform-workspace perform-report-workspace">
				<ReportBackButton
					onClick={ () => {
						setLocalReport( null );
						onNavigate( 'inventory' );
					} }
				/>
				{ report }
			</div>
		);
	}

	const diagnosticSummary = diagnostics.summary || {};
	const diagnosticsAvailable =
		Array.isArray( diagnostics.items ) &&
		0 < diagnostics.items.length &&
		[ 'ready', 'warning', 'needs-attention' ].every( ( key ) => Number.isFinite( diagnosticSummary[ key ] ) );
	const reviewCount = ( diagnosticSummary.warning || 0 ) + ( diagnosticSummary[ 'needs-attention' ] || 0 );
	let diagnosticsStatus = __( 'Not measured', 'perform' );
	let diagnosticsStatusType = 'neutral';
	let diagnosticsHeading = __( 'Cache and delivery checks are not measured', 'perform' );
	let diagnosticsBadge = __( 'Cache checks not measured', 'perform' );
	if ( diagnosticsAvailable ) {
		if ( 0 < reviewCount ) {
			diagnosticsStatus = __( 'Review', 'perform' );
			diagnosticsStatusType = 'warning';
			diagnosticsHeading = __( 'Some cache and delivery checks need review', 'perform' );
			diagnosticsBadge = sprintf(
				/* translators: %d: number of cache checks needing review. */
				_n( '%d cache check to review', '%d cache checks to review', reviewCount, 'perform' ),
				reviewCount
			);
		} else {
			diagnosticsStatus = __( 'Good', 'perform' );
			diagnosticsStatusType = 'success';
			diagnosticsHeading = __( 'Cache and delivery checks look good', 'perform' );
			diagnosticsBadge = __( 'Cache and delivery checks ready', 'perform' );
		}
	}
	const inventoryReady = 'ready' === siteInventory.status;
	const databaseReady = 'ready' === databaseAudit.status;
	const databaseStatus = databaseAudit.isStale ? __( 'Refresh recommended', 'perform' ) : __( 'Measured', 'perform' );
	const inventoryStatus = siteInventory.isStale ? __( 'Refresh recommended', 'perform' ) : __( 'Current', 'perform' );
	const optionCount = databaseAudit.totals?.count || 0;
	const databaseDescription = databaseReady
		? sprintf(
				/* translators: %1$s: measured size, %2$d: number of autoloaded options. */
				_n(
					'%1$s autoloaded across %2$d option',
					'%1$s autoloaded across %2$d options',
					optionCount,
					'perform'
				),
				formatBytes( databaseAudit.totals?.sizeBytes || 0 ),
				optionCount
			)
		: __(
				'Open Database options and run a check to see how much data WordPress loads on each request.',
				'perform'
			);
	const siteDescription = inventoryReady
		? sprintf(
				/* translators: %1$d: active plugin count, %2$d: content type count. */
				__( 'Local counts: %1$d active plugins, %2$d content types.', 'perform' ),
				siteInventory.plugins?.activeCount || 0,
				siteInventory.postTypes?.totalCount || 0
			)
		: __( 'Generate a private local inventory for site-specific guidance.', 'perform' );

	return (
		<div className="perform-workspace">
			<WorkspaceHeader
				title={ __( 'Insights', 'perform' ) }
				description={ __(
					'Check your cache setup, review database options, and open reports on your WordPress installation.',
					'perform'
				) }
				status={ diagnosticsBadge }
				statusType={ diagnosticsStatusType }
				actions={
					<Button
						variant="primary"
						icon={ <Squares2X2Icon className="perform-ui-icon" aria-hidden="true" /> }
						onClick={ () => setLocalReport( 'inventory' ) }
					>
						{ __( 'Open site profile', 'perform' ) }
					</Button>
				}
			/>
			<section className="perform-panel perform-insight-health">
				<div className="perform-insight-health__summary">
					<span className="perform-icon-badge" data-status={ diagnosticsStatusType } aria-hidden="true">
						{ 'success' === diagnosticsStatusType ? <CheckCircleIcon /> : <ExclamationTriangleIcon /> }
					</span>
					<div>
						<h2>{ diagnosticsHeading }</h2>
						<p>
							{ __(
								'This summary uses evidence collected privately on this WordPress site.',
								'perform'
							) }
						</p>
					</div>
				</div>
				<div className="perform-insight-status-list">
					<InsightStatusRow
						title={ __( 'Cache and delivery setup', 'perform' ) }
						description={ __( 'Page-cache, menu support, and CDN prerequisites', 'perform' ) }
						status={ diagnosticsStatus }
						statusType={ diagnosticsStatusType }
					/>
					<InsightStatusRow
						title={ __( 'Database pressure', 'perform' ) }
						description={ databaseDescription }
						status={ databaseReady ? databaseStatus : __( 'Not measured', 'perform' ) }
						statusType={ databaseReady && ! databaseAudit.isStale ? 'success' : 'warning' }
					/>
					<InsightStatusRow
						title={ __( 'Site profile', 'perform' ) }
						description={ siteDescription }
						status={ inventoryReady ? inventoryStatus : __( 'Not measured', 'perform' ) }
						statusType={ inventoryReady && ! siteInventory.isStale ? 'success' : 'warning' }
					/>
				</div>
			</section>
			<section className="perform-measurement-callout">
				<div>
					<h2>{ __( 'Core Web Vitals require a separate measurement', 'perform' ) }</h2>
					<p>
						{ __(
							'These checks do not measure visitors’ load times, responsiveness, or layout shifts. Use real-user data to assess Core Web Vitals.',
							'perform'
						) }
					</p>
				</div>
				<div className="perform-measurement-callout__metrics">
					<span>
						<strong>LCP</strong>
						{ __( 'Not connected', 'perform' ) }
					</span>
					<span>
						<strong>INP</strong>
						{ __( 'Not connected', 'perform' ) }
					</span>
					<span>
						<strong>CLS</strong>
						{ __( 'Not connected', 'perform' ) }
					</span>
				</div>
			</section>
			<section className="perform-panel perform-report-list">
				<div className="perform-panel-heading">
					<div>
						<h2>{ __( 'Technical reports', 'perform' ) }</h2>
						<p>
							{ __(
								'Choose a report to inspect its findings. Opening a report does not change your settings.',
								'perform'
							) }
						</p>
					</div>
				</div>
				{ REPORTS.map( ( item ) => {
					const Icon = item.icon;
					return (
						<Button
							key={ item.slug }
							variant="tertiary"
							className="perform-report-link"
							onClick={ () =>
								'inventory' === item.slug ? setLocalReport( 'inventory' ) : onNavigate( item.slug )
							}
						>
							<span className="perform-icon-badge" aria-hidden="true">
								<Icon />
							</span>
							<span>
								<strong>{ item.title }</strong>
								<small>{ item.description }</small>
							</span>
							<ChevronRightIcon className="perform-report-link__chevron" aria-hidden="true" />
						</Button>
					);
				} ) }
			</section>
			<details id="perform-runtime-diagnostics" className="perform-runtime-disclosure" open={ showRuntime }>
				<summary>{ __( 'Runtime cache and delivery checks', 'perform' ) }</summary>
				<DiagnosticsPanel diagnostics={ diagnostics } />
			</details>
		</div>
	);
};

export { REPORTS };
export default InsightsWorkspace;
