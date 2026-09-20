import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	ArrowTopRightOnSquareIcon,
	BoltIcon,
	CheckCircleIcon,
	CircleStackIcon,
	CodeBracketSquareIcon,
	CpuChipIcon,
	DocumentTextIcon,
	EyeIcon,
	ExclamationTriangleIcon,
	InformationCircleIcon,
	MinusCircleIcon,
	ServerStackIcon,
	ShoppingCartIcon,
	Squares2X2Icon,
} from '@heroicons/react/24/outline';

const STATUS_LABELS = {
	ready: __( 'Ready', 'perform' ),
	review: __( 'Review', 'perform' ),
	observe: __( 'Observing', 'perform' ),
	'not-measured': __( 'Not measured', 'perform' ),
};

const STATUS_ICONS = {
	ready: CheckCircleIcon,
	review: ExclamationTriangleIcon,
	observe: EyeIcon,
	'not-measured': MinusCircleIcon,
};

const HEALTH_CARD_ICONS = {
	inventory: Squares2X2Icon,
	database: CircleStackIcon,
	runtime: CpuChipIcon,
	cache: BoltIcon,
	server: ServerStackIcon,
	store: ShoppingCartIcon,
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
	const hasDiagnostics = ( diagnostics?.items || [] ).length > 0;
	const diagnosticSummary = diagnostics?.summary || {};
	const runtimeAttention = ( diagnosticSummary.warning ?? 0 ) + ( diagnosticSummary[ 'needs-attention' ] ?? 0 );
	const autoloadSize = audit.totals?.sizeBytes ?? 0;
	const autoloadThreshold = audit.thresholdBytes ?? 0;
	const autoloadNeedsReview = auditReady && autoloadThreshold > 0 && autoloadSize > autoloadThreshold;
	let databaseStatus = 'not-measured';
	if ( auditReady ) {
		databaseStatus = autoloadNeedsReview ? 'review' : 'ready';
	}

	let runtimeStatus = 'not-measured';
	let runtimeDescription = __( 'Runtime diagnostics are not available in the current page payload.', 'perform' );
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
	let cacheDescription = __(
		'Page cache is disabled. Enable it only after reviewing dynamic-site exclusions.',
		'perform'
	);
	if ( pageCacheEnabled ) {
		cacheStatus = 'observe';
		cacheDescription = __(
			'Page cache is enabled; more local traffic is needed for a useful activity summary.',
			'perform'
		);
	}
	if ( cache.hasStats ) {
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
			action: inventoryReady ? null : { label: __( 'Generate inventory', 'perform' ), tab: 'inventory' },
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
				label: auditReady ? __( 'Review database', 'perform' ) : __( 'Run database audit', 'perform' ),
				tab: 'database',
			},
		},
		{
			id: 'runtime',
			title: __( 'Runtime checks', 'perform' ),
			status: runtimeStatus,
			description: runtimeDescription,
			action: { label: __( 'View runtime checks', 'perform' ), target: 'perform-runtime-diagnostics' },
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
			action: { label: __( 'Review server evidence', 'perform' ), tab: 'database' },
		},
	];

	if ( inventoryReady && inventory.patterns?.store ) {
		const dynamicExclusions = findDiagnostic( diagnostics, 'dynamic-cache-exclusions' );
		const needsReview = pageCacheEnabled && 'ready' !== dynamicExclusions?.status;

		cards.push( {
			id: 'store',
			title: __( 'Store safety', 'perform' ),
			status: needsReview ? 'review' : 'ready',
			description: needsReview
				? __( 'A store pattern is present. Review cart, checkout, account, and session exclusions.', 'perform' )
				: __(
						'A store pattern is present and current cache diagnostics show no unresolved exclusion warning.',
						'perform'
				  ),
			action: { label: __( 'Review cache exclusions', 'perform' ), tab: 'cache' },
		} );
	}

	return cards;
};

