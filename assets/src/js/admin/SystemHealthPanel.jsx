import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const STATUS_LABELS = {
	good: __( 'Good', 'perform' ),
	review: __( 'Review', 'perform' ),
	'action-recommended': __( 'Action recommended', 'perform' ),
	unavailable: __( 'Unavailable', 'perform' ),
};

const SystemHealthPanel = ( { health = {} } ) => {
	const summary = health.summary || {};
	const signals = Array.isArray( health.signals ) ? health.signals : [];
	const reviewCount = Number( summary.review || 0 ) + Number( summary.actionRecommended || 0 );

	return (
		<Card className="perform-system-health">
			<CardHeader>
				<div>
					<h3 className="perform-card-title">{ __( 'System health', 'perform' ) }</h3>
					<p className="perform-card-description">
						{ __(
							'Local runtime signals with practical guidance. Perform does not change server configuration.',
							'perform'
						) }
					</p>
				</div>
				<span className={ `perform-system-health__summary ${ reviewCount > 0 ? 'has-review' : '' }` }>
					{ reviewCount > 0
						? sprintf(
								/* translators: %d: number of system signals to review. */
								__( '%d to review', 'perform' ),
								reviewCount
						  )
						: __( 'No actions suggested', 'perform' ) }
				</span>
			</CardHeader>
			<CardBody>
				{ 0 === signals.length ? (
					<p>{ __( 'System health signals are unavailable in this environment.', 'perform' ) }</p>
				) : (
					<div className="perform-system-health__grid">
						{ signals.map( ( signal ) => (
							<div className="perform-system-health__signal" key={ signal.id }>
								<div className="perform-system-health__signal-heading">
									<div>
										<span>{ signal.label }</span>
										<strong>{ signal.value || __( 'Unavailable', 'perform' ) }</strong>
									</div>
									<span className={ `perform-system-health__status is-${ signal.status }` }>
										{ STATUS_LABELS[ signal.status ] || STATUS_LABELS.unavailable }
									</span>
								</div>
								<p>{ signal.recommendation }</p>
							</div>
						) ) }
					</div>
				) }
				<p className="perform-system-health__privacy">
					{ __(
						'Paths, credentials, request data, log contents, and private configuration values are intentionally excluded.',
						'perform'
					) }
				</p>
			</CardBody>
		</Card>
	);
};

export default SystemHealthPanel;
