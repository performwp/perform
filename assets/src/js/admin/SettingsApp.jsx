import SettingsHeader from './SettingsHeader';
import SettingsNav from './SettingsNav';
import Footer from './Footer';
import { useState, useEffect, useLayoutEffect, useMemo, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isSettingsWorkspace } from './navigation';
import { buildInitialFieldValues } from './settings/fieldValues';

const SETTINGS = window.performwpSettings || {};
const SETTINGS_TABS = SETTINGS.tabs || {};
const SETTINGS_FIELDS = SETTINGS.fields || {};
const SAVED_SETTINGS = SETTINGS.saved || {};
const INITIAL_DIAGNOSTICS = SETTINGS.diagnostics || {};
const DASHBOARD = SETTINGS.dashboard || {};
const DATABASE_AUDIT = SETTINGS.databaseAudit || {};
const SITE_INVENTORY = SETTINGS.siteInventory || {};
const SYSTEM_HEALTH = SETTINGS.systemHealth || {};
const ADMIN_PERFORMANCE = SETTINGS.adminPerformance || {};
const CRON_PRESSURE = SETTINGS.cronPressure || {};
const ADMIN_ASSET_AUDIT = SETTINGS.adminAssetAudit || {};
const PLUGIN_IMPACT = SETTINGS.pluginImpact || {};
const ACTION_SCHEDULER = SETTINGS.actionScheduler || {};
const TAB_KEYS = [ 'dashboard', 'diagnostics', ...Object.keys( SETTINGS_TABS ) ];
const AUTOSAVE_DELAY = 600;

const normalizeTab = ( tab ) => ( TAB_KEYS.includes( tab ) ? tab : 'dashboard' );

const getTabFromUrl = () => normalizeTab( new URL( window.location.href ).searchParams.get( 'tab' ) || 'dashboard' );

