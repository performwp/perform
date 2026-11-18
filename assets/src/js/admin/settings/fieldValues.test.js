import { buildInitialFieldValues, normalizeFieldValue } from './fieldValues';

describe( 'settings field values', () => {
	it.each( [ true, 1, '1', 'true' ] )( 'normalizes truthy toggle value %p to true', ( value ) => {
		expect( normalizeFieldValue( { type: 'toggle' }, value ) ).toBe( true );
	} );

	it.each( [ false, 0, '0', '', null, undefined ] )( 'normalizes falsey toggle value %p to false', ( value ) => {
		expect( normalizeFieldValue( { type: 'toggle' }, value ) ).toBe( false );
	} );

	it( 'normalizes form values to the types emitted by WordPress controls', () => {
		expect( normalizeFieldValue( { type: 'select' }, 900 ) ).toBe( '900' );
		expect( normalizeFieldValue( { type: 'textarea' }, [ 'one', 'two' ] ) ).toBe( 'one\ntwo' );
		expect( normalizeFieldValue( { type: 'text' }, null ) ).toBe( '' );
	} );

	it( 'builds a stable initial snapshot from saved and default values', () => {
		const fields = {
			general: [
				{
					fields: [
						{ id: 'force_ssl', type: 'toggle', default: false },
						{ id: 'cache_ttl', type: 'select', default: 300 },
					],
				},
			],
		};

		expect( buildInitialFieldValues( fields, { force_ssl: 0, cache_ttl: 900 } ) ).toEqual( {
			force_ssl: false,
			cache_ttl: '900',
		} );
	} );
} );
