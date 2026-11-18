import { Button } from '@wordpress/components';
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
					'Choose how Perform behaves, measures activity, and keeps data on this WordPress site.',
					'perform'
				) }
				actions={
					<Button
						variant="link"
						href={ window.performwpSettings?.docsUrl || '#' }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'View documentation', 'perform' ) }
					</Button>
				}
			/>
			<div className="perform-workspace-groups">
				<SettingsGroup
					title={ __( 'Site behavior', 'perform' ) }
					description={ __(
						'Everyday behavior that affects site delivery and repeated WordPress work.',
						'perform'
					) }
					fields={ generalFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ Cog6ToothIcon }
					defaultOpen
				/>
				<SettingsGroup
					title={ __( 'Measurement and privacy', 'perform' ) }
					description={ __(
						'Temporarily collect private, aggregate evidence when the WordPress admin needs investigation.',
						'perform'
					) }
					fields={ measurementFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ LockClosedIcon }
					defaultOpen
				/>
				<SettingsGroup
					title={ __( 'Data lifecycle', 'perform' ) }
					description={ __( 'Choose what happens to Perform data when the plugin is removed.', 'perform' ) }
					fields={ lifecycleFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ CircleStackIcon }
				/>
				{ commerceAvailable && commerceFields.length > 0 && (
					<SettingsGroup
						title={ __( 'Commerce', 'perform' ) }
						description={ __( 'WooCommerce performance controls for this store.', 'perform' ) }
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
					<strong>{ __( 'Private by default', 'perform' ) }</strong>
					<p>
						{ __(
							'Local diagnostics exclude page content, private URLs, credentials, request payloads, and visitor identities.',
							'perform'
						) }
					</p>
				</div>
			</div>
		</div>
	);
};

export default PreferencesWorkspace;
