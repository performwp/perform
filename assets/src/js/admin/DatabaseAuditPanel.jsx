import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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
	const largestOptions = audit.largestOptions || [];
	const needsReview = hasAudit && ( totals.sizeBytes || 0 ) > ( audit.thresholdBytes || 800000 );
	let refreshLabel = hasAudit ? __( 'Refresh audit', 'perform' ) : __( 'Run audit', 'perform' );
	if ( refreshing ) {
		refreshLabel = __( 'Running audit…', 'perform' );
	}

	const refreshAudit = async () => {
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

			setAudit( result.data.audit );
			setMessage( {
				type: 'success',
				text: result.data.message || __( 'Database audit refreshed.', 'perform' ),
			} );
		} catch ( error ) {
			setMessage( {
				type: 'error',
				text: error?.message || __( 'The database audit could not be refreshed. Please try again.', 'perform' ),
			} );
		} finally {
			setRefreshing( false );
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
							'Review how much option data WordPress loads on every request. Perform shows names and sizes, never stored values, and does not change third-party data.',
							'perform'
						) }
					</p>
				</div>
				<Button variant="primary" onClick={ refreshAudit } disabled={ refreshing } isBusy={ refreshing }>
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

					<Card>
						<CardHeader>
							<div>
								<h3 className="perform-card-title">
									{ __( 'Largest autoloaded options', 'perform' ) }
								</h3>
								<p className="perform-card-description">
									{ __(
										'Showing up to 20 option names and stored sizes. Values remain private.',
										'perform'
									) }
								</p>
							</div>
						</CardHeader>
						<CardBody>
							<div className="perform-database-audit__table-wrap">
								<table className="perform-database-audit__table">
									<thead>
										<tr>
											<th scope="col">{ __( 'Option', 'perform' ) }</th>
											<th scope="col">{ __( 'Size', 'perform' ) }</th>
											<th scope="col">{ __( 'Autoload state', 'perform' ) }</th>
											<th scope="col">{ __( 'Likely owner', 'perform' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ 0 === largestOptions.length && (
											<tr>
												<td colSpan="4">
													{ __(
														'No autoloaded options were returned for this site.',
														'perform'
													) }
												</td>
											</tr>
										) }
										{ largestOptions.map( ( option ) => (
											<tr key={ option.name }>
												<th scope="row">
													<code>{ option.name }</code>
												</th>
												<td>{ formatBytes( option.sizeBytes ) }</td>
												<td>{ option.autoload }</td>
												<td>
													{ option.ownership?.label || __( 'Unknown', 'perform' ) }
													<span className="perform-database-audit__confidence">
														{ option.ownership?.confidence || __( 'unknown', 'perform' ) }{ ' ' }
														{ __( 'confidence', 'perform' ) }
													</span>
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						</CardBody>
					</Card>

					<p className="perform-database-audit__meta">
						{ __( 'Snapshot generated', 'perform' ) }{ ' ' }
						{ new Date( ( audit.generatedAt || 0 ) * 1000 ).toLocaleString() }
						{ audit.isStale ? ` · ${ __( 'Refresh recommended', 'perform' ) }` : '' }
						{ audit.isMultisite ? ` · ${ __( 'Current site only', 'perform' ) }` : '' }
					</p>
				</>
			) }
		</div>
	);
};

export { formatBytes };
export default DatabaseAuditPanel;
