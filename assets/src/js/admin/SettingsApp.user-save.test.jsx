import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock( './SettingsHeader', () => () => null );
jest.mock( './SettingsNav', () => ( { fieldValues, onFieldChange } ) => (
	<input
		aria-label="Feature"
		type="checkbox"
		checked={ fieldValues.feature }
		onChange={ ( event ) => onFieldChange( 'feature', event.target.checked ) }
	/>
) );

window.performwpSettings = {
	tabs: { general: 'Settings' },
	fields: { general: [ { fields: [ { id: 'feature', type: 'toggle' } ] } ] },
	saved: { feature: 0 },
};
const SettingsApp = require( './SettingsApp' ).default;

describe( 'settings save snapshot', () => {
	beforeEach( () => {
		window.history.replaceState( {}, '', '?tab=general' );
	} );

	it( 'keeps a pending save guarded when the user reverts to the initial value', async () => {
		let finish;
		global.fetch = jest.fn(
			() =>
				new Promise( ( resolve ) => {
					finish = resolve;
				} )
		);
		render( <SettingsApp /> );
		fireEvent.click( screen.getByLabelText( 'Feature' ) );
		await waitFor( () => expect( global.fetch ).toHaveBeenCalledTimes( 1 ) );
		fireEvent.click( screen.getByLabelText( 'Feature' ) );
		const event = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( event );
		expect( event.defaultPrevented ).toBe( true );
		await act( async () => {
			finish( { json: async () => ( { success: true } ) } );
		} );
		await waitFor( () => expect( global.fetch ).toHaveBeenCalledTimes( 2 ) );
		expect( JSON.parse( global.fetch.mock.calls[ 1 ][ 1 ].body.get( 'data' ) ).feature ).toBe( false );
	} );

	it( 'saves the next edit automatically after the previous success', async () => {
		global.fetch = jest.fn( async () => ( { json: async () => ( { success: true } ) } ) );
		render( <SettingsApp /> );
		fireEvent.click( screen.getByLabelText( 'Feature' ) );
		await waitFor( () => expect( global.fetch ).toHaveBeenCalledTimes( 1 ) );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' ) );
		fireEvent.click( screen.getByLabelText( 'Feature' ) );
		await waitFor( () => expect( global.fetch ).toHaveBeenCalledTimes( 2 ) );
		expect( JSON.parse( global.fetch.mock.calls[ 1 ][ 1 ].body.get( 'data' ) ).feature ).toBe( false );
	} );
} );
