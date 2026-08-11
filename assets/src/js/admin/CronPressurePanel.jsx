import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const SETTINGS = window.performwpSettings || {};

const CronPressurePanel = ( { initialAudit = SETTINGS.cronPressure || {} } ) => {
	const [ audit, setAudit ] = useState( initialAudit );
	const [ working, setWorking ] = useState( '' );
	const [ message, setMessage ] = useState( null );
	const hasAudit = 'ready' === audit.status;
	const totals = audit.totals || {};
	const hooks = Array.isArray( audit.frequentHooks ) ? audit.frequentHooks : [];
	const guidance = audit.guidance || {};
	let refreshLabel = hasAudit ? __( 'Refresh check', 'perform' ) : __( 'Run check', 'perform' );

	if ( 'refresh' === working ) {
		refreshLabel = __( 'Checking scheduled tasks…', 'perform' );
	}

	const request = async ( action ) => {
		const isClear = 'perform_clear_cron_pressure_audit' === action;
		setWorking( isClear ? 'clear' : 'refresh' );
		setMessage( {
			type: 'status',
			text: isClear
				? __( 'Clearing the saved diagnostic…', 'perform' )
				: __( 'Checking scheduled tasks…', 'perform' ),
		} );

		try {
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: new URLSearchParams( {
					action,
					nonce: SETTINGS.cronPressureNonce || '',
				} ),
			} );
			const result = await response.json();

			if ( ! response.ok || ! result?.success || ! result.data?.audit ) {
				throw new Error(
					result?.data?.message || __( 'The scheduled-task diagnostic could not be updated.', 'perform' )
				);
			}

			setAudit( result.data.audit );
			setMessage( { type: 'success', text: result.data.message } );
		} catch ( error ) {
			setMessage( {
				type: 'error',
				text: error?.message || __( 'The scheduled-task diagnostic could not be updated.', 'perform' ),
			} );
		} finally {
			setWorking( '' );
		}
	};

	const clearAudit = () => {
		if (
			// eslint-disable-next-line no-alert -- Clearing the saved aggregate requires explicit administrator confirmation.
			window.confirm(
				__( 'Clear the saved scheduled-task diagnostic? No cron events will be changed.', 'perform' )
			)
		) {
			request( 'perform_clear_cron_pressure_audit' );
		}
	};

	return (
		<div className="perform-cron-pressure">
			<div className="perform-cron-pressure__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Scheduled work', 'perform' ) }</p>
					<h2>{ __( 'Scheduled-task pressure', 'perform' ) }</h2>
					<p>
						{ __(
							'Inspect overdue and clustered WP-Cron work without reading hook arguments or changing third-party schedules.',
							'perform'
						) }
					</p>
				</div>
				<div className="perform-cron-pressure__actions">
					<Button
						variant="primary"
						onClick={ () => request( 'perform_refresh_cron_pressure_audit' ) }
						disabled={ Boolean( working ) }
						isBusy={ 'refresh' === working }
					>
						{ refreshLabel }
					</Button>
					{ hasAudit && (
						<Button variant="tertiary" onClick={ clearAudit } disabled={ Boolean( working ) }>
							{ 'clear' === working ? __( 'Clearing…', 'perform' ) : __( 'Clear snapshot', 'perform' ) }
						</Button>
					) }
				</div>
			</div>

			<div className="perform-cron-pressure__privacy">
				<strong>{ __( 'Read-only and bounded', 'perform' ) }</strong>
				<p>
					{ __(
						'Perform scans up to 5,000 scheduled events for this site, stores aggregate counts for one hour, and excludes hook arguments, payloads, URLs, and user data.',
						'perform'
					) }
				</p>
			</div>

			{ message && (
				<div
					className={ `perform-cron-pressure__message is-${ message.type }` }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }

			{ ! hasAudit ? (
				<Card className="perform-cron-pressure__empty">
					<CardBody>
						<h3>{ __( 'Run the first scheduled-task check', 'perform' ) }</h3>
						<p>
							{ __(
								'The check runs only when requested and does not delete or reschedule events.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) : (
				<>
					{ audit.wpCronDisabled && (
						<div className="perform-cron-pressure__notice">
							<strong>{ __( 'Built-in WP-Cron spawning is disabled', 'perform' ) }</strong>
							<p>
								{ __(
									'Confirm that the host or server calls wp-cron.php on a reliable schedule. Perform cannot verify an external runner from this page.',
									'perform'
								) }
							</p>
						</div>
					) }

					<div
						className="perform-cron-pressure__summary"
						aria-label={ __( 'Scheduled-task summary', 'perform' ) }
					>
						<div>
							<span>{ __( 'Due now', 'perform' ) }</span>
							<strong>{ totals.due || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Overdue', 'perform' ) }</span>
							<strong>{ totals.overdue || 0 }</strong>
							<small>{ __( 'More than 5 minutes late', 'perform' ) }</small>
						</div>
						<div>
							<span>{ __( 'Recurring', 'perform' ) }</span>
							<strong>{ totals.recurring || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Peak 5-minute cluster', 'perform' ) }</span>
							<strong>{ totals.peakCluster || 0 }</strong>
							<small>{ __( 'Within the next hour', 'perform' ) }</small>
						</div>
					</div>

					<div className={ `perform-cron-pressure__guidance is-${ guidance.status || 'good' }` }>
						<strong>{ guidance.heading }</strong>
						<p>{ guidance.message }</p>
					</div>

					<Card>
						<CardHeader>
							<div>
								<h3 className="perform-card-title">
									{ __( 'Most frequent scheduled hooks', 'perform' ) }
								</h3>
								<p className="perform-card-description">
									{ __(
										'Hook names and aggregate counts only. Frequency does not prove that a hook is harmful.',
										'perform'
									) }
								</p>
							</div>
						</CardHeader>
						<CardBody>
							<div className="perform-cron-pressure__table-wrap">
								<table className="perform-cron-pressure__table">
									<thead>
										<tr>
											<th scope="col">{ __( 'Hook', 'perform' ) }</th>
											<th scope="col">{ __( 'Scheduled instances', 'perform' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ 0 === hooks.length && (
											<tr>
												<td colSpan="2">
													{ __( 'No scheduled hooks were returned.', 'perform' ) }
												</td>
											</tr>
										) }
										{ hooks.map( ( hook ) => (
											<tr key={ hook.hook }>
												<th scope="row">
													<code>{ hook.hook }</code>
												</th>
												<td>{ hook.count }</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
							<p className="perform-cron-pressure__meta">
								{ totals.truncated
									? __( 'The scan reached its 5,000-event safety limit.', 'perform' )
									: __( 'The bounded scan completed without reaching its event limit.', 'perform' ) }
							</p>
						</CardBody>
					</Card>
				</>
			) }
		</div>
	);
};

export default CronPressurePanel;
