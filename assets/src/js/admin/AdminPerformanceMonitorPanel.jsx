import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { AdjustmentsHorizontalIcon, TrashIcon } from '@heroicons/react/24/outline';

const SETTINGS = window.performwpSettings || {};

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

const TYPE_LABELS = {
	'admin-page': __( 'Admin page', 'perform' ),
	ajax: __( 'AJAX', 'perform' ),
	rest: __( 'REST', 'perform' ),
	'cron-adjacent': __( 'Cron-adjacent', 'perform' ),
};

const REASON_LABELS = {
	'slow-request': __( 'Slow request', 'perform' ),
	'high-memory': __( 'High memory', 'perform' ),
	'high-query-count': __( 'High query count', 'perform' ),
};

const AdminPerformanceMonitorPanel = ( { initialSnapshot = SETTINGS.adminPerformance || {}, onNavigate } ) => {
	const [ snapshot, setSnapshot ] = useState( initialSnapshot );
	const [ clearing, setClearing ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const contexts = snapshot.contexts || [];
	const thresholds = snapshot.thresholds || { durationMs: 1000, memoryBytes: 134217728, queryCount: 100 };

	const clearData = async () => {
		// eslint-disable-next-line no-alert -- Aggregate removal requires explicit administrator confirmation.
		if ( ! window.confirm( __( 'Clear all collected admin performance aggregates?', 'perform' ) ) ) {
			return;
		}

		setClearing( true );
		setMessage( { type: 'status', text: __( 'Clearing admin performance data…', 'perform' ) } );
		try {
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: new URLSearchParams( {
					action: 'perform_clear_admin_performance_monitor',
					nonce: SETTINGS.adminMonitorNonce || '',
				} ),
			} );
			const result = await response.json();
			if ( ! response.ok || ! result?.success || ! result.data?.snapshot ) {
				throw new Error(
					result?.data?.message || __( 'Admin performance data could not be cleared.', 'perform' )
				);
			}

			setSnapshot( result.data.snapshot );
			setMessage( {
				type: 'success',
				text: result.data.message || __( 'Admin performance data cleared.', 'perform' ),
			} );
		} catch ( error ) {
			setMessage( {
				type: 'error',
				text: error?.message || __( 'Admin performance data could not be cleared.', 'perform' ),
			} );
		} finally {
			setClearing( false );
		}
	};

	return (
		<div className="perform-admin-monitor">
			<div className="perform-admin-monitor__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Private admin diagnostics', 'perform' ) }</p>
					<h2>{ __( 'Admin Performance Monitor', 'perform' ) }</h2>
					<p>
						{ __(
							'Find repeated slow WordPress admin contexts using bounded aggregate timing, peak memory, and query-count evidence.',
							'perform'
						) }
					</p>
				</div>
				{ snapshot.enabled ? (
					<Button
						variant="secondary"
						onClick={ clearData }
						disabled={ clearing || 0 === contexts.length }
						isBusy={ clearing }
						icon={ clearing ? undefined : <TrashIcon className="perform-ui-icon" aria-hidden="true" /> }
					>
						{ clearing ? __( 'Clearing…', 'perform' ) : __( 'Clear collected data', 'perform' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={ () => onNavigate?.( 'advanced' ) }
						icon={ <AdjustmentsHorizontalIcon className="perform-ui-icon" aria-hidden="true" /> }
					>
						{ __( 'Enable in Advanced', 'perform' ) }
					</Button>
				) }
			</div>

			<div className="perform-admin-monitor__privacy">
				<strong>{ __( 'Aggregate-only and per site', 'perform' ) }</strong>
				<p>
					{ __(
						'Perform keeps up to 50 contexts for 14 days. It does not store full URLs, query arguments, request payloads, IP addresses, usernames, emails, or query text, and it does not enable SAVEQUERIES.',
						'perform'
					) }
				</p>
				<p className="perform-admin-monitor__thresholds">
					{ sprintf(
						/* translators: 1: duration in milliseconds, 2: memory size, 3: query count. */
						__( 'Review thresholds: %1$s ms duration, %2$s peak memory, or %3$s queries.', 'perform' ),
						thresholds.durationMs,
						formatBytes( thresholds.memoryBytes ),
						thresholds.queryCount
					) }
				</p>
			</div>

			{ message && (
				<div
					className={ `perform-admin-monitor__message is-${ message.type }` }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }

			{ ! snapshot.enabled && (
				<Card className="perform-admin-monitor__empty">
					<CardBody>
						<h3>{ __( 'Monitoring is off', 'perform' ) }</h3>
						<p>
							{ __(
								'Disabled mode registers no request-capture hooks and performs no report writes.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }

			{ snapshot.enabled && 0 === contexts.length && (
				<Card className="perform-admin-monitor__empty">
					<CardBody>
						<h3>{ __( 'Collecting the first admin contexts', 'perform' ) }</h3>
						<p>
							{ __(
								'Use WordPress admin normally, then return here to review aggregate evidence.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }

			{ contexts.length > 0 && (
				<Card>
					<CardHeader>
						<div>
							<h3 className="perform-card-title">{ __( 'Measured admin contexts', 'perform' ) }</h3>
							<p className="perform-card-description">
								{ __(
									'Sorted by worst observed duration. A threshold is a review signal, not a root-cause diagnosis.',
									'perform'
								) }
							</p>
						</div>
					</CardHeader>
					<CardBody>
						<div className="perform-admin-monitor__guidance">
							<strong>{ __( 'How to investigate', 'perform' ) }</strong>
							<p>
								{ __(
									'Reproduce the same workflow, compare repeated samples, and use a dedicated profiler before changing plugin or query behavior.',
									'perform'
								) }
							</p>
						</div>
						<div className="perform-admin-monitor__table-wrap">
							<table className="perform-admin-monitor__table">
								<thead>
									<tr>
										<th scope="col">{ __( 'Context', 'perform' ) }</th>
										<th scope="col">{ __( 'Requests', 'perform' ) }</th>
										<th scope="col">{ __( 'Duration', 'perform' ) }</th>
										<th scope="col">{ __( 'Peak memory', 'perform' ) }</th>
										<th scope="col">{ __( 'Queries', 'perform' ) }</th>
										<th scope="col">{ __( 'Guidance', 'perform' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ contexts.map( ( context ) => (
										<tr key={ context.key } data-status={ context.status }>
											<th scope="row">
												<code>{ context.identifier }</code>
												<small>
													{ TYPE_LABELS[ context.type ] || __( 'Unknown', 'perform' ) } ·{ ' ' }
													{ context.capabilityBucket }
												</small>
											</th>
											<td data-label={ __( 'Requests', 'perform' ) }>{ context.count }</td>
											<td data-label={ __( 'Duration', 'perform' ) }>
												<strong>{ context.maxDurationMs } ms</strong>
												<small>
													{ context.averageDurationMs } ms { __( 'average', 'perform' ) }
												</small>
											</td>
											<td data-label={ __( 'Peak memory', 'perform' ) }>
												{ formatBytes( context.maxMemoryBytes ) }
											</td>
											<td data-label={ __( 'Queries', 'perform' ) }>{ context.maxQueryCount }</td>
											<td data-label={ __( 'Guidance', 'perform' ) }>
												<span className="perform-admin-monitor__state">
													{ 'review' === context.status
														? __( 'Review', 'perform' )
														: __( 'Observing', 'perform' ) }
												</span>
												<small>
													{ context.reasons?.length
														? context.reasons
																.map( ( reason ) => REASON_LABELS[ reason ] || reason )
																.join( ', ' )
														: __( 'Below current review thresholds', 'perform' ) }
												</small>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</CardBody>
				</Card>
			) }
		</div>
	);
};

export { formatBytes };
export default AdminPerformanceMonitorPanel;
