import { Button, Card, CardBody } from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ArrowPathIcon } from '@heroicons/react/24/outline';

import AutoloadOptionsManager from './AutoloadOptionsManager';

const SETTINGS = window.performwpSettings || {};

const formatBytes = ( value ) => {
	const bytes = Number( value ) || 0;
	if ( bytes < 1024 ) {
		return `${ bytes } B`;
	}

	if ( bytes < 1024 * 1024 ) {
		return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
	}

	return `${ ( bytes / ( 1024 * 1024 ) ).toFixed( 2 ) } MB`;
};

const DatabaseAuditPanel = ( { initialAudit = SETTINGS.databaseAudit || {} } ) => {
	const [ audit, setAudit ] = useState( initialAudit );
	const [ refreshing, setRefreshing ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const hasAudit = 'ready' === audit.status;
	const totals = audit.totals || {};
	const [ needsRefresh, setNeedsRefresh ] = useState( false );
	const auditSequence = useRef( 0 );
	const needsReview = hasAudit && ( totals.sizeBytes || 0 ) > ( audit.thresholdBytes || 800000 );
	let refreshLabel = hasAudit ? __( 'Refresh audit', 'perform' ) : __( 'Run audit', 'perform' );
	if ( refreshing ) {
		refreshLabel = __( 'Running audit…', 'perform' );
	}

	const refreshAudit = async () => {
		const sequence = ++auditSequence.current;
		setRefreshing( true );
		setMessage( { type: 'status', text: __( 'Running the database audit…', 'perform' ) } );

		try {
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: new URLSearchParams( {
					action: 'perform_refresh_autoload_options_audit',
					nonce: SETTINGS.databaseAuditNonce || '',
				} ),
			} );
			const result = await response.json();

			if ( ! response.ok || ! result?.success || ! result.data?.audit ) {
				throw new Error(
					result?.data?.message || __( 'The database audit could not be refreshed.', 'perform' )
				);
			}

			if ( sequence !== auditSequence.current ) {
				return;
			}
			setAudit( result.data.audit );
			setNeedsRefresh( false );
			setMessage( {
				type: 'success',
				text: result.data.message || __( 'Database audit refreshed.', 'perform' ),
			} );
		} catch ( error ) {
			if ( sequence !== auditSequence.current ) {
				return;
			}
			setMessage( {
				type: 'error',
				text: error?.message || __( 'The database audit could not be refreshed. Please try again.', 'perform' ),
			} );
		} finally {
			if ( sequence === auditSequence.current ) {
				setRefreshing( false );
			}
		}
	};

	return (
		<div className="perform-database-audit">
			<div className="perform-database-audit__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Database health', 'perform' ) }</p>
					<h2>{ __( 'Autoloaded options audit', 'perform' ) }</h2>
					<p>
						{ __(
							'The audit shows measured totals and option names and sizes, never stored values. Review each option before changing its autoload policy.',
							'perform'
						) }
					</p>
				</div>
				<Button
					variant="primary"
					onClick={ () => refreshAudit() }
					disabled={ refreshing }
					isBusy={ refreshing }
					icon={ refreshing ? undefined : <ArrowPathIcon className="perform-ui-icon" aria-hidden="true" /> }
				>
					{ refreshLabel }
				</Button>
			</div>

			{ message && (
				<div
					className={ `perform-database-audit__message is-${ message.type }` }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }

			{ ! hasAudit ? (
				<Card className="perform-database-audit__empty">
					<CardBody>
						<h3>{ __( 'Run the first local audit', 'perform' ) }</h3>
						<p>
							{ __(
								'The scan runs only when requested, stores a bounded snapshot for this site, and makes no external requests.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) : (
				<>
					<div
						className="perform-database-audit__summary"
						aria-label={ __( 'Autoloaded options summary', 'perform' ) }
					>
						<div>
							<span>{ __( 'Total autoloaded size', 'perform' ) }</span>
							<strong>{ formatBytes( totals.sizeBytes ) }</strong>
						</div>
						<div>
							<span>{ __( 'Autoloaded options', 'perform' ) }</span>
							<strong>{ totals.count || 0 }</strong>
						</div>
						<div>
							<span>{ __( 'Persistent object cache', 'perform' ) }</span>
							<strong>
								{ audit.objectCache?.persistent
									? __( 'Detected', 'perform' )
									: __( 'Not detected', 'perform' ) }
							</strong>
						</div>
					</div>

					<div
						className={ `perform-database-audit__guidance ${
							needsReview ? 'is-review' : 'is-informational'
						}` }
					>
						<strong>
							{ needsReview ? __( 'Review recommended', 'perform' ) : __( 'Informational', 'perform' ) }
						</strong>
						<p>
							{ needsReview
								? __(
										'The measured total is above WordPress Site Health’s guidance threshold. Review the largest entries with the extension owner before changing autoload behavior.',
										'perform'
									)
								: __(
										'The measured total is below WordPress Site Health’s guidance threshold. Size alone does not prove that an option is unnecessary.',
										'perform'
									) }
						</p>
					</div>

					<p className="perform-database-audit__meta">
						{ __( 'Snapshot generated', 'perform' ) }{ ' ' }
						{ new Date( ( audit.generatedAt || 0 ) * 1000 ).toLocaleString() }
						{ audit.isStale || needsRefresh
							? ` · ${ __( 'Totals may be out of date; refresh the audit', 'perform' ) }`
							: '' }
						{ audit.isMultisite ? ` · ${ __( 'Current site only', 'perform' ) }` : '' }
					</p>
				</>
			) }
			<AutoloadOptionsManager
				audit={ audit }
				onMutation={ ( success ) => {
					auditSequence.current++;
					setRefreshing( false );
					setMessage( ( current ) => ( current?.type === 'status' ? null : current ) );
					setNeedsRefresh( true );
					if ( success ) {
						refreshAudit();
					}
				} }
			/>
		</div>
	);
};

export { formatBytes };
export default DatabaseAuditPanel;
