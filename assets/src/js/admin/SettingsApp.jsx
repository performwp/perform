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
const SYSTEM_HEALTH = SETTINGS.systemHealth || {};
const ADMIN_PERFORMANCE = SETTINGS.adminPerformance || {};
const CRON_PRESSURE = SETTINGS.cronPressure || {};
const ADMIN_ASSET_AUDIT = SETTINGS.adminAssetAudit || {};
const PLUGIN_IMPACT = SETTINGS.pluginImpact || {};
const ACTION_SCHEDULER = SETTINGS.actionScheduler || {};
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
					if ( typeof savedVal !== 'undefined' && ! ( 'select' === f.type && '' === savedVal ) ) {
						values[ f.id ] = savedVal;
					} else if ( typeof f.default !== 'undefined' ) {
						values[ f.id ] = f.default;
					} else if ( 'select' === f.type ) {
						values[ f.id ] = Object.keys( f.options || {} )[ 0 ] || '';
					} else {
						values[ f.id ] = f.type === 'toggle' ? false : '';
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
		const submittedSnapshot = { ...fieldValues };
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
					data: JSON.stringify( submittedSnapshot ),
				} ),
			} );
			const json = await res.json();
			if ( json && json.success ) {
				// Later edits remain dirty until their own submission is acknowledged.
				setSavedSnapshot( submittedSnapshot );
				setMessage( { text: json.data?.message || 'Settings saved.', type: 'success' } );
				if ( json.data?.diagnostics ) {
					setDiagnostics( json.data.diagnostics );
				}
			} else {
				setMessage( { text: ( json && json.data && json.data.message ) || 'Save failed.', type: 'error' } );
			}
		} catch ( e ) {
			setMessage( { text: e.message || 'Save failed.', type: 'error' } );
		} finally {
			setSaving( false );
		}
	};

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
				systemHealth={ SYSTEM_HEALTH }
				adminPerformance={ ADMIN_PERFORMANCE }
				cronPressure={ CRON_PRESSURE }
				adminAssetAudit={ ADMIN_ASSET_AUDIT }
				pluginImpact={ PLUGIN_IMPACT }
				actionScheduler={ ACTION_SCHEDULER }
				activeTab={ activeTab }
				onTabChange={ handleTabChange }
				fieldValues={ fieldValues }
				onFieldChange={ handleFieldChange }
			/>
			{ 'dashboard' === activeTab && <DiagnosticsPanel diagnostics={ diagnostics } /> }
			{ ! [
				'dashboard',
				'cache-stats',
				'database',
				'inventory',
				'admin-monitor',
				'scheduled-tasks',
				'admin-assets',
				'plugin-impact',
				'action-scheduler',
			].includes( activeTab ) && (
				<Footer dirty={ isDirty } saving={ saving } message={ message } onSave={ handleSave } />
			) }
		</>
	);
};

export default SettingsApp;
