import SettingsHeader from './SettingsHeader';
import SettingsNav from './SettingsNav';
import Footer from './Footer';
import DiagnosticsPanel from './DiagnosticsPanel';
import { useState, useEffect, useMemo, useRef } from '@wordpress/element';

const SETTINGS = window.performwpSettings || {};
const SETTINGS_TABS = SETTINGS.tabs || {};
const SETTINGS_FIELDS = SETTINGS.fields || {};
const SAVED_SETTINGS = SETTINGS.saved || {};
const INITIAL_DIAGNOSTICS = SETTINGS.diagnostics || {};
const DASHBOARD = SETTINGS.dashboard || {};
const DATABASE_AUDIT = SETTINGS.databaseAudit || {};
const SITE_INVENTORY = SETTINGS.siteInventory || {};
const ADMIN_PERFORMANCE = SETTINGS.adminPerformance || {};
const TAB_KEYS = [ 'dashboard', ...Object.keys( SETTINGS_TABS ) ];

const normalizeTab = ( tab ) => ( TAB_KEYS.includes( tab ) ? tab : 'dashboard' );

const getTabFromUrl = () => normalizeTab( new URL( window.location.href ).searchParams.get( 'tab' ) || 'dashboard' );

const SettingsApp = () => {
	const tabs = SETTINGS_TABS;
	const fields = SETTINGS_FIELDS;
	const initialValues = useMemo( () => {
		// Build a map of field id => saved value (if present) or default value (empty string or false)
		const values = {};
		Object.keys( fields ).forEach( ( tab ) => {
			fields[ tab ].forEach( ( card ) => {
				( card.fields || [] ).forEach( ( f ) => {
					const savedVal =
						SAVED_SETTINGS && Object.prototype.hasOwnProperty.call( SAVED_SETTINGS, f.id )
							? SAVED_SETTINGS[ f.id ]
							: undefined;
					if ( typeof savedVal !== 'undefined' ) {
						values[ f.id ] = savedVal;
					} else {
						values[ f.id ] = f.default ?? ( f.type === 'toggle' ? false : '' );
					}
				} );
			} );
		} );
		return values;
	}, [ fields ] );

	const [ fieldValues, setFieldValues ] = useState( initialValues );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const [ diagnostics, setDiagnostics ] = useState( INITIAL_DIAGNOSTICS );
	const [ activeTab, setActiveTab ] = useState( () => normalizeTab( SETTINGS.activeTab ) );
	const messageTimerRef = useRef( null );

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

	// dirty detection

	const handleFieldChange = ( id, value ) => {
		setFieldValues( ( prev ) => ( { ...prev, [ id ]: value } ) );
	};

	const handleSave = async () => {
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
					data: JSON.stringify( fieldValues ),
				} ),
			} );
			const json = await res.json();
			if ( json && json.success ) {
				setMessage( { text: json.data?.message || 'Settings saved.', type: 'success' } );
				if ( json.data?.diagnostics ) {
					setDiagnostics( json.data.diagnostics );
				}
				// update initialValues snapshot
				// mutate initialValues object won't update memo, so reset by rebuild: setFieldValues equals current, but we need to reset initialValues - simplest approach: set initial snapshot to current by resetting via a state.
				// We'll set the initialValues by replacing the state used for comparison: emulate by setting all initialValues to current values via a ref - but here we'll just clear dirty by resetting initialValues via resetting fieldValues baseline.
				// For simplicity, update initialValues by assigning to window.performwpSettings._initial = fieldValues (not ideal), but we can update local initialValues via a small trick: setFieldValues to same and update a savedSnapshot state.
				// Implement savedSnapshot state instead.
			} else {
				setMessage( { text: ( json && json.data && json.data.message ) || 'Save failed.', type: 'error' } );
			}
		} catch ( e ) {
			setMessage( { text: e.message || 'Save failed.', type: 'error' } );
		} finally {
			setSaving( false );
		}
	};

	// Add a savedSnapshot state to serve as baseline for dirty calculation
	const [ savedSnapshot, setSavedSnapshot ] = useState( initialValues );

	useEffect( () => {
		// when initialValues changes (first render) set snapshot
		setSavedSnapshot( initialValues );
		setFieldValues( initialValues );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ initialValues ] );

	// recompute dirty based on savedSnapshot
	const isDirty = useMemo( () => {
		return Object.keys( fieldValues ).some( ( k ) => fieldValues[ k ] !== savedSnapshot[ k ] );
	}, [ fieldValues, savedSnapshot ] );

	// update savedSnapshot on successful save by watching message success
	useEffect( () => {
		if ( message && message.type === 'success' ) {
			setSavedSnapshot( fieldValues );
		}
	}, [ message, fieldValues ] );

	// Auto-dismiss message after 5 seconds
	useEffect( () => {
		if ( ! message || ! message.text ) {
			return;
		}
		// Clear previous timer
		if ( messageTimerRef.current ) {
			clearTimeout( messageTimerRef.current );
			messageTimerRef.current = null;
		}
		messageTimerRef.current = setTimeout( () => {
			setMessage( null );
			messageTimerRef.current = null;
		}, 5000 );

		return () => {
			if ( messageTimerRef.current ) {
				clearTimeout( messageTimerRef.current );
				messageTimerRef.current = null;
			}
		};
	}, [ message ] );

	// Clear timer on unmount
	useEffect(
		() => () => {
			if ( messageTimerRef.current ) {
				clearTimeout( messageTimerRef.current );
				messageTimerRef.current = null;
			}
		},
		[]
	);

	useEffect( () => {
		const handlePopState = () => setActiveTab( getTabFromUrl() );
		window.addEventListener( 'popstate', handlePopState );

		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	return (
		<>
			<SettingsHeader />
			<SettingsNav
				fields={ fields }
				tabs={ tabs }
				dashboard={ DASHBOARD }
				diagnostics={ diagnostics }
				databaseAudit={ DATABASE_AUDIT }
				siteInventory={ SITE_INVENTORY }
				adminPerformance={ ADMIN_PERFORMANCE }
				activeTab={ activeTab }
				onTabChange={ handleTabChange }
				fieldValues={ fieldValues }
				onFieldChange={ handleFieldChange }
			/>
			{ 'dashboard' === activeTab && <DiagnosticsPanel diagnostics={ diagnostics } /> }
			{ ! [ 'dashboard', 'cache-stats', 'database', 'inventory', 'admin-monitor' ].includes( activeTab ) && (
				<Footer dirty={ isDirty } saving={ saving } message={ message } onSave={ handleSave } />
			) }
		</>
	);
};

export default SettingsApp;