const SettingsApp = () => {
	const tabs = SETTINGS_TABS;
	const fields = SETTINGS_FIELDS;
	const initialValues = useMemo( () => {
		const values = buildInitialFieldValues( fields, SAVED_SETTINGS );
		Object.values( fields ).forEach( ( cards ) => {
			cards.forEach( ( card ) => {
				( card.fields || [] ).forEach( ( field ) => {
					if ( 'select' !== field.type ) {
						return;
					}
					const saved = SAVED_SETTINGS[ field.id ];
					if ( '' === saved || ( typeof saved === 'undefined' && typeof field.default === 'undefined' ) ) {
						values[ field.id ] = String( field.default ?? Object.keys( field.options || {} )[ 0 ] ?? '' );
					}
				} );
			} );
		} );
		return values;
	}, [ fields ] );

	const [ fieldValues, setFieldValues ] = useState( initialValues );
	const [ savedSnapshot, setSavedSnapshot ] = useState( initialValues );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const [ uncertainKeys, setUncertainKeys ] = useState( [] );
	const [ diagnostics, setDiagnostics ] = useState( INITIAL_DIAGNOSTICS );
	const [ activeTab, setActiveTab ] = useState( getTabFromUrl );
	const [ insightsNavigation, setInsightsNavigation ] = useState( 0 );
	const noticesRef = useRef( null );
	const inFlightRef = useRef( false );

	useLayoutEffect( () => {
		const page = document.getElementById( 'perform-settings-page' );
		const noticeSlot = noticesRef.current;
		if ( ! page || ! noticeSlot ) {
			return undefined;
		}

		const movedNotices = [];
		document
			.querySelectorAll( '#wpbody-content .notice, #wpbody-content > .updated, #wpbody-content > .error' )
			.forEach( ( notice ) => {
				if ( page.contains( notice ) ) {
					return;
				}
				movedNotices.push( {
					notice,
					parent: notice.parentNode,
					nextSibling: notice.nextSibling,
				} );
				noticeSlot.appendChild( notice );
			} );

		return () => {
			movedNotices.reverse().forEach( ( { notice, parent, nextSibling } ) => {
				if ( parent ) {
					parent.insertBefore( notice, nextSibling?.parentNode === parent ? nextSibling : null );
				}
			} );
		};
	}, [] );

	const handleTabChange = ( tab ) => {
		const nextTab = normalizeTab( tab );
		setActiveTab( nextTab );

		const url = new URL( window.location.href );
		if ( 'dashboard' === nextTab ) {
			url.searchParams.delete( 'tab' );
		} else {
			url.searchParams.set( 'tab', nextTab );
		}
		window.history.pushState( { performTab: nextTab }, '', url );
	};

	const handleFieldChange = ( id, value ) => {
		setFieldValues( ( prev ) => ( prev[ id ] === value ? prev : { ...prev, [ id ]: value } ) );
		// An ambiguous response requires an explicit retry, even after another edit.
		if ( ! uncertainKeys.length ) {
			setMessage( null );
		}
	};

	const handleSave = useCallback( async () => {
		if ( inFlightRef.current ) {
			return;
		}
		const submittedSnapshot = { ...fieldValues };
		const maskedSecret = SETTINGS.maskedSecretValue || '__PERFORM_MASKED_SECRET__';
		if ( uncertainKeys.some( ( key ) => submittedSnapshot[ key ] === maskedSecret ) ) {
			setMessage( {
				type: 'error',
				text: __( 'The previous save may have succeeded. Enter the secret again and retry.', 'perform' ),
			} );
			return;
		}
		// Submit only edits: rendering a default must not persist it or rewrite unrelated legacy values.
		const changedValues = Object.fromEntries(
			Object.entries( submittedSnapshot ).filter(
				( [ key, value ] ) => value !== savedSnapshot[ key ] || uncertainKeys.includes( key )
			)
		);
		if ( ! Object.keys( changedValues ).length ) {
			return;
		}
		inFlightRef.current = true;
		setSaving( true );
		setMessage( null );
		try {
			const res = await fetch( window.ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: new URLSearchParams( {
					action: 'perform_save_settings',
					nonce: SETTINGS.nonce || '',
					data: JSON.stringify( changedValues ),
				} ),
			} );
			if ( res.ok === false ) {
				throw new Error( __( 'Save failed. Please try again.', 'perform' ) );
			}
			const json = await res.json();
			if ( json?.success === true ) {
				// Later edits remain dirty until their own submission is acknowledged.
				setSavedSnapshot( ( previous ) => ( {
					...previous,
					...changedValues,
				} ) );
				setUncertainKeys( ( previous ) =>
					previous.filter( ( key ) => ! Object.prototype.hasOwnProperty.call( changedValues, key ) )
				);
				setMessage( {
					text: json.data?.message || __( 'Settings saved.', 'perform' ),
					type: 'success',
				} );
				if ( json.data?.diagnostics ) {
					setDiagnostics( json.data.diagnostics );
				}
			} else if ( json?.success === false ) {
				setMessage( {
					text: ( json && json.data && json.data.message ) || __( 'Save failed.', 'perform' ),
					type: 'error',
				} );
			} else {
				throw new Error( __( 'The save response could not be confirmed. Please retry.', 'perform' ) );
			}
		} catch {
			setUncertainKeys( ( previous ) => [ ...new Set( [ ...previous, ...Object.keys( changedValues ) ] ) ] );
			setMessage( {
				text: __( 'The save could not be confirmed and may have succeeded. Please retry.', 'perform' ),
				type: 'error',
			} );
		} finally {
			inFlightRef.current = false;
			setSaving( false );
		}
	}, [ fieldValues, savedSnapshot, uncertainKeys ] );

	const isDirty = useMemo( () => {
		return Object.keys( fieldValues ).some( ( k ) => fieldValues[ k ] !== savedSnapshot[ k ] );
	}, [ fieldValues, savedSnapshot ] );

	useEffect( () => {
		if ( ! isDirty || saving || uncertainKeys.length || 'error' === message?.type ) {
			return undefined;
		}
		const timer = setTimeout( handleSave, AUTOSAVE_DELAY );
		return () => clearTimeout( timer );
	}, [ handleSave, isDirty, saving, message, uncertainKeys ] );

	useEffect( () => {
		if ( ! isDirty && ! saving && ! uncertainKeys.length ) {
			return undefined;
		}
		const warnBeforeUnload = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warnBeforeUnload );
		return () => window.removeEventListener( 'beforeunload', warnBeforeUnload );
	}, [ isDirty, saving, uncertainKeys ] );

	useEffect( () => {
		const handlePopState = () => setActiveTab( getTabFromUrl() );
		window.addEventListener( 'popstate', handlePopState );

		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	return (
		<>
			<SettingsHeader
				activeTab={ activeTab }
				onTabChange={ ( tab ) => {
					if ( 'inventory' === tab ) {
						setInsightsNavigation( ( previous ) => previous + 1 );
					}
					handleTabChange( tab );
				} }
			/>
			<div ref={ noticesRef } className="perform-settings-notices" />
			<SettingsNav
				fields={ fields }
				tabs={ tabs }
				dashboard={ DASHBOARD }
				diagnostics={ diagnostics }
				databaseAudit={ DATABASE_AUDIT }
				siteInventory={ SITE_INVENTORY }
				systemHealth={ SYSTEM_HEALTH }
				adminPerformance={ ADMIN_PERFORMANCE }
				cronPressure={ CRON_PRESSURE }
				adminAssetAudit={ ADMIN_ASSET_AUDIT }
				pluginImpact={ PLUGIN_IMPACT }
				actionScheduler={ ACTION_SCHEDULER }
				activeTab={ activeTab }
				insightsNavigation={ insightsNavigation }
				onTabChange={ handleTabChange }
				fieldValues={ fieldValues }
				onFieldChange={ handleFieldChange }
			/>
			{ ( ( isSettingsWorkspace( activeTab ) && 'cache-stats' !== activeTab ) ||
				isDirty ||
				saving ||
				'error' === message?.type ) && (
				<Footer
					dirty={ isDirty || 0 < uncertainKeys.length }
					saving={ saving }
					uncertain={ 0 < uncertainKeys.length }
					message={ message }
					onRetry={ handleSave }
				/>
			) }
		</>
	);
};

export default SettingsApp;
