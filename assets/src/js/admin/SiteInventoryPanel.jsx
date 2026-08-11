import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import SystemHealthPanel from './SystemHealthPanel';

const SETTINGS = window.performwpSettings || {};

const STATE_LABELS = {
	'measured-locally': __( 'Measured locally', 'perform' ),
	'not-available': __( 'Not available', 'perform' ),
	'needs-separate-test': __( 'Needs a separate test', 'perform' ),
};

const StateLabel = ( { state } ) => (
	<span className={ `perform-site-inventory__state is-${ state || 'not-available' }` }>
		{ STATE_LABELS[ state ] || STATE_LABELS[ 'not-available' ] }
	</span>
);

const Fact = ( { label, fact } ) => (
	<div className="perform-site-inventory__fact">
		<span>{ label }</span>
		<strong>{ fact?.value || __( 'Unavailable', 'perform' ) }</strong>
		<StateLabel state={ fact?.state } />
	</div>
);

const formatDuration = ( duration ) => {
	const milliseconds = Number( duration );
	return Number.isFinite( milliseconds ) ? `${ milliseconds.toFixed( 2 ) } ms` : __( 'Unavailable', 'perform' );
};

const getFrontPageLabel = ( value ) => {
	if ( 'page' === value ) {
		return __( 'A static page', 'perform' );
	}

	if ( 'posts' === value ) {
		return __( 'Latest posts', 'perform' );
	}

	return __( 'Unavailable', 'perform' );
};

