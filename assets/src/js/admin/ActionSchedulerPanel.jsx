import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ArrowPathIcon, TrashIcon } from '@heroicons/react/24/outline';

const SETTINGS = window.performwpSettings || {};

const formatAge = ( seconds ) => {
	if ( seconds < 3600 ) {
		return __( 'Less than one hour', 'perform' );
	}
	if ( seconds < 86400 ) {
		return `${ Math.floor( seconds / 3600 ) } ${ __( 'hours', 'perform' ) }`;
	}
	return `${ Math.floor( seconds / 86400 ) } ${ __( 'days', 'perform' ) }`;
};

const CountTable = ( { title, description, items, emptyLabel } ) => (
	<Card>
		<CardHeader>
			<div>
				<h3 className="perform-card-title">{ title }</h3>
				<p className="perform-card-description">{ description }</p>
			</div>
		</CardHeader>
		<CardBody>
			<div className="perform-action-scheduler__table-wrap">
				<table>
					<thead>
						<tr>
							<th scope="col">{ __( 'Name', 'perform' ) }</th>
							<th scope="col">{ __( 'Actions', 'perform' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ 0 === items.length && (
							<tr>
								<td colSpan="2">{ emptyLabel }</td>
							</tr>
						) }
						{ items.map( ( item ) => (
							<tr key={ item.name }>
								<th scope="row">
									<code>{ item.name }</code>
								</th>
								<td>{ item.count }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</CardBody>
	</Card>
);

const ActionSchedulerPanel = ( { initialAudit = SETTINGS.actionScheduler || {} } ) => {
	const [ audit, setAudit ] = useState( initialAudit );
	const [ working, setWorking ] = useState( '' );
	const [ message, setMessage ] = useState( null );
	const hasAudit = [ 'ready', 'not-available' ].includes( audit.status );
	const isReady = 'ready' === audit.status;
	const counts = audit.counts || {};
	const storage = audit.storage || {};
	let refreshLabel = hasAudit ? __( 'Refresh check', 'perform' ) : __( 'Run check', 'perform' );
	if ( 'refresh' === working ) {
		refreshLabel = __( 'Checking queue…', 'perform' );
	}

	const request = async ( action ) => {
		const isClear = 'perform_clear_action_scheduler_audit' === action;
		setWorking( isClear ? 'clear' : 'refresh' );
		setMessage( {
			type: 'status',
			text: isClear
				? __( 'Clearing the saved diagnostic…', 'perform' )
				: __( 'Checking the Action Scheduler queue…', 'perform' ),
		} );
		try {
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: new URLSearchParams( {
					action,
					nonce: SETTINGS.actionSchedulerNonce || '',
				} ),
			} );
			const result = await response.json();
			if ( ! response.ok || ! result?.success || ! result.data?.audit ) {
				throw new Error(
					result?.data?.message || __( 'The Action Scheduler diagnostic could not be updated.', 'perform' )
				);
			}
			setAudit( result.data.audit );
			setMessage( { type: 'success', text: result.data.message } );
		} catch ( error ) {
			setMessage( {
				type: 'error',
				text: error?.message || __( 'The Action Scheduler diagnostic could not be updated.', 'perform' ),
			} );
		} finally {
			setWorking( '' );
		}
	};

	const clearAudit = () => {
		if (
			// eslint-disable-next-line no-alert -- Clearing the saved aggregate requires explicit administrator confirmation.
			window.confirm(
				__( 'Clear the saved Action Scheduler diagnostic? No queued actions will be changed.', 'perform' )
			)
		) {
			request( 'perform_clear_action_scheduler_audit' );
		}
	};

	return (
		<div className="perform-action-scheduler">
			<div className="perform-action-scheduler__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Background queues', 'perform' ) }</p>
					<h2>{ __( 'Action Scheduler', 'perform' ) }</h2>
					<p>
						{ __(
							'Review queue pressure used by commerce and other extensions without reading action arguments or changing queued work.',
							'perform'
						) }
					</p>
				</div>
				<div className="perform-action-scheduler__actions">
					<Button
						variant="primary"
						onClick={ () => request( 'perform_refresh_action_scheduler_audit' ) }
						disabled={ Boolean( working ) }
						isBusy={ 'refresh' === working }
						icon={
							'refresh' === working ? undefined : (
								<ArrowPathIcon className="perform-ui-icon" aria-hidden="true" />
							)
						}
					>
						{ refreshLabel }
					</Button>
					{ hasAudit && (
						<Button
							variant="tertiary"
							onClick={ clearAudit }
							disabled={ Boolean( working ) }
							icon={
								'clear' === working ? undefined : (
									<TrashIcon className="perform-ui-icon" aria-hidden="true" />
								)
							}
						>
							{ 'clear' === working ? __( 'Clearing…', 'perform' ) : __( 'Clear snapshot', 'perform' ) }
						</Button>
					) }
				</div>
			</div>

			<div className="perform-action-scheduler__privacy">
				<strong>{ __( 'Read-only and private', 'perform' ) }</strong>
				<p>
					{ __(
						'Perform stores aggregate queue counts for one hour. It does not read action arguments, payloads, or log messages, and it never runs, cancels, or deletes queued work.',
						'perform'
					) }
				</p>
			</div>
			{ message && (
				<div
					className={ `perform-action-scheduler__message is-${ message.type }` }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }

			{ ! hasAudit && (
				<Card>
					<CardBody>
						<h3>{ __( 'Run the first queue check', 'perform' ) }</h3>
						<p>
							{ __(
								'The check starts only when requested and saves aggregate counts for this site.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }
			{ 'not-available' === audit.status && (
				<Card>
					<CardBody>
						<h3>{ __( 'Action Scheduler is not available', 'perform' ) }</h3>
						<p>
							{ __(
								'No Action Scheduler actions table was found for this site. There is no queue to inspect.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }

			{ isReady && (
				<>
					<div
						className="perform-action-scheduler__summary"
						aria-label={ __( 'Action Scheduler queue summary', 'perform' ) }
					>
						<div>
							<span>{ __( 'Pending', 'perform' ) }</span>
							<strong>{ counts.pending || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Running', 'perform' ) }</span>
							<strong>{ counts.running || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Failed', 'perform' ) }</span>
							<strong>{ counts.failed || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Complete', 'perform' ) }</span>
							<strong>{ counts.complete || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Canceled', 'perform' ) }</span>
							<strong>{ counts.canceled || 0 }</strong>
						</div>
					</div>
					<div className="perform-action-scheduler__storage">
						<div>
							<span>{ __( 'Oldest pending action', 'perform' ) }</span>
							<strong>
								{ audit.oldestPending?.available
									? formatAge( audit.oldestPending.ageSeconds || 0 )
									: __( 'None pending', 'perform' ) }
							</strong>
						</div>
						<div>
							<span>{ __( 'Stored actions', 'perform' ) }</span>
							<strong>{ storage.actionCount || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Stored log rows', 'perform' ) }</span>
							<strong>
								{ null === storage.logCount || undefined === storage.logCount
									? __( 'Not available', 'perform' )
									: storage.logCount }
							</strong>
						</div>
					</div>
					<p className="perform-action-scheduler__guidance">
						{ __(
							'Counts describe the current queue, not whether a hook is harmful. Compare repeated snapshots and consult the responsible extension before changing retention or runner settings.',
							'perform'
						) }
					</p>
					<p className="perform-action-scheduler__limitation">
						{ __(
							'Failed-action trend is not shown because deriving it reliably would require reading or interpreting private log messages.',
							'perform'
						) }
					</p>
					<div className="perform-action-scheduler__grids">
						<CountTable
							title={ __( 'Largest hooks', 'perform' ) }
							description={ __( 'Aggregate counts only; action arguments are never read.', 'perform' ) }
							items={ audit.topHooks || [] }
							emptyLabel={ __( 'No hook counts were returned.', 'perform' ) }
						/>
						<CountTable
							title={ __( 'Largest groups', 'perform' ) }
							description={ __( 'Group names and aggregate counts only.', 'perform' ) }
							items={ audit.topGroups || [] }
							emptyLabel={ __( 'No group counts were returned.', 'perform' ) }
						/>
					</div>
				</>
			) }
		</div>
	);
};

export default ActionSchedulerPanel;
