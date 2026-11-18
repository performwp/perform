export const normalizeFieldValue = ( field, value ) => {
	if ( 'toggle' === field.type ) {
		return true === value || 1 === value || '1' === value || 'true' === value;
	}

	if ( Array.isArray( value ) ) {
		return 'textarea' === field.type ? value.join( '\n' ) : value.map( String );
	}

	return null === value || typeof value === 'undefined' ? '' : String( value );
};

export const buildInitialFieldValues = ( fields = {}, savedSettings = {} ) => {
	const values = {};

	Object.keys( fields ).forEach( ( tab ) => {
		fields[ tab ].forEach( ( card ) => {
			( card.fields || [] ).forEach( ( field ) => {
				const hasSavedValue = Object.prototype.hasOwnProperty.call( savedSettings, field.id );
				const value = hasSavedValue
					? savedSettings[ field.id ]
					: ( field.default ?? ( 'toggle' === field.type ? false : '' ) );

				values[ field.id ] = normalizeFieldValue( field, value );
			} );
		} );
	} );

	return values;
};