const HealthCard = ( { card, onAction } ) => {
	const CardIcon = HEALTH_CARD_ICONS[ card.id ] || CpuChipIcon;
	const StatusIcon = STATUS_ICONS[ card.status ] || STATUS_ICONS[ 'not-measured' ];

	return (
		<Card className="perform-health-card" data-status={ card.status }>
			<CardBody>
				<div className="perform-health-card__heading">
					<div className="perform-health-card__title">
						<span className="perform-health-card__icon" aria-hidden="true">
							<CardIcon className="perform-ui-icon" />
						</span>
						<h3>{ card.title }</h3>
					</div>
					<span className="perform-health-card__status">
						<StatusIcon className="perform-ui-icon" aria-hidden="true" />
						{ STATUS_LABELS[ card.status ] || STATUS_LABELS[ 'not-measured' ] }
					</span>
				</div>
				<p>{ card.description }</p>
				{ card.action && (
					<Button variant="link" onClick={ () => onAction( card.action ) }>
						{ card.action.label }
					</Button>
				) }
			</CardBody>
		</Card>
	);
};

const StatCard = ( { label, value, description, icon: Icon, status } ) => (
	<div className="perform-dashboard-stat">
		{ Icon && (
			<span className="perform-dashboard-stat__icon" data-status={ status } aria-hidden="true">
				<Icon className="perform-ui-icon" />
			</span>
		) }
		<div className="perform-dashboard-stat__content">
			<span>{ label }</span>
			<strong>{ value }</strong>
			{ description && <p>{ description }</p> }
		</div>
	</div>
);

