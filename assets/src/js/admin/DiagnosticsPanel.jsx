import { Card, CardBody, CardHeader } from '@wordpress/components';

const STATUS_LABELS = {
	ready: 'Ready',
	warning: 'Warning',
	'needs-attention': 'Needs attention',
};

const DiagnosticsPanel = ( { diagnostics } ) => {
	const items = diagnostics?.items || [];

	if ( ! items.length ) {
		return null;
	}

	return (
		<Card className="perform-diagnostics-panel">
			<CardHeader className="perform-diagnostics-panel__header">
				<div>
					<h3 className="perform-card-title">Runtime Diagnostics</h3>
					<p className="perform-card-description">
						Read-only checks for cache, CDN, and optimization prerequisites.
					</p>
				</div>
			</CardHeader>
			<CardBody>
				<div className="perform-diagnostics-list">
					{ items.map( ( item ) => {
						const status = item.status || 'warning';

						return (
							<div className="perform-diagnostics-item" data-status={ status } key={ item.label }>
								<div className="perform-diagnostics-item__heading">
									<strong>{ item.label }</strong>
									<span className="perform-diagnostics-badge">
										{ STATUS_LABELS[ status ] || 'Warning' }
									</span>
								</div>
								<p>{ item.message }</p>
								<p className="perform-diagnostics-item__action">{ item.action }</p>
							</div>
						);
					} ) }
				</div>
			</CardBody>
		</Card>
	);
};

export default DiagnosticsPanel;
