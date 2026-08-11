import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const SETTINGS = window.performwpSettings || {};

const PluginImpactPanel = ( { report = SETTINGS.pluginImpact || {}, onNavigate } ) => {
	const items = report.items || [];
	const audit = report.adminAssetAudit || {};
	const inventoryReady = 'ready' === report.status;
	let primaryAction = (
		<Button variant="secondary" onClick={ () => onNavigate?.( 'admin-assets' ) }>
			{ __( 'View admin asset evidence', 'perform' ) }
		</Button>
	);
	if ( ! inventoryReady ) {
		primaryAction = (
			<Button variant="primary" onClick={ () => onNavigate?.( 'inventory' ) }>
				{ __( 'Generate site inventory', 'perform' ) }
			</Button>
		);
	} else if ( ! audit.enabled ) {
		primaryAction = (
			<Button variant="primary" onClick={ () => onNavigate?.( 'advanced' ) }>
				{ __( 'Enable admin asset audit', 'perform' ) }
			</Button>
		);
	}

	return (
		<div className="perform-plugin-impact">
			<div className="perform-plugin-impact__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Measured local evidence', 'perform' ) }</p>
					<h2>{ __( 'Plugin impact', 'perform' ) }</h2>
					<p>
						{ __(
							'Connect active plugins to admin asset pressure that Perform has actually observed on this site.',
							'perform'
						) }
					</p>
				</div>
				{ primaryAction }
			</div>

			<div className="perform-plugin-impact__contract">
				<strong>{ __( 'Evidence, not a plugin ranking', 'perform' ) }</strong>
				<p>
					{ __(
						'Asset presence does not prove a plugin is slow or that its files are safe to disable. Perform does not attribute query, callback, or memory cost to a plugin without a dedicated measurement contract.',
						'perform'
					) }
				</p>
			</div>

			<div className="perform-plugin-impact__summary">
				<div>
					<span>{ __( 'Active plugins', 'perform' ) }</span>
					<strong>{ report.activeCount ?? items.length }</strong>
					<small>{ __( 'From site inventory', 'perform' ) }</small>
				</div>
				<div>
					<span>{ __( 'Sampled admin screens', 'perform' ) }</span>
					<strong>{ audit.sampledScreens ?? 0 }</strong>
					<small>
						{ audit.enabled ? __( 'Audit enabled', 'perform' ) : __( 'Audit not enabled', 'perform' ) }
					</small>
				</div>
				<div>
					<span>{ __( 'Plugin signals', 'perform' ) }</span>
					<strong>{ items.filter( ( item ) => item.hasEvidence ).length }</strong>
					<small>{ __( 'High-confidence source matches', 'perform' ) }</small>
				</div>
			</div>

			{ ! inventoryReady && (
				<Card>
					<CardBody>
						<h3>{ __( 'Site inventory is required', 'perform' ) }</h3>
						<p>
							{ __(
								'Generate the private site inventory before matching measured handles to active plugins.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }
			{ inventoryReady && 0 === items.length && (
				<Card>
					<CardBody>
						<h3>{ __( 'No active plugins are available in the bounded inventory', 'perform' ) }</h3>
						<p>{ __( 'Refresh Site Inventory if the active plugin list has changed.', 'perform' ) }</p>
					</CardBody>
				</Card>
			) }

			{ inventoryReady && items.length > 0 && (
				<Card>
					<CardHeader>
						<div>
							<h3 className="perform-card-title">{ __( 'Observed admin asset presence', 'perform' ) }</h3>
							<p className="perform-card-description">
								{ sprintf(
									/* translators: %d: number of sampled admin screens. */
									__(
										'Based on %d sampled admin screens. Zero means not observed in this sample, not zero impact.',
										'perform'
									),
									audit.sampledScreens ?? 0
								) }
							</p>
						</div>
					</CardHeader>
					<CardBody>
						<div className="perform-plugin-impact__table-wrap">
							<table>
								<thead>
									<tr>
										<th scope="col">{ __( 'Plugin', 'perform' ) }</th>
										<th scope="col">{ __( 'Screens', 'perform' ) }</th>
										<th scope="col">{ __( 'Scripts', 'perform' ) }</th>
										<th scope="col">{ __( 'Styles', 'perform' ) }</th>
										<th scope="col">{ __( 'Evidence', 'perform' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ items.map( ( item ) => (
										<tr key={ item.slug }>
											<th scope="row">
												<strong>{ item.name }</strong>
												<small>
													<code>{ item.slug }</code>
													{ item.version ? ` · ${ item.version }` : '' }
												</small>
											</th>
											<td data-label={ __( 'Screens', 'perform' ) }>
												{ item.adminAssets?.screenCount || 0 }
											</td>
											<td data-label={ __( 'Scripts', 'perform' ) }>
												{ item.adminAssets?.scriptCount || 0 }
											</td>
											<td data-label={ __( 'Styles', 'perform' ) }>
												{ item.adminAssets?.styleCount || 0 }
											</td>
											<td data-label={ __( 'Evidence', 'perform' ) }>
												<span className={ item.hasEvidence ? 'is-measured' : 'is-unobserved' }>
													{ item.hasEvidence
														? __( 'Measured locally', 'perform' )
														: __( 'Not observed', 'perform' ) }
												</span>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</CardBody>
				</Card>
			) }

			<Card>
				<CardHeader>
					<div>
						<h3 className="perform-card-title">{ __( 'Not attributed in this release', 'perform' ) }</h3>
						<p className="perform-card-description">
							{ __(
								'These signals need dedicated instrumentation before Perform can connect them to a plugin reliably.',
								'perform'
							) }
						</p>
					</div>
				</CardHeader>
				<CardBody>
					<ul className="perform-plugin-impact__unavailable">
						<li>{ __( 'Frontend asset ownership and request context', 'perform' ) }</li>
						<li>{ __( 'Database query contribution by plugin', 'perform' ) }</li>
						<li>{ __( 'Callback execution time by plugin', 'perform' ) }</li>
						<li>{ __( 'Memory contribution by plugin', 'perform' ) }</li>
					</ul>
				</CardBody>
			</Card>
		</div>
	);
};

export default PluginImpactPanel;
