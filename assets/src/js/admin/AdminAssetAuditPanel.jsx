import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const SETTINGS = window.performwpSettings || {};

const formatAssetCount = ( count ) =>
	sprintf(
		/* translators: %s: number of captured assets. */
		__( '%s captured assets', 'perform' ),
		count
	);

const AdminAssetAuditPanel = ( { initialSnapshot = SETTINGS.adminAssetAudit || {}, onNavigate } ) => {
	const [ snapshot, setSnapshot ] = useState( initialSnapshot );
	const [ clearing, setClearing ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const screens = snapshot.screens || [];
	const repeated = snapshot.repeated || [];

	const clearAudit = async () => {
		// eslint-disable-next-line no-alert -- Removing the diagnostic snapshot requires explicit confirmation.
		if ( ! window.confirm( __( 'Clear the collected admin asset audit?', 'perform' ) ) ) {
			return;
		}
		setClearing( true );
		setMessage( { type: 'status', text: __( 'Clearing admin asset audit…', 'perform' ) } );
		try {
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: new URLSearchParams( {
					action: 'perform_clear_admin_asset_audit',
					nonce: SETTINGS.adminAssetAuditNonce || '',
				} ),
			} );
			const result = await response.json();
			if ( ! response.ok || ! result?.success || ! result.data?.snapshot ) {
				throw new Error( result?.data?.message || __( 'Admin asset audit could not be cleared.', 'perform' ) );
			}
			setSnapshot( result.data.snapshot );
			setMessage( { type: 'success', text: result.data.message } );
		} catch ( error ) {
			setMessage( {
				type: 'error',
				text: error?.message || __( 'Admin asset audit could not be cleared.', 'perform' ),
			} );
		} finally {
			setClearing( false );
		}
	};

	return (
		<div className="perform-admin-assets">
			<div className="perform-admin-assets__heading">
				<div>
					<p className="perform-dashboard-eyebrow">{ __( 'Private admin diagnostics', 'perform' ) }</p>
					<h2>{ __( 'Admin asset audit', 'perform' ) }</h2>
					<p>
						{ __(
							'See which scripts and styles recur across sampled WordPress admin screens.',
							'perform'
						) }
					</p>
				</div>
				{ snapshot.enabled ? (
					<Button
						variant="secondary"
						onClick={ clearAudit }
						disabled={ clearing || ! screens.length }
						isBusy={ clearing }
					>
						{ clearing ? __( 'Clearing…', 'perform' ) : __( 'Clear audit', 'perform' ) }
					</Button>
				) : (
					<Button variant="primary" onClick={ () => onNavigate?.( 'advanced' ) }>
						{ __( 'Enable in Advanced', 'perform' ) }
					</Button>
				) }
			</div>

			<div className="perform-admin-assets__privacy">
				<strong>{ __( 'Inventory only — nothing is disabled', 'perform' ) }</strong>
				<p>
					{ sprintf(
						/* translators: 1: screen limit, 2: asset limit, 3: retention in days. */
						__(
							'Perform stores handles and likely source labels for up to %1$s screens and %2$s assets per screen for %3$s days. URLs, query strings, nonces, and user data are not stored.',
							'perform'
						),
						snapshot.limits?.screens || 30,
						snapshot.limits?.assetsPerScreen || 100,
						snapshot.limits?.retentionDays || 7
					) }
				</p>
			</div>

			{ message && (
				<div
					className={ `perform-admin-assets__message is-${ message.type }` }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }

			{ ! snapshot.enabled && (
				<Card>
					<CardBody>
						<h3>{ __( 'Audit mode is off', 'perform' ) }</h3>
						<p>
							{ __(
								'Disabled mode registers no asset-capture hooks and writes no audit data.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }
			{ snapshot.enabled && ! screens.length && (
				<Card>
					<CardBody>
						<h3>{ __( 'Visit a few admin screens', 'perform' ) }</h3>
						<p>
							{ __(
								'Perform records the final script and style queues as you use WordPress admin normally.',
								'perform'
							) }
						</p>
					</CardBody>
				</Card>
			) }

			{ repeated.length > 0 && (
				<Card>
					<CardHeader>
						<div>
							<h3 className="perform-card-title">{ __( 'Repeated across admin screens', 'perform' ) }</h3>
							<p className="perform-card-description">
								{ __(
									'Repeated presence is a review signal, not proof that an asset is unnecessary or safe to disable.',
									'perform'
								) }
							</p>
						</div>
					</CardHeader>
					<CardBody>
						<div className="perform-admin-assets__table-wrap">
							<table>
								<thead>
									<tr>
										<th scope="col">{ __( 'Asset', 'perform' ) }</th>
										<th scope="col">{ __( 'Likely source', 'perform' ) }</th>
										<th scope="col">{ __( 'Screens', 'perform' ) }</th>
										<th scope="col">{ __( 'Confidence', 'perform' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ repeated.map( ( asset ) => (
										<tr key={ `${ asset.type }:${ asset.handle }` }>
											<th scope="row">
												<code>{ asset.handle }</code>
												<small>{ asset.type }</small>
											</th>
											<td data-label={ __( 'Likely source', 'perform' ) }>{ asset.source }</td>
											<td data-label={ __( 'Screens', 'perform' ) }>{ asset.screenCount }</td>
											<td data-label={ __( 'Confidence', 'perform' ) }>{ asset.confidence }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</CardBody>
				</Card>
			) }

			{ screens.length > 0 && (
				<div className="perform-admin-assets__screens">
					{ screens.map( ( screen ) => (
						<Card key={ screen.id }>
							<CardHeader>
								<div>
									<h3 className="perform-card-title">
										<code>{ screen.id }</code>
									</h3>
									<p className="perform-card-description">
										{ formatAssetCount( screen.assets?.length || 0 ) }
									</p>
								</div>
							</CardHeader>
							<CardBody>
								<div className="perform-admin-assets__chips">
									{ ( screen.assets || [] ).map( ( asset ) => (
										<span key={ `${ asset.type }:${ asset.handle }` }>
											<code>{ asset.handle }</code>
											<small>
												{ asset.type } · { asset.source }
											</small>
										</span>
									) ) }
								</div>
							</CardBody>
						</Card>
					) ) }
				</div>
			) }
		</div>
	);
};

export default AdminAssetAuditPanel;