const DashboardPanel = ( { dashboard, diagnostics, databaseAudit, siteInventory, onNavigate } ) => {
	const data = dashboard || {};
	const links = data.links || {};
	const cache = data.cache || {};
	const assets = data.assetsManager || {};
	const changelog = data.changelog || [];
	const cards = buildHealthCards( { dashboard: data, diagnostics, databaseAudit, siteInventory } );
	const reviewCount = cards.filter( ( card ) => 'review' === card.status ).length;
	const notMeasuredCount = cards.filter( ( card ) => 'not-measured' === card.status ).length;
	const readyCount = cards.filter( ( card ) => 'ready' === card.status ).length;
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
		summary = __( 'Finish the local diagnostics to unlock site-specific guidance.', 'perform' );
	}

	return (
		<div className="perform-dashboard">
			<section className="perform-dashboard-overview" aria-labelledby="perform-health-title">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Performance health', 'perform' ) }</p>
					<h2 id="perform-health-title">{ summary }</h2>
					<p>
						{ __(
							'Perform combines private local evidence into clear next steps. It never turns these checks into an unverified speed score.',
							'perform'
						) }
					</p>
				</div>
				<div className="perform-dashboard-actions">
					<Button
						variant="primary"
						href={ links.docs || '#' }
						target="_blank"
						rel="noopener noreferrer"
						icon={ <ArrowTopRightOnSquareIcon className="perform-ui-icon" aria-hidden="true" /> }
						iconPosition="right"
					>
						{ __( 'View documentation', 'perform' ) }
					</Button>
					<Button
						variant="secondary"
						href={ links.support || '#' }
						target="_blank"
						rel="noopener noreferrer"
						icon={ <ArrowTopRightOnSquareIcon className="perform-ui-icon" aria-hidden="true" /> }
						iconPosition="right"
					>
						{ __( 'Get support', 'perform' ) }
					</Button>
				</div>
			</section>

			<div className="perform-dashboard-stats" aria-label={ __( 'Performance health summary', 'perform' ) }>
				<StatCard
					label={ __( 'Ready', 'perform' ) }
					value={ readyCount }
					description={ __( 'Measured local areas', 'perform' ) }
					icon={ CheckCircleIcon }
					status="ready"
				/>
				<StatCard
					label={ __( 'Review', 'perform' ) }
					value={ reviewCount }
					description={ __( 'Actions worth checking', 'perform' ) }
					icon={ ExclamationTriangleIcon }
					status="review"
				/>
				<StatCard
					label={ __( 'Not measured', 'perform' ) }
					value={ notMeasuredCount }
					description={ __( 'Diagnostics still available', 'perform' ) }
					icon={ MinusCircleIcon }
					status="not-measured"
				/>
			</div>

			<div className="perform-health-grid">
				{ cards.map( ( card ) => (
					<HealthCard key={ card.id } card={ card } onAction={ handleAction } />
				) ) }
			</div>

			<div className="perform-dashboard-measurement-note">
				<InformationCircleIcon className="perform-ui-icon" aria-hidden="true" />
				<div>
					<strong>{ __( 'Core Web Vitals need a separate test', 'perform' ) }</strong>
					<p>
						{ __(
							'Local WordPress diagnostics cannot prove real-user LCP, INP, or CLS. Field data and lab testing remain separate evidence sources.',
							'perform'
						) }
					</p>
				</div>
			</div>

			<div className="perform-dashboard-grid">
				<Card>
					<CardHeader>
						<div className="perform-card-heading">
							<BoltIcon className="perform-ui-icon" aria-hidden="true" />
							<div>
								<h3 className="perform-card-title">{ __( 'Cache value observed', 'perform' ) }</h3>
								<p className="perform-card-description">
									{ __(
										'Measured requests from this site, without estimated time savings.',
										'perform'
									) }
								</p>
							</div>
						</div>
					</CardHeader>
					<CardBody>
						{ cache.hasStats ? (
							<div className="perform-dashboard-metrics">
								<StatCard
									label={ __( 'Hit ratio', 'perform' ) }
									value={ `${ cache.hitRatio ?? 0 }%` }
								/>
								<StatCard label={ __( 'Hits', 'perform' ) } value={ cache.hits ?? 0 } />
								<StatCard label={ __( 'Misses', 'perform' ) } value={ cache.misses ?? 0 } />
								<StatCard label={ __( 'Bypasses', 'perform' ) } value={ cache.bypasses ?? 0 } />
							</div>
						) : (
							<p className="perform-dashboard-empty">
								{ __( 'No cache activity has been measured yet.', 'perform' ) }
							</p>
						) }
						<Button variant="link" onClick={ () => onNavigate?.( 'cache-stats' ) }>
							{ __( 'Open cache activity', 'perform' ) }
						</Button>
					</CardBody>
				</Card>

				<Card>
					<CardHeader>
						<div className="perform-card-heading">
							<CodeBracketSquareIcon className="perform-ui-icon" aria-hidden="true" />
							<div>
								<h3 className="perform-card-title">{ __( 'Configured asset coverage', 'perform' ) }</h3>
								<p className="perform-card-description">
									{ __(
										'Configured rules only; no historical byte savings are claimed.',
										'perform'
									) }
								</p>
							</div>
						</div>
					</CardHeader>
					<CardBody>
						<div className="perform-dashboard-metrics">
							<StatCard
								label={ __( 'Disabled JS', 'perform' ) }
								value={ assets.disabledJsHandles ?? 0 }
							/>
							<StatCard
								label={ __( 'Disabled CSS', 'perform' ) }
								value={ assets.disabledCssHandles ?? 0 }
							/>
							<StatCard
								label={ __( 'Group rules', 'perform' ) }
								value={ assets.groupDisabledRules ?? 0 }
							/>
							<StatCard
								label={ __( 'URL exceptions', 'perform' ) }
								value={ assets.currentPageExceptions ?? 0 }
							/>
						</div>
						<Button variant="link" onClick={ () => onNavigate?.( 'assets' ) }>
							{ __( 'Open Assets Manager', 'perform' ) }
						</Button>
					</CardBody>
				</Card>
			</div>

			<Card>
				<CardHeader>
					<div className="perform-card-heading">
						<DocumentTextIcon className="perform-ui-icon" aria-hidden="true" />
						<div>
							<h3 className="perform-card-title">{ __( 'What this build includes', 'perform' ) }</h3>
							<p className="perform-card-description">
								{ __( 'Evidence-bound product updates included in the current code.', 'perform' ) }
							</p>
						</div>
					</div>
				</CardHeader>
				<CardBody>
					<ul className="perform-dashboard-list">
						{ changelog.map( ( item ) => (
							<li key={ item }>{ item }</li>
						) ) }
					</ul>
				</CardBody>
			</Card>
		</div>
	);
};

export { buildHealthCards, findDiagnostic, formatBytes };
export default DashboardPanel;
