import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { BoltIcon, ChartBarIcon, ClockIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';
import CacheStatsPanel from './CacheStatsPanel';
import { MetricStrip, ReportBackButton, SettingsGroup, WorkspaceHeader } from './WorkspaceLayout';

const findFields = ( sections, ids ) => {
	const fieldMap = new Map(
		sections.flatMap( ( section ) => section.fields || [] ).map( ( field ) => [ field.id, field ] )
	);
	return ids.map( ( id ) => fieldMap.get( id ) ).filter( Boolean );
};

const CacheWorkspace = ( {
	sections = [],
	dashboard = {},
	fieldValues,
	onFieldChange,
	onNavigate,
	showActivity = false,
} ) => {
	if ( showActivity ) {
		return (
			<div className="perform-workspace">
				<ReportBackButton label={ __( 'Back to cache', 'perform' ) } onClick={ () => onNavigate( 'cache' ) } />
				<WorkspaceHeader
					eyebrow={ __( 'Measured locally', 'perform' ) }
					title={ __( 'Cache activity', 'perform' ) }
					description={ __(
						'Review how this site uses ready-made pages and where uncached requests need attention.',
						'perform'
					) }
				/>
				<CacheStatsPanel />
			</div>
		);
	}

	const cache = dashboard.cache || {};
	const isEnabled = Boolean( fieldValues.enable_page_cache );
	const cachedVisits = ( cache.hits || 0 ) + ( cache.staleHits || 0 );
	const recommended = findFields( sections, [
		'enable_page_cache',
		'page_cache_ttl',
		'page_cache_swr_ttl',
		'enable_cache_preload',
	] );
	const exclusions = findFields( sections, [
		'cache_separate_query_params',
		'cache_bypass_exact_paths',
		'cache_bypass_path_prefixes',
		'cache_bypass_query_params',
		'cache_bypass_cookie_names',
		'cache_bypass_cookie_prefixes',
	] );
	const observability = findFields( sections, [ 'cache_slow_request_threshold_ms' ] );

	return (
		<div className="perform-workspace">
			<WorkspaceHeader
				title={ __( 'Page cache', 'perform' ) }
				description={ __(
					'Serve ready-made pages to repeat visitors while Perform refreshes content safely in the background.',
					'perform'
				) }
				status={ isEnabled ? __( 'Active', 'perform' ) : __( 'Not active', 'perform' ) }
				statusType={ isEnabled ? 'success' : 'neutral' }
				actions={
					<Button variant="primary" onClick={ () => onNavigate( 'cache-stats' ) }>
						{ __( 'View cache activity', 'perform' ) }
					</Button>
				}
			/>
			<MetricStrip
				label={ __( 'Cache performance', 'perform' ) }
				metrics={ [
					{
						icon: ChartBarIcon,
						label: __( 'Hit rate', 'perform' ),
						value: cache.hasStats ? `${ cache.hitRatio || 0 }%` : '—',
					},
					{
						icon: BoltIcon,
						label: __( 'Cached visits', 'perform' ),
						value: cache.hasStats ? cachedVisits : '—',
					},
					{
						icon: ClockIcon,
						label: __( 'Slow uncached pages', 'perform' ),
						value: cache.hasStats ? cache.slowUncachedCount || 0 : '—',
					},
				] }
				note={
					cache.hasStats
						? __( 'Based on activity observed on this WordPress site.', 'perform' )
						: __( 'Metrics will appear after page caching records local activity.', 'perform' )
				}
			/>
			<div className="perform-workspace-groups">
				<SettingsGroup
					title={ __( 'Recommended setup', 'perform' ) }
					description={ __( 'The core controls most sites need for safe page caching.', 'perform' ) }
					fields={ recommended }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ ShieldCheckIcon }
					defaultOpen
				/>
				<SettingsGroup
					title={ __( 'Pages never cached', 'perform' ) }
					description={ __(
						'Protect checkout, account, preview, session, and other dynamic experiences.',
						'perform'
					) }
					fields={ exclusions }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ ShieldCheckIcon }
				/>
				<SettingsGroup
					title={ __( 'Performance thresholds', 'perform' ) }
					description={ __( 'Choose when an uncached request should be highlighted for review.', 'perform' ) }
					fields={ observability }
					fieldValues={ fieldValues }
					onFieldChange={ onFieldChange }
					icon={ ChartBarIcon }
				/>
			</div>
		</div>
	);
};

export { findFields };
export default CacheWorkspace;
