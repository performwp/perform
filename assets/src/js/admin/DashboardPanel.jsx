import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

const StatCard = ( { label, value, description } ) => (
	<div className="perform-dashboard-stat">
		<span>{ label }</span>
		<strong>{ value }</strong>
		{ description && <p>{ description }</p> }
	</div>
);

const DashboardPanel = ( { dashboard, diagnostics } ) => {
	const data = dashboard || {};
	const links = data.links || {};
	const cache = data.cache || {};
	const assets = data.assetsManager || {};
	const diagnosticSummary = diagnostics?.summary || {};
	const changelog = data.changelog || [];

	return (
		<div className="perform-dashboard">
			<Card className="perform-dashboard-hero">
				<CardHeader className="perform-dashboard-hero__header">
					<div>
						<p className="perform-dashboard-eyebrow">Dashboard</p>
						<h2>Perform overview</h2>
						<p>
							Review current plugin health, cache activity, and configured optimization rule coverage
							before changing production settings.
						</p>
					</div>
					<div className="perform-dashboard-actions">
						<Button variant="primary" href={ links.docs || '#' } target="_blank" rel="noopener noreferrer">
							View docs
						</Button>
						<Button
							variant="secondary"
							href={ links.support || '#' }
							target="_blank"
							rel="noopener noreferrer"
						>
							Support
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<div className="perform-dashboard-stats">
						<StatCard
							label="Plugin version"
							value={ data.version || 'Unknown' }
							description="Bundled version loaded in this WordPress admin."
						/>
						<StatCard
							label="Ready checks"
							value={ diagnosticSummary.ready ?? 0 }
							description="Runtime diagnostics currently marked ready."
						/>
						<StatCard
							label="Warnings"
							value={
								( diagnosticSummary.warning ?? 0 ) + ( diagnosticSummary[ 'needs-attention' ] ?? 0 )
							}
							description="Review these before broad cache or CDN rollout."
						/>
					</div>
				</CardBody>
			</Card>

			<div className="perform-dashboard-grid">
				<Card>
					<CardHeader>
						<div>
							<h3 className="perform-card-title">Cache summary</h3>
							<p className="perform-card-description">Existing page cache stats from this site.</p>
						</div>
					</CardHeader>
					<CardBody>
						{ cache.hasStats ? (
							<div className="perform-dashboard-metrics">
								<StatCard label="Hit ratio" value={ `${ cache.hitRatio ?? 0 }%` } />
								<StatCard label="Hits" value={ cache.hits ?? 0 } />
								<StatCard label="Misses" value={ cache.misses ?? 0 } />
								<StatCard label="Stale hits" value={ cache.staleHits ?? 0 } />
								<StatCard label="Bypasses" value={ cache.bypasses ?? 0 } />
								<StatCard label="Preload queue" value={ cache.preloadQueueSize ?? 0 } />
							</div>
						) : (
							<p className="perform-dashboard-empty">
								No cache stats have been recorded yet. Enable page cache and revisit after traffic or
								preload runs.
							</p>
						) }
						<a
							className="perform-help-link"
							href={ links.cache || '#' }
							target="_blank"
							rel="noopener noreferrer"
						>
							Read cache docs <span className="perform-help-icon">→</span>
						</a>
					</CardBody>
				</Card>

				<Card>
					<CardHeader>
						<div>
							<h3 className="perform-card-title">Assets Manager rule coverage</h3>
							<p className="perform-card-description">
								Configured rules only. Perform does not store historical byte savings yet.
							</p>
						</div>
					</CardHeader>
					<CardBody>
						<div className="perform-dashboard-metrics">
							<StatCard label="Disabled JS handles" value={ assets.disabledJsHandles ?? 0 } />
							<StatCard label="Disabled CSS handles" value={ assets.disabledCssHandles ?? 0 } />
							<StatCard label="Group rules" value={ assets.groupDisabledRules ?? 0 } />
							<StatCard label="Current URL exceptions" value={ assets.currentPageExceptions ?? 0 } />
						</div>
						<a
							className="perform-help-link"
							href={ links.assetsManager || '#' }
							target="_blank"
							rel="noopener noreferrer"
						>
							Read Assets Manager docs <span className="perform-help-icon">→</span>
						</a>
					</CardBody>
				</Card>
			</div>

			<Card>
				<CardHeader>
					<div>
						<h3 className="perform-card-title">Release notes</h3>
						<p className="perform-card-description">Local release-candidate notes for owner review.</p>
					</div>
				</CardHeader>
				<CardBody>
					<ul className="perform-dashboard-list">
						{ changelog.map( ( item ) => (
							<li key={ item }>{ item }</li>
						) ) }
					</ul>
					<a
						className="perform-help-link"
						href={ links.releaseNotes || '#' }
						target="_blank"
						rel="noopener noreferrer"
					>
						View release updates <span className="perform-help-icon">→</span>
					</a>
				</CardBody>
			</Card>
		</div>
	);
};

export default DashboardPanel;
