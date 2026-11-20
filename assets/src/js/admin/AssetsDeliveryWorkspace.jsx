import { __, _n, sprintf } from '@wordpress/i18n';
import { BoltIcon, CloudIcon, CodeBracketIcon } from '@heroicons/react/24/outline';
import { MetricStrip, SettingsGroup, WorkspaceHeader } from './WorkspaceLayout';

const AssetsDeliveryWorkspace = ( {
	assetSections = [],
	cdnSections = [],
	cacheSections = [],
	dashboard = {},
	fieldValues,
	onFieldChange,
} ) => {
	const assetFields = assetSections.flatMap( ( section ) => section.fields || [] );
	const cdnFields = cdnSections.flatMap( ( section ) => section.fields || [] );
	const cloudflareFields = cacheSections
		.flatMap( ( section ) => section.fields || [] )
		.filter( ( field ) =>
			[ 'enable_cloudflare_cache_sync', 'cloudflare_zone_id', 'cloudflare_api_token' ].includes( field.id )
		);
	const assets = dashboard.assetsManager || {};
	const configuredRules =
		( assets.disabledJsHandles || 0 ) + ( assets.disabledCssHandles || 0 ) + ( assets.groupDisabledRules || 0 );

	return (
		<div className="perform-workspace">
			<WorkspaceHeader
				title={ __( 'Assets & CDN', 'perform' ) }
				description={ __(
					'Choose how frontend assets load and configure content delivery for your site.',
					'perform'
				) }
				status={ sprintf(
					/* translators: %d: number of configured asset rules. */
					_n( '%d asset rule configured', '%d asset rules configured', configuredRules, 'perform' ),
					configuredRules
				) }
				statusType={ 0 < configuredRules ? 'success' : 'neutral' }
			/>
			<div className="perform-workspace-groups">
				<SettingsGroup
					title={ __( 'Frontend optimization', 'perform' ) }
					description={ __(
						'Control asset loading and prepare trusted third-party connections.',
						'perform'
					) }
					fields={ assetFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ CodeBracketIcon }
					defaultOpen
				/>
				<SettingsGroup
					title={ __( 'Content delivery', 'perform' ) }
					description={ __( 'Connect a CDN and control which public files use it.', 'perform' ) }
					fields={ cdnFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ CloudIcon }
				/>
				<SettingsGroup
					title={ __( 'Cloudflare cache sync', 'perform' ) }
					description={ __( 'Keep Cloudflare in step when Perform clears local cached pages.', 'perform' ) }
					fields={ cloudflareFields }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ BoltIcon }
				/>
			</div>
			<MetricStrip
				label={ __( 'Configured coverage', 'perform' ) }
				metrics={ [
					{ label: __( 'JavaScript rules', 'perform' ), value: assets.disabledJsHandles || 0 },
					{ label: __( 'CSS rules', 'perform' ), value: assets.disabledCssHandles || 0 },
					{ label: __( 'Group rules', 'perform' ), value: assets.groupDisabledRules || 0 },
					{ label: __( 'Page-specific exceptions', 'perform' ), value: assets.currentPageExceptions || 0 },
				] }
				note={ __( 'Configured rules only; Perform does not claim unmeasured byte savings.', 'perform' ) }
			/>
		</div>
	);
};

export default AssetsDeliveryWorkspace;