const SiteInventoryPanel = ( {
	initialInventory = SETTINGS.siteInventory || {},
	systemHealth = SETTINGS.systemHealth || {},
} ) => {
	const [ inventory, setInventory ] = useState( initialInventory );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const hasInventory = 'ready' === inventory.status;
	const plugins = inventory.plugins || {};
	const postTypes = inventory.postTypes || {};
	const environment = inventory.environment || {};
	const activePlugins = ( plugins.items || [] ).filter( ( plugin ) => plugin.active );
	let refreshLabel = hasInventory ? __( 'Refresh inventory', 'perform' ) : __( 'Generate inventory', 'perform' );

	if ( refreshing ) {
		refreshLabel = __( 'Generating inventory…', 'perform' );
	}

	const refreshInventory = async () => {
		setRefreshing( true );
		setMessage( { type: 'status', text: __( 'Generating the local site inventory…', 'perform' ) } );

		try {
			const response = await fetch( SETTINGS.siteInventoryUrl || '', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': SETTINGS.restNonce || '',
				},
			} );
			const result = await response.json();

			if ( ! response.ok || ! result?.inventory ) {
				throw new Error( result?.message || __( 'The site inventory could not be refreshed.', 'perform' ) );
			}

			setInventory( result.inventory );
			setMessage( {
				type: 'success',
				text: result.message || __( 'Site inventory refreshed.', 'perform' ),
			} );
		} catch ( error ) {
			setMessage( {
				type: 'error',
				text: error?.message || __( 'The site inventory could not be refreshed. Please try again.', 'perform' ),
			} );
		} finally {
			setRefreshing( false );
		}
	};

	return (
		<div className="perform-site-inventory">
			<div className="perform-site-inventory__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Local diagnostics', 'perform' ) }</p>
					<h2>{ __( 'Site inventory', 'perform' ) }</h2>
					<p>
						{ __(
							'Create a private snapshot of this site’s software and content structure so Perform can give context-aware guidance.',
							'perform'
						) }
					</p>
				</div>
				<Button variant="primary" onClick={ refreshInventory } disabled={ refreshing } isBusy={ refreshing }>
					{ refreshLabel }
				</Button>
			</div>

			<div className="perform-site-inventory__privacy">
				<strong>{ __( 'Private and local', 'perform' ) }</strong>
				<p>
					{ __(
						'This inventory contains no post content, option values, private URLs, customer data, secrets, or telemetry. It does not change the site.',
						'perform'
					) }
				</p>
			</div>

			{ message && (
				<div
					className={ `perform-site-inventory__message is-${ message.type }` }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }

			<SystemHealthPanel health={ systemHealth } />

			{ ! hasInventory ? (
				<Card className="perform-site-inventory__empty">
					<CardBody>
						<h3>{ __( 'Generate the first inventory', 'perform' ) }</h3>
						<p>
							{ __(
								'The collection runs only when requested, stays on this WordPress site, and is cached for 24 hours.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) : (
				<>
					<div
						className="perform-site-inventory__summary"
						aria-label={ __( 'Site inventory summary', 'perform' ) }
					>
						<div>
							<span>{ __( 'Active plugins', 'perform' ) }</span>
							<strong>{ plugins.activeCount ?? 0 }</strong>
							<small>
								{ sprintf(
									/* translators: %d: installed plugin count. */
									__( '%d installed', 'perform' ),
									plugins.installedCount ?? 0
								) }
							</small>
						</div>
						<div>
							<span>{ __( 'Content types', 'perform' ) }</span>
							<strong>{ postTypes.totalCount ?? 0 }</strong>
							<small>{ __( 'Registered locally', 'perform' ) }</small>
						</div>
						<div>
							<span>{ __( 'Inventory cost', 'perform' ) }</span>
							<strong>{ formatDuration( inventory.collection?.durationMs ) }</strong>
							<small>{ __( 'Manual bounded collection', 'perform' ) }</small>
						</div>
					</div>

					<Card>
						<CardHeader>
							<div>
								<h3 className="perform-card-title">{ __( 'Environment', 'perform' ) }</h3>
								<p className="perform-card-description">
									{ __( 'Versions detected through local WordPress and server APIs.', 'perform' ) }
								</p>
							</div>
						</CardHeader>
						<CardBody>
							<div className="perform-site-inventory__facts">
								<Fact label="WordPress" fact={ environment.wordpress } />
								<Fact label="PHP" fact={ environment.php } />
								<Fact label={ __( 'Database', 'perform' ) } fact={ environment.database } />
								<Fact label="Perform" fact={ environment.perform } />
							</div>
						</CardBody>
					</Card>

					<div className="perform-site-inventory__grid">
						<Card>
							<CardHeader>
								<div>
									<h3 className="perform-card-title">{ __( 'Site configuration', 'perform' ) }</h3>
									<p className="perform-card-description">
										{ __(
											'Facts describe the site; they are not performance judgments.',
											'perform'
										) }
									</p>
								</div>
							</CardHeader>
							<CardBody>
								<dl className="perform-site-inventory__details">
									<div>
										<dt>{ __( 'Active theme', 'perform' ) }</dt>
										<dd>{ inventory.theme?.name || __( 'Unavailable', 'perform' ) }</dd>
									</div>
									<div>
										<dt>{ __( 'Multisite', 'perform' ) }</dt>
										<dd>
											{ inventory.isMultisite
												? __( 'Current site only', 'perform' )
												: __( 'No', 'perform' ) }
										</dd>
									</div>
									<div>
										<dt>{ __( 'Front page', 'perform' ) }</dt>
										<dd>{ getFrontPageLabel( inventory.configuration?.showOnFront ) }</dd>
									</div>
									<div>
										<dt>{ __( 'Enabled Perform modules', 'perform' ) }</dt>
										<dd>{ inventory.perform?.enabledCount ?? 0 }</dd>
									</div>
								</dl>
							</CardBody>
						</Card>

						<Card>
							<CardHeader>
								<div>
									<h3 className="perform-card-title">{ __( 'Measurement coverage', 'perform' ) }</h3>
									<p className="perform-card-description">
										{ __(
											'Local facts cannot replace lab or real-user performance data.',
											'perform'
										) }
									</p>
								</div>
							</CardHeader>
							<CardBody>
								<ul className="perform-site-inventory__signals">
									<li>
										<span>{ __( 'Site structure', 'perform' ) }</span>
										<StateLabel state={ inventory.signals?.localInventory } />
									</li>
									<li>
										<span>{ __( 'Plugin identity', 'perform' ) }</span>
										<StateLabel state={ inventory.signals?.pluginIdentity } />
									</li>
									<li>
										<span>{ __( 'Lab performance', 'perform' ) }</span>
										<StateLabel state={ inventory.signals?.labPerformance } />
									</li>
									<li>
										<span>{ __( 'Core Web Vitals field data', 'perform' ) }</span>
										<StateLabel state={ inventory.signals?.fieldMetrics } />
									</li>
								</ul>
							</CardBody>
						</Card>
					</div>

					<Card>
						<CardHeader>
							<div>
								<h3 className="perform-card-title">{ __( 'Installed plugins', 'perform' ) }</h3>
								<p className="perform-card-description">
									{ __(
										'Visible only to administrators. No plugin is ranked or labelled harmful.',
										'perform'
									) }
								</p>
							</div>
						</CardHeader>
						<CardBody>
							<details>
								<summary>
									{ sprintf(
										/* translators: 1: active count, 2: collected count. */
										__( 'View %1$d active and %2$d collected plugins', 'perform' ),
										activePlugins.length,
										( plugins.items || [] ).length
									) }
								</summary>
								<div className="perform-site-inventory__table-wrap">
									<table className="perform-site-inventory__table">
										<thead>
											<tr>
												<th scope="col">{ __( 'Plugin', 'perform' ) }</th>
												<th scope="col">{ __( 'Version', 'perform' ) }</th>
												<th scope="col">{ __( 'Status', 'perform' ) }</th>
											</tr>
										</thead>
										<tbody>
											{ ( plugins.items || [] ).map( ( plugin ) => (
												<tr key={ plugin.slug }>
													<th scope="row">{ plugin.name }</th>
													<td>{ plugin.version || '—' }</td>
													<td>
														{ plugin.active
															? __( 'Active', 'perform' )
															: __( 'Inactive', 'perform' ) }
													</td>
												</tr>
											) ) }
										</tbody>
									</table>
								</div>
							</details>
							{ plugins.isTruncated && (
								<p className="perform-site-inventory__note">
									{ __( 'The plugin list is truncated to keep collection bounded.', 'perform' ) }
								</p>
							) }
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							<div>
								<h3 className="perform-card-title">{ __( 'Registered content types', 'perform' ) }</h3>
								<p className="perform-card-description">
									{ __(
										'Aggregate readable counts only. No content or private URLs are collected.',
										'perform'
									) }
								</p>
							</div>
						</CardHeader>
						<CardBody>
							<div className="perform-site-inventory__table-wrap">
								<table className="perform-site-inventory__table">
									<thead>
										<tr>
											<th scope="col">{ __( 'Content type', 'perform' ) }</th>
											<th scope="col">{ __( 'Items', 'perform' ) }</th>
											<th scope="col">{ __( 'Visibility', 'perform' ) }</th>
											<th scope="col">{ __( 'Likely owner', 'perform' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ ( postTypes.items || [] ).map( ( postType ) => (
											<tr key={ postType.name }>
												<th scope="row">{ postType.label }</th>
												<td>{ postType.contentCount ?? 0 }</td>
												<td>
													{ postType.public
														? __( 'Public', 'perform' )
														: __( 'Private', 'perform' ) }
												</td>
												<td>
													{ postType.ownership?.label || __( 'Unknown', 'perform' ) }{ ' ' }
													<small>
														{ postType.ownership?.confidence || __( 'unknown', 'perform' ) }{ ' ' }
														{ __( 'confidence', 'perform' ) }
													</small>
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
							{ postTypes.isTruncated && (
								<p className="perform-site-inventory__note">
									{ __(
										'The content-type list is truncated to keep collection bounded.',
										'perform'
									) }
								</p>
							) }
						</CardBody>
					</Card>

					<p className="perform-site-inventory__meta">
						{ __( 'Snapshot generated', 'perform' ) }{ ' ' }
						{ new Date( ( inventory.generatedAt || 0 ) * 1000 ).toLocaleString() }
						{ inventory.isStale ? ` · ${ __( 'Refresh recommended', 'perform' ) }` : '' }
						{ inventory.isMultisite ? ` · ${ __( 'Current site only', 'perform' ) }` : '' }
					</p>
				</>
			) }
		</div>
	);
};

export { formatDuration, StateLabel };
export default SiteInventoryPanel;
