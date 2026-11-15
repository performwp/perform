import { Button, CheckboxControl, Modal, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import '../../css/admin/autoload-manager.css';

const toggleLabel = ( item ) =>
	item.enabled
		? sprintf( /* translators: %s: option name. */ __( 'Disable autoload for %s', 'perform' ), item.name )
		: sprintf( /* translators: %s: option name. */ __( 'Enable autoload for %s', 'perform' ), item.name );

const SETTINGS = window.performwpSettings || {};

const AutoloadOptionsManager = ( { nonce = SETTINGS.autoloadManagerNonce || '' } ) => {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( { search: '', page: 1, view: 'all', revision: 0 } );
	const [ listing, setListing ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ confirmation, setConfirmation ] = useState( null );
	const alive = useRef( true );
	const mutation = useRef( null );
	const listSequence = useRef( 0 );

	useEffect( () => {
		alive.current = true;
		return () => {
			alive.current = false;
			mutation.current?.abort();
		};
	}, [] );

	const request = async ( action, data, signal ) => {
		const response = await fetch( window.ajaxurl, {
			method: 'POST',
			signal,
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams( { action, nonce, ...data } ),
		} );
		const result = await response.json();
		if ( ! response.ok || ! result?.success ) {
			throw new Error(
				result?.data?.message || __( 'The request failed. Refresh the list and try again.', 'perform' )
			);
		}
		return result.data;
	};

	useEffect( () => {
		if ( ! nonce ) {
			return;
		}
		const controller = new AbortController();
		const sequence = ++listSequence.current;
		setLoading( true );
		setListing( null ); // Do not leave actions bound to the previous query visible.
		request( 'perform_list_autoload_options', query, controller.signal )
			.then( ( data ) => {
				if ( alive.current && sequence === listSequence.current && ! controller.signal.aborted ) {
					setListing( data );
				}
			} )
			.catch( ( error ) => {
				if ( alive.current && ! controller.signal.aborted ) {
					setNotice( { error: true, text: error.message } );
				}
			} )
			.finally( () => {
				if ( alive.current && sequence === listSequence.current && ! controller.signal.aborted ) {
					setLoading( false );
				}
			} );
		return () => controller.abort();
		// The request's nonce is explicitly included in this effect's dependencies.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ query, nonce ] );

	const reload = () => setQuery( ( current ) => ( { ...current, revision: current.revision + 1 } ) );
	const confirmChange = async () => {
		if ( mutation.current || ! confirmation ) {
			return;
		}
		const controller = new AbortController();
		mutation.current = controller;
		setBusy( true );
		setNotice( null );
		try {
			const { item, undo } = confirmation;
			const result = await request(
				undo ? 'perform_undo_option_autoload' : 'perform_change_option_autoload',
				{
					name: item.name,
					token: item.token,
					...( undo ? {} : { autoload: item.enabled ? 'off' : 'on' } ),
				},
				controller.signal
			);
			if ( alive.current ) {
				const delta = Number.isFinite( result.byteDelta )
					? sprintf(
							/* translators: %d: signed byte change. */ __(
								'Measured autoload byte change: %d B.',
								'perform'
							),
							result.byteDelta
					  )
					: '';
				setNotice( {
					error: false,
					text: `${ result.message || __( 'Autoload setting updated.', 'perform' ) } ${ delta }`,
				} );
			}
		} catch ( error ) {
			if ( alive.current && ! controller.signal.aborted ) {
				setNotice( {
					error: true,
					text: `${ error.message } ${ __(
						'Refreshing the list. Review the current state before trying again.',
						'perform'
					) }`,
				} );
			}
		} finally {
			mutation.current = null;
			if ( alive.current ) {
				setBusy( false );
				setConfirmation( null );
				reload(); // Never automatically replay an uncertain or stale write.
			}
		}
	};

	if ( ! nonce ) {
		return null;
	}
	const items = listing?.items || [];
	const pages = Math.max( 1, Math.ceil( ( listing?.total || 0 ) / ( listing?.pageSize || 20 ) ) );
	return (
		<section className="perform-autoload-manager" aria-label={ __( 'Manage option autoload', 'perform' ) }>
			<h3>{ __( 'Manage option autoload', 'perform' ) }</h3>
			<p>
				{ __(
					'Change whether WordPress loads an option automatically. Values are never shown or deleted. Size alone does not prove an option is unnecessary; changes can affect plugin behavior. Measured byte changes are not a promise of faster pages.',
					'perform'
				) }
			</p>
			{ notice && <p role={ notice.error ? 'alert' : 'status' }>{ notice.text }</p> }
			{ listing?.supported === false ? (
				<p role="status">
					{ listing.reason ||
						__( 'Autoload changes require WordPress 6.4 or newer and a supported database.', 'perform' ) }
				</p>
			) : (
				<>
					<form
						className="perform-autoload-manager__filters"
						onSubmit={ ( event ) => {
							event.preventDefault();
							setQuery( ( current ) => ( { ...current, search: search.trim(), page: 1 } ) );
						} }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Search option names', 'perform' ) }
							value={ search }
							onChange={ setSearch }
							disabled={ busy }
						/>
						<Button type="submit" variant="secondary" disabled={ busy }>
							{ __( 'Search', 'perform' ) }
						</Button>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __( 'Changed by Perform', 'perform' ) }
							checked={ query.view === 'changed' }
							disabled={ busy }
							onChange={ ( checked ) =>
								setQuery( ( current ) => ( {
									...current,
									view: checked ? 'changed' : 'all',
									page: 1,
								} ) )
							}
						/>
						<Button variant="tertiary" onClick={ reload } disabled={ busy || loading }>
							{ __( 'Refresh options', 'perform' ) }
						</Button>
					</form>
					{ loading ? (
						<p role="status">{ __( 'Loading option metadata…', 'perform' ) }</p>
					) : (
						<>
							<div
								className="perform-autoload-manager__scroll"
								role="region"
								aria-label={ __( 'Option autoload settings', 'perform' ) }
								tabIndex="0"
							>
								<table>
									<thead>
										<tr>
											<th scope="col">{ __( 'Option', 'perform' ) }</th>
											<th scope="col">{ __( 'Stored size', 'perform' ) }</th>
											<th scope="col">{ __( 'Autoload', 'perform' ) }</th>
											<th scope="col">{ __( 'Actions', 'perform' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ items.map( ( item ) => (
											<tr key={ item.name }>
												<th scope="row">
													<code>{ item.name }</code>
													{ item.changed && (
														<span>{ __( 'Changed by Perform', 'perform' ) }</span>
													) }
												</th>
												<td>
													{ sprintf(
														/* translators: %d: bytes. */ __( '%d B', 'perform' ),
														item.sizeBytes
													) }
												</td>
												<td>
													{ item.enabled
														? __( 'Enabled', 'perform' )
														: __( 'Disabled', 'perform' ) }
												</td>
												<td>
													{ item.protected ? (
														<span>
															{ __( 'Protected option', 'perform' ) }
															{ item.protectionReason && `: ${ item.protectionReason }` }
														</span>
													) : (
														<div className="perform-autoload-manager__actions">
															<Button
																variant="secondary"
																disabled={ busy }
																onClick={ () =>
																	setConfirmation( { item, undo: false } )
																}
																aria-label={ toggleLabel( item ) }
															>
																{ item.enabled
																	? __( 'Disable autoload', 'perform' )
																	: __( 'Enable autoload', 'perform' ) }
															</Button>
															{ item.changed && ! item.canUndo && (
																<span>
																	{ __(
																		'Changed elsewhere; Undo unavailable',
																		'perform'
																	) }
																</span>
															) }
															{ item.canUndo && (
																<Button
																	variant="tertiary"
																	disabled={ busy }
																	onClick={ () =>
																		setConfirmation( { item, undo: true } )
																	}
																	aria-label={ sprintf(
																		/* translators: %s: option name. */ __(
																			'Undo autoload change for %s',
																			'perform'
																		),
																		item.name
																	) }
																>
																	{ __( 'Undo', 'perform' ) }
																</Button>
															) }
														</div>
													) }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
							{ ! items.length && <p>{ __( 'No matching options.', 'perform' ) }</p> }
							<nav
								className="perform-autoload-manager__pagination"
								aria-label={ __( 'Options pagination', 'perform' ) }
							>
								<Button
									disabled={ busy || query.page <= 1 }
									onClick={ () =>
										setQuery( ( current ) => ( { ...current, page: current.page - 1 } ) )
									}
								>
									{ __( 'Previous', 'perform' ) }
								</Button>
								<span>
									{ sprintf(
										/* translators: 1: current page, 2: total pages. */ __(
											'Page %1$d of %2$d',
											'perform'
										),
										query.page,
										pages
									) }
								</span>
								<Button
									disabled={ busy || query.page >= pages }
									onClick={ () =>
										setQuery( ( current ) => ( { ...current, page: current.page + 1 } ) )
									}
								>
									{ __( 'Next', 'perform' ) }
								</Button>
							</nav>
						</>
					) }
				</>
			) }
			{ confirmation && (
				<Modal
					title={
						confirmation.undo
							? __( 'Undo autoload change?', 'perform' )
							: __( 'Change option autoload?', 'perform' )
					}
					onRequestClose={ () => {
						if ( ! busy ) {
							setConfirmation( null );
						}
					} }
					isDismissible={ ! busy }
					shouldCloseOnClickOutside={ ! busy }
				>
					<p>
						<code>{ confirmation.item.name }</code>
						{ ! confirmation.undo && (
							<strong>
								{ confirmation.item.enabled
									? __( 'Disable autoload', 'perform' )
									: __( 'Enable autoload', 'perform' ) }
							</strong>
						) }
					</p>
					<p>
						{ confirmation.undo
							? __(
									'Restore the previous autoload state recorded by Perform. The stored value will not change.',
									'perform'
							  )
							: __(
									'This changes automatic loading only, not the stored value. Test the affected plugin or workflow afterward. You can undo this change if the option remains unchanged.',
									'perform'
							  ) }
					</p>
					<div className="perform-autoload-manager__actions">
						<Button variant="secondary" disabled={ busy } onClick={ () => setConfirmation( null ) }>
							{ __( 'Cancel', 'perform' ) }
						</Button>
						<Button variant="primary" disabled={ busy } isBusy={ busy } onClick={ confirmChange }>
							{ busy ? __( 'Applying…', 'perform' ) : __( 'Confirm change', 'perform' ) }
						</Button>
					</div>
				</Modal>
			) }
		</section>
	);
};
export default AutoloadOptionsManager;
