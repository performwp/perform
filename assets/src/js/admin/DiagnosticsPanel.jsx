import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STATUS_LABELS = {
	ready: __( 'Ready', 'perform' ),
	warning: __( 'Warning', 'perform' ),
	'needs-attention': __( 'Needs attention', 'perform' ),
};

const DiagnosticsPanel = ( { diagnostics } ) => {
	const items = diagnostics?.items || [];

	if ( ! items.length ) {
		return null;
	}

	return (
		<Card id="perform-runtime-diagnostics" className="perform-diagnostics-panel">
			<CardHeader className="perform-diagnostics-panel__header">
				<div>
					<h3 className="perform-card-title">{ __( 'Runtime Diagnostics', 'perform' ) }</h3>
					<p className="perform-card-description">
						{ __( 'Read-only checks for cache, CDN, and optimization prerequisites.', 'perform' ) }
					</p>
				</div>
			</CardHeader>
			<CardBody>
				<div className="perform-diagnostics-list">
					{ items.map( ( item ) => {
						const status = item.status || 'warning';

						return (
							<div
								className="perform-diagnostics-item"
								data-status={ status }
								key={ item.id || item.label }
							>
								<div className="perform-diagnostics-item__heading">
									<strong>{ item.label }</strong>
									<span className="perform-diagnostics-badge">
										{ STATUS_LABELS[ status ] || STATUS_LABELS.warning }
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
