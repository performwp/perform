/* eslint-env jest */
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import AutoloadOptionsManager from './AutoloadOptionsManager';
const item = {
	name: 'fixture_option',
	sizeBytes: 2048,
	enabled: true,
	token: 'fresh-token',
	changed: false,
	canUndo: false,
	protected: false,
};
const list = ( items = [ item ] ) => ( { supported: true, page: 1, pageSize: 25, total: items.length, items } );
const response = ( data, success = true ) => ( { ok: success, json: async () => ( { success, data } ) } );

afterEach( () => {
	delete global.fetch;
	jest.restoreAllMocks();
} );
test( 'requires explicit confirmation and never displays values', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( list() ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	fireEvent.click( await screen.findByRole( 'button', { name: 'Disable autoload for fixture_option' } ) );
	expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	expect( screen.getByRole( 'dialog' ) ).toBeVisible();
	fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
	expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	fireEvent.click( screen.getByRole( 'button', { name: 'Disable autoload for fixture_option' } ) );
	global.fetch.mockResolvedValueOnce( response( { message: 'Updated.', byteDelta: -2048 } ) );
	fireEvent.click( screen.getByRole( 'button', { name: 'Confirm change' } ) );
	await screen.findByText( /Measured autoload byte change: -2048 B/ );
	const body = global.fetch.mock.calls[ 1 ][ 1 ].body;
	expect( body.get( 'token' ) ).toBe( 'fresh-token' );
	expect( body.get( 'autoload' ) ).toBe( 'off' );
	expect( body.get( 'action' ) ).toBe( 'perform_change_option_autoload' );
} );
test( 'protects critical options and unsupported installations', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( list( [ { ...item, protected: true } ] ) ) );
	const { unmount } = render( <AutoloadOptionsManager nonce="nonce" /> );
	await screen.findByText( 'Protected option' );
	expect( screen.queryByRole( 'button', { name: /Disable autoload for/ } ) ).not.toBeInTheDocument();
	unmount();
	global.fetch.mockResolvedValue( response( { supported: false, reason: 'Requires WordPress 6.4.' } ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	await screen.findByText( 'Requires WordPress 6.4.' );
	expect( screen.queryByRole( 'button', { name: 'Search' } ) ).not.toBeInTheDocument();
} );
test( 'undo confirms, uses fresh token and refreshes after a stale rejection without retrying', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( list( [ { ...item, changed: true, canUndo: true } ] ) ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	fireEvent.click( await screen.findByRole( 'button', { name: 'Undo autoload change for fixture_option' } ) );
	global.fetch.mockResolvedValueOnce( response( { message: 'Option changed elsewhere.', code: 'stale' }, false ) );
	fireEvent.click( screen.getByRole( 'button', { name: 'Confirm change' } ) );
	await screen.findByRole( 'alert' );
	await waitFor( () => expect( global.fetch ).toHaveBeenCalledTimes( 3 ) );
	expect( global.fetch.mock.calls[ 1 ][ 1 ].body.get( 'action' ) ).toBe( 'perform_undo_option_autoload' );
	expect( global.fetch.mock.calls[ 2 ][ 1 ].body.get( 'action' ) ).toBe( 'perform_list_autoload_options' );
} );
test( 'ignores stale list responses and aborts pending requests on unmount', async () => {
	let finish;
	global.fetch = jest
		.fn()
		.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					finish = resolve;
				} )
		)
		.mockResolvedValue( response( list( [ { ...item, name: 'new_option' } ] ) ) );
	const { unmount } = render( <AutoloadOptionsManager nonce="nonce" /> );
	fireEvent.change( screen.getByLabelText( 'Search option names' ), { target: { value: 'new' } } );
	fireEvent.click( screen.getByRole( 'button', { name: 'Search' } ) );
	await screen.findByText( 'new_option' );
	await act( async () => finish( response( list() ) ) );
	expect( screen.queryByText( 'fixture_option' ) ).not.toBeInTheDocument();
	expect( global.fetch.mock.calls[ 0 ][ 1 ].signal.aborted ).toBe( true );
	unmount();
	expect( global.fetch.mock.calls[ 1 ][ 1 ].signal.aborted ).toBe( true );
} );
test( 'search and Changed by Perform reset pagination', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( { ...list(), total: 60 } ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	fireEvent.click( await screen.findByRole( 'button', { name: 'Next' } ) );
	await waitFor( () => expect( global.fetch.mock.calls[ 1 ][ 1 ].body.get( 'page' ) ).toBe( '2' ) );
	fireEvent.click( screen.getByLabelText( 'Changed by Perform' ) );
	await waitFor( () => expect( global.fetch.mock.calls[ 2 ][ 1 ].body.get( 'view' ) ).toBe( 'changed' ) );
	expect( global.fetch.mock.calls[ 2 ][ 1 ].body.get( 'page' ) ).toBe( '1' );
} );
