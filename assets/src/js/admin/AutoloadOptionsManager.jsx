import { Button, CheckboxControl, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import '../../css/admin/autoload-manager.css';

const SETTINGS = window.performwpSettings || {};

const AutoloadOptionsManager = ( {
	nonce = SETTINGS.autoloadManagerNonce || '',
	audit = {},
	onMutation = () => {},
} ) => {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( {
		search: '',
		page: 1,
		view: 'all',
		revision: 0,
	} );
	const [ listing, setListing ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ busyName, setBusyName ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ listError, setListError ] = useState( null );
	const alive = useRef( true );
	const mutation = useRef( null );
	const listSequence = useRef( 0 );
	const listRegion = useRef( null );
	const restoreFocus = useRef( false );

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
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
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
		let redirecting = false;
		setLoading( true );
		setListing( null );
		request( 'perform_list_autoload_options', query, controller.signal )
			.then( ( data ) => {
				if ( alive.current && sequence === listSequence.current && ! controller.signal.aborted ) {
					setListError( null );
					const maxPage = Math.max( 1, Math.ceil( ( data.total || 0 ) / ( data.pageSize || 25 ) ) );
					if ( query.page > maxPage ) {
						redirecting = true;
						setQuery( ( current ) => ( {
							...current,
							page: maxPage,
						} ) );
						return;
					}
					setListing( data );
					if ( restoreFocus.current ) {
						restoreFocus.current = false;
						setTimeout( () => listRegion.current?.focus(), 0 );
					}
				}
			} )
			.catch( ( error ) => {
				if ( alive.current && ! controller.signal.aborted ) {
					setListError( error.message );
					if ( restoreFocus.current ) {
						restoreFocus.current = false;
						setTimeout( () => listRegion.current?.focus(), 0 );
					}
				}
			} )
			.finally( () => {
				if (
					alive.current &&
					sequence === listSequence.current &&
					! controller.signal.aborted &&
					! redirecting
				) {
					setLoading( false );
				}
			} );
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ query, nonce ] );

	const reload = () =>
		setQuery( ( current ) => ( {
			...current,
			revision: current.revision + 1,
		} ) );
	const applyChange = async ( item, undo ) => {
		if ( mutation.current || ! item.token ) {
			return;
		}
		const controller = new AbortController();
		mutation.current = controller;
		setBusyName( item.name );
		setNotice( null );
		try {
			const result = await request(
				undo ? 'perform_undo_option_autoload' : 'perform_change_option_autoload',
				{
					name: item.name,
					token: item.token,
					...( undo ? {} : { autoload: 'off' } ),
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
					text: `${ result.message || __( 'Autoload setting updated.', 'perform' ) } ${ delta } ${
						! undo ? __( 'Showing matching changed options so you can review Undo.', 'perform' ) : ''
					}`,
				} );
				if ( ! undo ) {
					setSearch( item.name );
					setQuery( ( current ) => ( {
						...current,
						search: item.name,
						view: 'changed',
						page: 1,
					} ) );
				}
				onMutation( true );
			}
		} catch ( error ) {
			if ( alive.current && ! controller.signal.aborted ) {
				onMutation( false );
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
				setBusyName( '' );
				restoreFocus.current = true;
				reload(); // Reconcile, never replay an uncertain write.
			}
		}
	};

	const busy = !! busyName;
	// If management is unavailable, retain the bounded audit snapshot as read-only data.
	const fallback = ( audit.largestOptions || [] ).map( ( option ) => ( {
		...option,
		enabled: true,
	} ) );
	const items = listing?.items || ( ! nonce || ( ! loading && ! listing ) ? fallback : [] );
	const pages = Math.max( 1, Math.ceil( ( listing?.total || 0 ) / ( listing?.pageSize || 25 ) ) );
	return (
		<section className="perform-autoload-manager" aria-label={ __( 'Manage option autoload', 'perform' ) }>
			<h3>{ __( 'Options and autoload actions', 'perform' ) }</h3>
			<p>
				{ __(
					'Autoloaded options are shown largest first. Use Changed by Perform to review previous changes, including options no longer autoloaded. Values are never shown or deleted. Size alone does not prove an option is unnecessary; disabling autoload can affect plugin behavior. Check the affected plugin afterward. Undo is available only while the recorded change remains unmodified.',
					'perform'
				) }
			</p>
			{ notice && <p role={ notice.error ? 'alert' : 'status' }>{ notice.text }</p> }
			{ listError && <p role="alert">{ listError }</p> }
			{ busy && <p role="status">{ __( 'Applying the autoload change…', 'perform' ) }</p> }
			{ listing?.supported === false && (
				<p role="status">
					{ listing.reason ||
						__( 'Autoload changes require WordPress 6.4 or newer and a supported database.', 'perform' ) }
				</p>
			) }
			{ ! listing && ! loading && fallback.length > 0 && (
				<p>
					{ __(
						'Showing the last audit snapshot as read-only; this information may be out of date.',
						'perform'
					) }
				</p>
			) }
			{ nonce && (
				<form
					className="perform-autoload-manager__filters"
					onSubmit={ ( event ) => {
						event.preventDefault();
						setQuery( ( current ) => ( {
							...current,
							search: search.trim(),
							page: 1,
						} ) );
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
			) }
			{ loading ? (
				<p role="status">{ __( 'Loading option metadata…', 'perform' ) }</p>
			) : (
				<>
					<div
						className="perform-autoload-manager__scroll"
						ref={ listRegion }
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
									<th scope="col">{ __( 'Likely owner', 'perform' ) }</th>
									<th scope="col">{ __( 'Actions', 'perform' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( item ) => (
									<tr key={ item.name }>
										<th scope="row">
											<code>{ item.name }</code>
											{ item.changed && <span>{ __( 'Changed by Perform', 'perform' ) }</span> }
										</th>
										<td>
											{ sprintf(
												/* translators: %d: bytes. */ __( '%d B', 'perform' ),
												item.sizeBytes
											) }
										</td>
										<td>
											{ item.enabled ? __( 'Enabled', 'perform' ) : __( 'Disabled', 'perform' ) }
										</td>
										<td>
											{ item.ownership?.label || __( 'Unknown', 'perform' ) }{ ' ' }
											<span>
												{ item.ownership?.confidence || __( 'unknown', 'perform' ) }{ ' ' }
												{ __( 'confidence', 'perform' ) }
											</span>
										</td>
										<td>
											{ ( ! listing || listing.supported === false ) &&
												__( 'Read-only', 'perform' ) }
											{ listing?.supported !== false && listing && item.protected && (
												<span>
													{ __( 'Protected option', 'perform' ) }
													{ item.protectionReason && `: ${ item.protectionReason }` }
												</span>
											) }
											{ listing?.supported !== false &&
												listing &&
												! item.protected &&
												! item.token &&
												__( 'Action unavailable', 'perform' ) }
											{ listing?.supported !== false &&
												listing &&
												! item.protected &&
												item.token && (
													<div className="perform-autoload-manager__actions">
														{ item.enabled && (
															<Button
																variant="secondary"
																disabled={ busy }
																onClick={ () => applyChange( item, false ) }
																aria-label={ sprintf(
																	/* translators: %s: option name. */ __(
																		'Disable autoload for %s',
																		'perform'
																	),
																	item.name
																) }
															>
																{ busyName === item.name
																	? __( 'Applying…', 'perform' )
																	: __( 'Disable autoload', 'perform' ) }
															</Button>
														) }
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
																onClick={ () => applyChange( item, true ) }
																aria-label={ sprintf(
																	/* translators: %s: option name. */ __(
																		'Undo autoload change for %s',
																		'perform'
																	),
																	item.name
																) }
															>
																{ busyName === item.name
																	? __( 'Applying…', 'perform' )
																	: __( 'Undo', 'perform' ) }
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
					{ listing && (
						<nav
							className="perform-autoload-manager__pagination"
							aria-label={ __( 'Options pagination', 'perform' ) }
						>
							<Button
								disabled={ busy || query.page <= 1 }
								onClick={ () =>
									setQuery( ( current ) => ( {
										...current,
										page: current.page - 1,
									} ) )
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
									setQuery( ( current ) => ( {
										...current,
										page: current.page + 1,
									} ) )
								}
							>
								{ __( 'Next', 'perform' ) }
							</Button>
						</nav>
					) }
				</>
			) }
		</section>
	);
};
export default AutoloadOptionsManager;
