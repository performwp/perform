import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ArrowLeftIcon, ChevronDownIcon } from '@heroicons/react/24/outline';
import SettingsFieldRow from './settings/SettingsFieldRow';

export const StatusBadge = ( { children, status = 'neutral' } ) => (
	<span className="perform-status-badge" data-status={ status }>
		{ children }
	</span>
);

export const WorkspaceHeader = ( { eyebrow, title, description, status, statusType, actions } ) => (
	<header className="perform-workspace-header">
		<div className="perform-workspace-header__copy">
			{ eyebrow && <p className="perform-workspace-eyebrow">{ eyebrow }</p> }
			<h1>{ title }</h1>
			{ description && <p>{ description }</p> }
			{ status && <StatusBadge status={ statusType }>{ status }</StatusBadge> }
		</div>
		{ actions && <div className="perform-workspace-header__actions">{ actions }</div> }
	</header>
);

export const MetricStrip = ( { label, metrics, note } ) => (
	<section className="perform-panel perform-metric-panel" aria-label={ label }>
		{ label && <h2>{ label }</h2> }
		<div className="perform-metric-strip">
			{ metrics.map( ( metric ) => {
				const Icon = metric.icon;
				return (
					<div className="perform-metric" key={ metric.label }>
						{ Icon && (
							<span className="perform-icon-badge" aria-hidden="true">
								<Icon />
							</span>
						) }
						<div>
							<span>{ metric.label }</span>
							<strong>{ metric.value }</strong>
							{ metric.description && <small>{ metric.description }</small> }
						</div>
					</div>
				);
			} ) }
		</div>
		{ note && <p className="perform-panel-note">{ note }</p> }
	</section>
);

const getEnabledCount = ( fields, values ) =>
	fields.filter( ( field ) => {
		const value = values[ field.id ];
		return 'toggle' === field.type ? Boolean( value ) : '' !== value && undefined !== value && null !== value;
	} ).length;

export const SettingsGroup = ( {
	title,
	description,
	fields = [],
	fieldValues,
	onFieldChange,
	icon: Icon,
	defaultOpen = false,
	badge,
} ) => {
	const [ isOpen, setIsOpen ] = useState( defaultOpen );

	if ( ! fields.length ) {
		return null;
	}

	const enabledCount = getEnabledCount( fields, fieldValues );

	return (
		<details
			className="perform-settings-group"
			open={ isOpen }
			onToggle={ ( event ) => setIsOpen( event.currentTarget.open ) }
		>
			<summary>
				<div className="perform-settings-group__summary-copy">
					{ Icon && (
						<span className="perform-icon-badge" aria-hidden="true">
							<Icon />
						</span>
					) }
					<div>
						<h2>{ title }</h2>
						{ description && <p>{ description }</p> }
					</div>
				</div>
				<div className="perform-settings-group__summary-meta">
					<span>
						{ badge ||
							sprintf(
								/* translators: 1: configured setting count, 2: total setting count. */
								__( '%1$d of %2$d configured', 'perform' ),
								enabledCount,
								fields.length
							) }
					</span>
					<ChevronDownIcon aria-hidden="true" />
				</div>
			</summary>
			<div className="perform-settings-group__fields">
				{ fields.map( ( field ) => (
					<SettingsFieldRow
						key={ field.id }
						field={ field }
						value={ fieldValues[ field.id ] }
						onChange={ onFieldChange }
					/>
				) ) }
			</div>
		</details>
	);
};

export const ReportBackButton = ( { onClick, label = __( 'Back to insights', 'perform' ) } ) => (
	<Button
		variant="link"
		className="perform-report-back"
		icon={ <ArrowLeftIcon aria-hidden="true" /> }
		onClick={ onClick }
	>
		{ label }
	</Button>
);
