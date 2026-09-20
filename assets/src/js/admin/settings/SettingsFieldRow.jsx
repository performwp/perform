import { FormToggle, SelectControl, TextareaControl, TextControl } from '@wordpress/components';
import { ArrowTopRightOnSquareIcon } from '@heroicons/react/24/outline';

const SENSITIVE_KEYS = window.performwpSettings?.sensitiveKeys || [];
const MASKED_SECRET_VALUE = window.performwpSettings?.maskedSecretValue || '__PERFORM_MASKED_SECRET__';

export const normalizeOptions = ( options ) => {
	if ( ! options ) {
		return [];
	}

	if ( Array.isArray( options ) ) {
		return options.map( ( option ) => {
			if ( 'object' === typeof option && null !== option ) {
				return {
					label: option.label ?? String( option.value ?? '' ),
					value: option.value ?? option.label ?? '',
				};
			}

			return { label: String( option ), value: option };
		} );
	}

	if ( 'object' === typeof options ) {
		return Object.keys( options ).map( ( key ) => ( {
			label: options[ key ],
			value: key,
		} ) );
	}

	return [];
};

const SettingsFieldRow = ( { field, value, onChange } ) => {
	const {
		type = 'text',
		id,
		name,
		desc,
		help_link: helpLink,
		options,
		placeholder,
		className: fieldClass,
		disabled = false,
		...rest
	} = field;
	const labelId = `${ id }-label`;
	const descriptionId = `${ id }-description`;
	const sensitive = SENSITIVE_KEYS.includes( id );
	const visibleValue = sensitive && value === MASKED_SECRET_VALUE ? '' : value ?? '';
	const controlProps = {
		...rest,
		disabled,
		id,
		label: name,
		hideLabelFromVision: true,
		'aria-describedby': descriptionId,
		__nextHasNoMarginBottom: true,
	};
	let control;

	if ( 'toggle' === type ) {
		control = (
			<FormToggle
				id={ id }
				checked={ !! value }
				disabled={ disabled }
				aria-labelledby={ labelId }
				aria-describedby={ descriptionId }
				onChange={ ( event ) => onChange( id, event.target.checked ) }
			/>
		);
	} else if ( 'select' === type ) {
		const normalizedOptions = normalizeOptions( options );
		control = (
			<SelectControl
				{ ...controlProps }
				__next40pxDefaultSize
				className={ fieldClass ?? 'perform-select-control' }
				help={ <span className="screen-reader-text">{ desc }</span> }
				options={ normalizedOptions }
				value={ value ?? normalizedOptions[ 0 ]?.value ?? '' }
				onChange={ ( nextValue ) => onChange( id, nextValue ) }
			/>
		);
	} else if ( 'textarea' === type ) {
		control = (
			<TextareaControl
				{ ...rest }
				disabled={ disabled }
				label={ name }
				hideLabelFromVision
				aria-describedby={ descriptionId }
				__nextHasNoMarginBottom
				rows={ field.rows ?? 5 }
				placeholder={ placeholder }
				value={ value ?? '' }
				onChange={ ( nextValue ) => onChange( id, nextValue ) }
			/>
		);
	} else if ( 'text' === type ) {
		control = (
			<TextControl
				{ ...controlProps }
				__next40pxDefaultSize
				type={ sensitive ? 'password' : 'text' }
				autoComplete={ sensitive ? 'new-password' : undefined }
				placeholder={ placeholder }
				value={ visibleValue }
				onChange={ ( nextValue ) => onChange( id, nextValue ) }
			/>
		);
	} else {
		control = <p className="perform-settings-field__unsupported">{ `Unsupported field type: ${ type }` }</p>;
	}

	return (
		<div className="perform-settings-field" data-control={ type }>
			<div className="perform-settings-field__copy">
				<div className="perform-settings-field__title-row">
					<h4 id={ labelId }>{ name }</h4>
					{ helpLink && (
						<a
							href={ helpLink }
							target="_blank"
							rel="noopener noreferrer"
							className="perform-settings-field__learn-more"
						>
							Learn more
							<ArrowTopRightOnSquareIcon className="perform-ui-icon" aria-hidden="true" />
						</a>
					) }
				</div>
				<p id={ descriptionId }>
					{ desc }
					{ sensitive && value === MASKED_SECRET_VALUE && (
						<span className="perform-settings-field__saved-secret">
							Existing secret is saved. Enter a new value only to replace it.
						</span>
					) }
				</p>
			</div>
			<div className="perform-settings-field__control">{ control }</div>
		</div>
	);
};

export default SettingsFieldRow;
