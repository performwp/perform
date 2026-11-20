import { __ } from '@wordpress/i18n';
import { CircleStackIcon, Cog6ToothIcon, LockClosedIcon, ShoppingBagIcon } from '@heroicons/react/24/outline';
import { SettingsGroup, WorkspaceHeader } from './WorkspaceLayout';

const fieldById = ( sections, id ) =>
	sections.flatMap( ( section ) => section.fields || [] ).find( ( field ) => field.id === id );

const PreferencesWorkspace = ( {
	generalSections = [],
	advancedSections = [],
	commerceSections = [],
	commerceAvailable = false,
	fieldValues,
	onFieldChange,
} ) => {
	const generalFields = generalSections.flatMap( ( section ) => section.fields || [] );
	const measurementFields = [
		fieldById( advancedSections, 'enable_admin_performance_monitor' ),
		fieldById( advancedSections, 'enable_admin_asset_audit' ),
	].filter( Boolean );
	const lifecycleFields = [ fieldById( advancedSections, 'remove_data_on_uninstall' ) ].filter( Boolean );
	const commerceFields = commerceSections.flatMap( ( section ) => section.fields || [] );

	return (
		<div className="perform-workspace">
			<WorkspaceHeader
				title={ __( 'Settings', 'perform' ) }
				description={ __(
					'Manage site behavior, admin monitoring, and what to keep when you uninstall Perform.',
					'perform'
				) }
			/>
			<div className="perform-workspace-groups">
				<SettingsGroup
					title={ __( 'Site behavior', 'perform' ) }
					description={ __( 'Set up HTTPS redirects and navigation menu caching.', 'perform' ) }
					fields={ generalFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ Cog6ToothIcon }
					defaultOpen
				/>
				<SettingsGroup
					title={ __( 'Measurement and privacy', 'perform' ) }
					description={ __(
						'Choose whether to record admin request times and the scripts and styles loaded on admin screens.',
						'perform'
					) }
					fields={ measurementFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ LockClosedIcon }
					defaultOpen
				/>
				<SettingsGroup
					title={ __( 'When you uninstall Perform', 'perform' ) }
					description={ __( 'Choose what happens to Perform data when the plugin is removed.', 'perform' ) }
					fields={ lifecycleFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ CircleStackIcon }
				/>
				{ commerceAvailable && commerceFields.length > 0 && (
					<SettingsGroup
						title={ __( 'Commerce', 'perform' ) }
						description={ __( 'WooCommerce preferences retained for compatibility.', 'perform' ) }
						fields={ commerceFields }
						fieldValues={ fieldValues }
						onFieldChange={ onFieldChange }
						icon={ ShoppingBagIcon }
					/>
				) }
			</div>
			<div className="perform-privacy-callout">
				<LockClosedIcon aria-hidden="true" />
				<div>
					<strong>{ __( 'Local admin monitoring', 'perform' ) }</strong>
					<p>
						{ __(
							'Admin performance and asset monitoring use screen IDs and aggregate samples rather than request URLs.',
							'perform'
						) }
					</p>
				</div>
			</div>
		</div>
	);
};

export default PreferencesWorkspace;
