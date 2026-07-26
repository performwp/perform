import { Button, Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { ArrowDownTrayIcon, TrashIcon } from '@heroicons/react/24/outline';

const SETTINGS = window.performwpSettings?.cacheActivity || {};

const CacheStatsPanel = ( { onClearSuccess = ( redirect ) => window.location.assign( redirect ) } ) => {
	const containerRef = useRef( null );
	const [ activeAction, setActiveAction ] = useState( '' );
	const [ status, setStatus ] = useState( { text: '', type: '' } );

	useEffect( () => {
		const template = document.getElementById( 'perform-cache-stats-template' );
		const container = containerRef.current;
		if ( ! template || ! container ) {
			return undefined;
		}

		container.replaceChildren( template.content.cloneNode( true ) );

		return () => container.replaceChildren();
	}, [] );

	const exportActivity = async () => {
		if ( activeAction ) {
			return;
		}

		setActiveAction( 'export' );
		setStatus( { text: SETTINGS.exporting || 'Exporting…', type: 'progress' } );

		try {
			const response = await fetch( SETTINGS.actionUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: new URLSearchParams( {
					action: 'perform_export_cache_activity',
					_wpnonce: SETTINGS.exportNonce || '',
				} ),
			} );

			if ( ! response.ok ) {
				throw new Error( SETTINGS.exportError || 'Cache activity could not be exported.' );
			}

			const blob = await response.blob();
			const objectUrl = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = objectUrl;
			link.download = SETTINGS.downloadName || 'perform-cache-activity.csv';
			document.body.appendChild( link );
			link.click();
			link.remove();
			URL.revokeObjectURL( objectUrl );
			setStatus( {
				text: SETTINGS.exportSuccess || 'Cache activity exported.',
				type: 'success',
			} );
		} catch ( error ) {
			setStatus( {
				text: error?.message || SETTINGS.exportError || 'Cache activity could not be exported.',
				type: 'error',
			} );
		} finally {
			setActiveAction( '' );
		}
	};

	const clearActivity = async () => {
		if (
			activeAction ||
			// eslint-disable-next-line no-alert -- Destructive activity removal requires explicit confirmation.
			! window.confirm(
				SETTINGS.clearConfirmation || 'Clear all collected cache activity? This does not clear cached pages.'
			)
		) {
			return;
		}

		setActiveAction( 'clear' );
		setStatus( { text: SETTINGS.clearing || 'Clearing…', type: 'progress' } );
		let failureMessage = SETTINGS.clearError || 'Cache activity could not be cleared.';

		try {
			const response = await fetch( SETTINGS.actionUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: new URLSearchParams( {
					action: 'perform_clear_cache_activity',
					_wpnonce: SETTINGS.clearNonce || '',
					perform_async: '1',
				} ),
			} );
			let result = null;
			try {
				result = await response.json();
			} catch {
				// A nonce failure, proxy, or upstream error may return HTML.
			}

			const redirect = result?.data?.redirect;
			if ( ! response.ok || ! result?.success || 'string' !== typeof redirect || ! redirect ) {
				failureMessage = result?.data?.message || failureMessage;
				throw new Error( 'perform_clear_failed' );
			}

			setStatus( {
				text: SETTINGS.clearSuccess || 'Cache activity cleared.',
				type: 'success',
			} );
			setActiveAction( '' );
			onClearSuccess( redirect );
		} catch ( error ) {
			setStatus( {
				text: failureMessage,
				type: 'error',
			} );
			setActiveAction( '' );
		}
	};

	const isBusy = '' !== activeAction;

	return (
		<div className="perform-cache-stats-container">
			<div className="perform-cache-activity-actions">
				<div>
					<h2>{ SETTINGS.heading || 'Activity actions' }</h2>
					<p>
						{ SETTINGS.description || 'Export cache activity for review or clear the collected metrics.' }
					</p>
				</div>
				<div className="perform-cache-activity-actions__controls">
					<Button
						variant="link"
						className="perform-cache-activity-actions__export"
						icon={ 'export' === activeAction ? <Spinner /> : <ArrowDownTrayIcon aria-hidden="true" /> }
						disabled={ isBusy }
						onClick={ exportActivity }
					>
						{ 'export' === activeAction
							? SETTINGS.exporting || 'Exporting…'
							: SETTINGS.exportLabel || 'Export CSV' }
					</Button>
					<Button
						variant="secondary"
						isDestructive
						icon={ 'clear' === activeAction ? <Spinner /> : <TrashIcon aria-hidden="true" /> }
						disabled={ isBusy }
						onClick={ clearActivity }
					>
						{ 'clear' === activeAction
							? SETTINGS.clearing || 'Clearing…'
							: SETTINGS.clearLabel || 'Clear activity' }
					</Button>
				</div>
			</div>
			<div
				className="perform-cache-activity-status"
				data-status={ status.type }
				role={ 'error' === status.type ? 'alert' : 'status' }
				aria-live="polite"
			>
				{ status.text }
			</div>
			<div ref={ containerRef } />
		</div>
	);
};

export default CacheStatsPanel;
