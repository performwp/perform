import {
	TabPanel,
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	TextControl,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import DashboardPanel from './DashboardPanel';

const FIELD_COMPONENTS = {
	toggle: ToggleControl,
	text: TextControl,
	textarea: TextareaControl,
	select: SelectControl,
};

const normalizeOptions = ( options ) => {
	if ( ! options ) {
		return [];
	}
	if ( Array.isArray( options ) ) {
		if ( options.length === 0 ) {
			return [];
		}
		if (
			typeof options[ 0 ] === 'object' &&
			( options[ 0 ].label !== undefined || options[ 0 ].value !== undefined )
		) {
			return options.map( ( opt ) => ( {
				label: opt.label ?? String( opt.value ),
				value: opt.value ?? opt.label,
			} ) );
		}
		return options.map( ( opt ) => ( { label: String( opt ), value: opt } ) );
	}
	if ( typeof options === 'object' ) {
		return Object.keys( options ).map( ( key ) => ( { label: options[ key ], value: key } ) );
	}
	return [];
};

const SENSITIVE_KEYS = window.performwpSettings?.sensitiveKeys || [];
const MASKED_SECRET_VALUE = window.performwpSettings?.maskedSecretValue || '__PERFORM_MASKED_SECRET__';
const SETTINGS = window.performwpSettings || {};

const isSensitiveField = ( fieldId ) => SENSITIVE_KEYS.includes( fieldId );

const CacheStatsPanel = () => {
	const containerRef = useRef( null );

	useEffect( () => {
		const template = document.getElementById( 'perform-cache-stats-template' );
		const container = containerRef.current;
		if ( ! template || ! container ) {
			return undefined;
		}

		container.replaceChildren( template.content.cloneNode( true ) );

		return () => container.replaceChildren();
	}, [] );

	return <div ref={ containerRef } className="perform-cache-stats-container" />;
};

const renderField = ( field, value, onChange ) => {
	const {
		type = 'text',
		id,
		name,
		desc,
		help_link: helpLink,
		options,
		placeholder,
		style: fieldStyle,
		className: fieldClass,
		...rest
	} = field;
	const Component = FIELD_COMPONENTS[ type ] || null;
	if ( ! Component ) {
		return <div key={ id }>Unsupported field type: { type }</div>;
	}

	const common = {
		// key moved to wrapper
		label: name,
		// Render description with optional "Learn more" link when provided
		help: (
			<span>
				{ desc }
				{ helpLink && (
					<>
						{ ' ' }
						<a href={ helpLink } target="_blank" rel="noopener noreferrer" className="perform-help-link">
							Learn more <span className="perform-help-icon">→</span>
						</a>
					</>
				) }
			</span>
		),
		...rest,
	};

	if ( type === 'toggle' ) {
		return <ToggleControl { ...common } checked={ !! value } onChange={ ( checked ) => onChange( id, checked ) } />;
	}

	if ( type === 'text' ) {
		const sensitive = isSensitiveField( id );
		const shownValue = sensitive && value === MASKED_SECRET_VALUE ? '' : value ?? '';
		const enhancedHelp = sensitive ? (
			<span>
				{ desc }{ ' ' }
				<em>
					{ value === MASKED_SECRET_VALUE
						? 'Existing secret is saved. Enter a new value only if you want to replace it.'
						: '' }
				</em>
				{ helpLink && (
					<>
						{ ' ' }
						<a href={ helpLink } target="_blank" rel="noopener noreferrer" className="perform-help-link">
							Learn more <span className="perform-help-icon">→</span>
						</a>
					</>
				) }
			</span>
		) : (
			common.help
		);

		return (
			<TextControl
				{ ...common }
				type={ sensitive ? 'password' : 'text' }
				autoComplete={ sensitive ? 'new-password' : undefined }
				help={ enhancedHelp }
				value={ shownValue }
				placeholder={ placeholder }
				onChange={ ( val ) => onChange( id, val ) }
			/>
		);
	}

	if ( type === 'textarea' ) {
		// allow optional rows property on the field definition
		const rows = field.rows ?? 5;
		return (
			<TextareaControl
				{ ...common }
				value={ value ?? '' }
				placeholder={ placeholder }
				rows={ rows }
				onChange={ ( val ) => onChange( id, val ) }
			/>
		);
	}

	if ( type === 'select' ) {
		const opts = normalizeOptions( options );
		return (
			<SelectControl
				{ ...common }
				className={ fieldClass ?? 'perform-select-control' }
				value={ value ?? ( opts[ 0 ] ? opts[ 0 ].value : '' ) }
				options={ opts }
				onChange={ ( val ) => onChange( id, val ) }
			/>
		);
	}

	return <Component { ...common } value={ value } onChange={ ( val ) => onChange( id, val ) } />;
};

const SettingsNav = ( {
	tabs: propTabs,
	fields: propFields,
	dashboard,
	diagnostics,
	activeTab: propActiveTab,
	onTabChange: propOnTabChange,
	fieldValues: propFieldValues,
	onFieldChange: propOnFieldChange,
} ) => {
	const tabs = useMemo( () => propTabs || SETTINGS.tabs || {}, [ propTabs ] );
	const fields = useMemo( () => propFields || SETTINGS.fields || {}, [ propFields ] );
	const tabKeys = useMemo( () => [ 'dashboard', ...Object.keys( tabs ) ], [ tabs ] );

	const [ internalActiveTab, setInternalActiveTab ] = useState( tabKeys[ 0 ] || '' );
	const activeTab = propActiveTab ?? internalActiveTab;
	const onTabChange = propOnTabChange ?? setInternalActiveTab;

	const [ internalFieldValues, setInternalFieldValues ] = useState( {} );
	const fieldValues = propFieldValues ?? internalFieldValues;
	const onFieldChange =
		propOnFieldChange ?? ( ( id, val ) => setInternalFieldValues( ( p ) => ( { ...p, [ id ]: val } ) ) );
	const tabPanelRef = useRef( null );

	useEffect( () => {
		const selectedTab = tabPanelRef.current?.querySelector( '[role="tab"][aria-selected="true"]' );
		selectedTab?.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
	}, [ activeTab ] );

	const tabPanelTabs = useMemo(
		() =>
			tabKeys.map( ( slug ) => ( {
				name: slug,
				title: 'dashboard' === slug ? 'Dashboard' : tabs[ slug ],
			} ) ),
		[ tabKeys, tabs ]
	);

	if ( ! tabKeys.length ) {
		return null;
	}

	return (
		<div ref={ tabPanelRef }>
			<TabPanel
				key={ activeTab }
				className="perform-settings-tab-panel"
				tabs={ tabPanelTabs }
				initialTabName={ activeTab }
				onSelect={ ( tabName ) => {
					if ( tabName !== activeTab ) {
						onTabChange( tabName );
					}
				} }
			>
				{ ( selectedTab ) => {
					const selectedTabName = selectedTab?.name || activeTab;
					const cards = fields[ selectedTabName ] || [];
					let content = (
						<div className="perform-settings-cards">
							{ cards.map( ( card, idx ) => (
								<Card
									key={ idx }
									style={ {
										marginBottom: '24px',
										boxShadow: '0 1px 2px rgba(0, 0, 0, 0.1)',
										borderRadius: 0,
									} }
								>
									<CardHeader style={ { alignItems: 'flex-start', flexDirection: 'column' } }>
										<h3 className="perform-card-title">{ card.title }</h3>
										{ card.description && (
											<p className="perform-card-description">{ card.description }</p>
										) }
									</CardHeader>
									{ card.fields && card.fields.length > 0 && (
										<CardBody>
											{ card.fields.map( ( field ) => (
												<div
													key={ field.id }
													className="perform-field"
													style={ { marginBottom: 16 } }
												>
													{ renderField( field, fieldValues[ field.id ], onFieldChange ) }
												</div>
											) ) }
										</CardBody>
									) }
								</Card>
							) ) }
						</div>
					);

					if ( 'dashboard' === selectedTabName ) {
						content = <DashboardPanel dashboard={ dashboard } diagnostics={ diagnostics } />;
					} else if ( 'cache-stats' === selectedTabName ) {
						content = <CacheStatsPanel />;
					}

					return <div className="perform-settings-content">{ content }</div>;
				} }
			</TabPanel>
		</div>
	);
};

export default SettingsNav;
