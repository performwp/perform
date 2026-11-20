import { Button } from '@wordpress/components';
import {
	BoltIcon,
	ChartBarIcon,
	CheckCircleIcon,
	CircleStackIcon,
	CpuChipIcon,
	ExclamationTriangleIcon,
	GlobeAltIcon,
	ServerStackIcon,
} from '@heroicons/react/24/outline';
import { __, _n, sprintf } from '@wordpress/i18n';
import { MetricStrip, StatusBadge, WorkspaceHeader } from './WorkspaceLayout';

const STATUS_LABELS = {
	ready: __( 'Ready', 'perform' ),
	review: __( 'Review', 'perform' ),
	observe: __( 'Observing', 'perform' ),
	'not-measured': __( 'Not measured', 'perform' ),
};

const formatBytes = ( bytes ) => {
	const value = Number( bytes );
	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return '0 B';
	}

	if ( value >= 1024 * 1024 ) {
		return `${ ( value / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
	}

	if ( value >= 1024 ) {
		return `${ ( value / 1024 ).toFixed( 1 ) } KB`;
	}

	return `${ Math.round( value ) } B`;
};

const findDiagnostic = ( diagnostics, id ) => ( diagnostics?.items || [] ).find( ( item ) => item.id === id ) || null;

const buildHealthCards = ( { dashboard, diagnostics, databaseAudit, siteInventory } ) => {
	const data = dashboard || {};
	const cache = data.cache || {};
	const server = data.server || {};
	const inventory = siteInventory || {};
	const audit = databaseAudit || {};
	const inventoryReady = 'ready' === inventory.status;
	const auditReady = 'ready' === audit.status;
	const enabledModules = inventory.perform?.items || [];
	const pageCacheEnabled = enabledModules.some( ( module ) => 'enable_page_cache' === module.id );
	const diagnosticSummary = diagnostics?.summary || {};
	const hasDiagnostics =
		Array.isArray( diagnostics?.items ) &&
		diagnostics.items.length > 0 &&
		[ 'ready', 'warning', 'needs-attention' ].every( ( key ) => Number.isFinite( diagnosticSummary[ key ] ) );
	const runtimeAttention = ( diagnosticSummary.warning ?? 0 ) + ( diagnosticSummary[ 'needs-attention' ] ?? 0 );
	const autoloadSize = audit.totals?.sizeBytes ?? 0;
	const autoloadThreshold = audit.thresholdBytes ?? 0;
	const autoloadNeedsReview = auditReady && autoloadThreshold > 0 && autoloadSize > autoloadThreshold;
	let databaseStatus = 'not-measured';
	if ( auditReady ) {
		databaseStatus = autoloadNeedsReview ? 'review' : 'ready';
	}

	let runtimeStatus = 'not-measured';
	let runtimeDescription = __( 'No cache or delivery checks are available yet.', 'perform' );
	if ( hasDiagnostics ) {
		runtimeStatus = runtimeAttention > 0 ? 'review' : 'ready';
		runtimeDescription = __( 'Local cache and delivery prerequisites currently report ready.', 'perform' );
		if ( runtimeAttention > 0 ) {
			runtimeDescription = sprintf(
				/* translators: %d: diagnostic count requiring review. */
				_n(
					'%d cache or delivery check needs review.',
					'%d cache or delivery checks need review.',
					runtimeAttention,
					'perform'
				),
				runtimeAttention
			);
		}
	}

	let cacheStatus = 'not-measured';
	let cacheDescription = __( 'Generate a site profile to check whether page caching is enabled.', 'perform' );
	if ( inventoryReady && ! pageCacheEnabled ) {
		cacheDescription = __(
			'Page cache is disabled. Review dynamic-site exclusions before enabling it.',
			'perform'
		);
	} else if ( pageCacheEnabled ) {
		cacheStatus = 'observe';
		cacheDescription = __(
			'Page cache is enabled; more local traffic is needed for a useful activity summary.',
			'perform'
		);
	}
	if ( pageCacheEnabled && cache.hasStats ) {
		cacheStatus = 'ready';
		cacheDescription = sprintf(
			/* translators: %s: measured cache hit ratio. */
			__( '%s%% measured cache hit ratio from local activity.', 'perform' ),
			cache.hitRatio ?? 0
		);
	}

	let serverStatus = 'not-measured';
	if ( 'warning' === server.opcache?.state ) {
		serverStatus = 'review';
	} else if ( 'ready' === server.opcache?.state || true === audit.objectCache?.persistent ) {
		serverStatus = 'ready';
	}

	const cards = [
		{
			id: 'inventory',
			title: __( 'Site context', 'perform' ),
			status: inventoryReady ? 'ready' : 'not-measured',
			description: inventoryReady
				? sprintf(
						/* translators: 1: active plugin count, 2: registered content type count, 3: enabled Perform module count. */
						__(
							'%1$d active plugins, %2$d content types, and %3$d Perform modules measured locally.',
							'perform'
						),
						inventory.plugins?.activeCount ?? 0,
						inventory.postTypes?.totalCount ?? 0,
						enabledModules.length
					)
				: __( 'Generate a private local inventory before using site-specific guidance.', 'perform' ),
			action: inventoryReady
				? null
				: { label: __( 'Find site profile in Insights', 'perform' ), tab: 'inventory' },
		},
		{
			id: 'database',
			title: __( 'Database pressure', 'perform' ),
			status: databaseStatus,
			description: auditReady
				? sprintf(
						/* translators: %s: measured autoloaded option size. */
						__( '%s of autoloaded options measured against WordPress guidance.', 'perform' ),
						formatBytes( autoloadSize )
					)
				: __( 'Run the local database audit to measure autoloaded option pressure.', 'perform' ),
			action: {
				label: __( 'Open database audit', 'perform' ),
				tab: 'database',
			},
		},
		{
			id: 'runtime',
			title: __( 'Runtime checks', 'perform' ),
			status: runtimeStatus,
			description: runtimeDescription,
			action: { label: __( 'View runtime checks', 'perform' ), tab: 'diagnostics' },
		},
		{
			id: 'cache',
			title: __( 'Page caching', 'perform' ),
			status: cacheStatus,
			description: cacheDescription,
			action: {
				label: cache.hasStats
					? __( 'View cache activity', 'perform' )
					: __( 'Review cache settings', 'perform' ),
				tab: cache.hasStats ? 'cache-stats' : 'cache',
			},
		},
		{
			id: 'server',
			title: __( 'Server acceleration', 'perform' ),
			status: serverStatus,
			description: sprintf(
				/* translators: 1: OPcache state, 2: persistent object-cache state, 3: compression state. */
				__( 'OPcache: %1$s. Persistent object cache: %2$s. Response compression: %3$s.', 'perform' ),
				'ready' === server.opcache?.state ? __( 'detected', 'perform' ) : __( 'not confirmed', 'perform' ),
				true === audit.objectCache?.persistent ? __( 'detected', 'perform' ) : __( 'not confirmed', 'perform' ),
				'ready' === server.compression?.state
					? __( 'detected in PHP', 'perform' )
					: __( 'needs a separate response test', 'perform' )
			),
			action: { label: __( 'Review server evidence', 'perform' ), tab: 'diagnostics' },
		},
	];

	if ( inventoryReady && inventory.patterns?.store ) {
		const dynamicExclusions = findDiagnostic( diagnostics, 'dynamic-cache-exclusions' );
		const needsReview = pageCacheEnabled && 'ready' !== dynamicExclusions?.status;
		let storeDescription = __(
			'Cache exclusions are configured. Review them whenever your store workflows change.',
			'perform'
		);
		if ( ! pageCacheEnabled ) {
			storeDescription = __(
				'A store pattern is present. Review cart, checkout, account, and session exclusions before enabling page cache.',
				'perform'
			);
		} else if ( needsReview ) {
			storeDescription = __(
				'A store pattern is present. Review cart, checkout, account, and session exclusions.',
				'perform'
			);
		}

		cards.push( {
			id: 'store',
			title: __( 'Store safety', 'perform' ),
			status: needsReview ? 'review' : 'ready',
			description: storeDescription,
			action: { label: __( 'Review cache exclusions', 'perform' ), tab: 'cache' },
		} );
	}

	return cards;
};

const HEALTH_ICONS = {
	inventory: GlobeAltIcon,
	database: CircleStackIcon,
	runtime: CpuChipIcon,
	cache: BoltIcon,
	server: ServerStackIcon,
	store: CheckCircleIcon,
};

const HealthRow = ( { card, onAction } ) => {
	const Icon = HEALTH_ICONS[ card.id ] || CheckCircleIcon;
	let statusType = 'neutral';
	if ( 'ready' === card.status ) {
		statusType = 'success';
	} else if ( 'review' === card.status ) {
		statusType = 'warning';
	}

	return (
		<div className="perform-health-row" data-status={ card.status }>
			<span className="perform-icon-badge" data-status={ statusType } aria-hidden="true">
				<Icon />
			</span>
			<div className="perform-health-row__copy">
				<strong>{ card.title }</strong>
				<p>{ card.description }</p>
			</div>
			<StatusBadge status={ statusType }>
				{ STATUS_LABELS[ card.status ] || STATUS_LABELS[ 'not-measured' ] }
			</StatusBadge>
			{ card.action && (
				<Button variant="link" onClick={ () => onAction( card.action ) }>
					{ card.action.label }
				</Button>
			) }
		</div>
	);
};

const DashboardPanel = ( { dashboard, diagnostics, databaseAudit, siteInventory, onNavigate } ) => {
	const data = dashboard || {};
	const links = data.links || {};
	const cache = data.cache || {};
	const assets = data.assetsManager || {};
	const cards = buildHealthCards( { dashboard: data, diagnostics, databaseAudit, siteInventory } );
	const reviewCount = cards.filter( ( card ) => 'review' === card.status ).length;
	const notMeasuredCount = cards.filter( ( card ) => 'not-measured' === card.status ).length;
	const observingCount = cards.filter( ( card ) => 'observe' === card.status ).length;
	const readyCount = cards.filter( ( card ) => 'ready' === card.status ).length;
	const cachedVisits = ( cache.hits || 0 ) + ( cache.staleHits || 0 );
	const configuredAssetRules =
		( assets.disabledJsHandles || 0 ) + ( assets.disabledCssHandles || 0 ) + ( assets.groupDisabledRules || 0 );
	const handleAction = ( action ) => {
		if ( action.tab ) {
			onNavigate?.( action.tab );
			return;
		}

		if ( action.target ) {
			document.getElementById( action.target )?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	};

	let summary = __( 'Your measured local performance checks are ready.', 'perform' );
	if ( reviewCount > 0 ) {
		summary = sprintf(
			/* translators: %d: number of recommendations requiring review. */
			_n( '%d action needs review.', '%d actions need review.', reviewCount, 'perform' ),
			reviewCount
		);
	} else if ( notMeasuredCount > 0 ) {
		summary = __( 'Some areas have not been measured yet.', 'perform' );
	} else if ( observingCount > 0 ) {
		summary = __( 'Some areas need more activity before they can be assessed.', 'perform' );
	}
	let summaryStatus = __( 'Local checks ready', 'perform' );
	let summaryStatusType = 'success';
	if ( 0 < reviewCount ) {
		summaryStatus = __( 'Review recommended', 'perform' );
		summaryStatusType = 'warning';
	} else if ( 0 < notMeasuredCount || 0 < observingCount ) {
		summaryStatus = __( 'More evidence needed', 'perform' );
		summaryStatusType = 'neutral';
	}

	return (
		<div className="perform-workspace perform-dashboard">
			<WorkspaceHeader
				eyebrow={ __( 'Performance overview', 'perform' ) }
				title={ summary }
				description={ __(
					'Review cache activity and site checks below. These checks do not measure the speed visitors experience.',
					'perform'
				) }
				status={ summaryStatus }
				statusType={ summaryStatusType }
				actions={
					<>
						<Button variant="primary" onClick={ () => onNavigate?.( 'inventory' ) }>
							{ __( 'View insights', 'perform' ) }
						</Button>
						<Button variant="link" href={ links.docs || '#' } target="_blank" rel="noopener noreferrer">
							{ __( 'How Perform works', 'perform' ) }
						</Button>
					</>
				}
			/>

			<MetricStrip
				label={ __( 'Site at a glance', 'perform' ) }
				metrics={ [
					{
						icon: ChartBarIcon,
						label: __( 'Cache hit rate', 'perform' ),
						value: cache.hasStats ? `${ cache.hitRatio ?? 0 }%` : '—',
						description: __( 'Local requests', 'perform' ),
					},
					{
						icon: BoltIcon,
						label: __( 'Cached visits', 'perform' ),
						value: cache.hasStats ? cachedVisits : '—',
						description: __( 'Hits and stale hits', 'perform' ),
					},
					{
						icon: CpuChipIcon,
						label: __( 'Asset rules', 'perform' ),
						value: configuredAssetRules,
						description: __( 'Script and style rules', 'perform' ),
					},
					{
						icon: CheckCircleIcon,
						label: __( 'Areas ready', 'perform' ),
						value: readyCount,
						/* translators: %d: number of areas not yet measured. */
						description: sprintf( __( '%d not measured', 'perform' ), notMeasuredCount ),
					},
				] }
				note={ __(
					'All values are measured or configured on this WordPress site. No estimated time or traffic savings are shown.',
					'perform'
				) }
			/>

			<section className="perform-panel perform-health-panel" aria-labelledby="perform-health-title">
				<div className="perform-panel-heading">
					<div>
						<h2 id="perform-health-title">{ __( 'How your site is doing', 'perform' ) }</h2>
						<p>{ __( 'See which checks need attention and open a report for details.', 'perform' ) }</p>
					</div>
					{ 0 < reviewCount && (
						<span className="perform-health-panel__attention">
							<ExclamationTriangleIcon aria-hidden="true" />
							{ sprintf(
								/* translators: %d: number of local checks that need review. */
								_n( '%d item to review', '%d items to review', reviewCount, 'perform' ),
								reviewCount
							) }
						</span>
					) }
				</div>
				<div className="perform-health-list">
					{ cards.map( ( card ) => (
						<HealthRow key={ card.id } card={ card } onAction={ handleAction } />
					) ) }
				</div>
			</section>

			<section className="perform-measurement-callout">
				<div>
					<h2>{ __( 'Core Web Vitals need a separate test', 'perform' ) }</h2>
					<p>
						{ __(
							'These checks run on your site. To see how quickly pages load, respond, and stay visually stable for visitors, use real-user Core Web Vitals data.',
							'perform'
						) }
					</p>
				</div>
				<div
					className="perform-measurement-callout__metrics"
					aria-label={ __( 'Core Web Vitals connection status', 'perform' ) }
				>
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
		</div>
	);
};

export { buildHealthCards, findDiagnostic, formatBytes };
export default DashboardPanel;
