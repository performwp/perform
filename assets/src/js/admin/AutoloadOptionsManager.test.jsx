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
const list = ( items = [ item ] ) => ( {
	supported: true,
	page: 1,
	pageSize: 25,
	total: items.length,
	items,
} );
const response = ( data, success = true ) => ( {
	ok: success,
	json: async () => ( { success, data } ),
} );

afterEach( () => {
	delete global.fetch;
	jest.restoreAllMocks();
} );
test( 'applies the labeled action directly and never displays values', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( list() ) );
	const onMutation = jest.fn();
	render( <AutoloadOptionsManager nonce="nonce" onMutation={ onMutation } /> );
	const button = await screen.findByRole( 'button', {
		name: 'Disable autoload for fixture_option',
	} );
	global.fetch.mockResolvedValueOnce( response( { message: 'Updated.', byteDelta: -2048 } ) );
	fireEvent.click( button );
	await screen.findByText( /Measured autoload byte change: -2048 B/ );
	expect( onMutation ).toHaveBeenCalledWith( true );
	expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
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
	global.fetch.mockResolvedValue(
		response( {
			...list(),
			supported: false,
			reason: 'Requires WordPress 6.4.',
		} )
	);
	render( <AutoloadOptionsManager nonce="nonce" /> );
	await screen.findByText( 'Requires WordPress 6.4.' );
	expect( screen.getByText( 'fixture_option' ) ).toBeVisible();
	expect( screen.getByText( 'Read-only' ) ).toBeVisible();
	expect( screen.queryByRole( 'button', { name: /Disable autoload for/ } ) ).not.toBeInTheDocument();
} );
test( 'does not offer a change without a token', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( list( [ { ...item, token: '' } ] ) ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	expect( await screen.findByText( 'Action unavailable' ) ).toBeVisible();
	expect( screen.queryByRole( 'button', { name: /Disable autoload for/ } ) ).not.toBeInTheDocument();
} );
test( 'announces a pending action and prevents duplicate writes', async () => {
	let finish;
	global.fetch = jest.fn().mockResolvedValue( response( list() ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	const button = await screen.findByRole( 'button', {
		name: 'Disable autoload for fixture_option',
	} );
	global.fetch.mockImplementationOnce(
		() =>
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
	);
	fireEvent.click( button );
	expect( screen.getByText( 'Applying the autoload change…' ) ).toHaveAttribute( 'role', 'status' );
	expect( button ).toBeDisabled();
	fireEvent.click( button );
	expect( global.fetch ).toHaveBeenCalledTimes( 2 );
	await act( async () => finish( response( { message: 'Updated.' } ) ) );
} );
test( 'undo uses fresh token and refreshes after a stale rejection without retrying', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( list( [ { ...item, changed: true, canUndo: true } ] ) ) );
	const onMutation = jest.fn();
	render( <AutoloadOptionsManager nonce="nonce" onMutation={ onMutation } /> );
	const undo = await screen.findByRole( 'button', {
		name: 'Undo autoload change for fixture_option',
	} );
	global.fetch.mockResolvedValueOnce( response( { message: 'Option changed elsewhere.', code: 'stale' }, false ) );
	fireEvent.click( undo );
	await screen.findByRole( 'alert' );
	expect( onMutation ).toHaveBeenCalledWith( false );
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
	fireEvent.change( screen.getByLabelText( 'Search option names' ), {
		target: { value: 'new' },
	} );
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
test( 'clears a list error after a successful retry', async () => {
	global.fetch = jest
		.fn()
		.mockResolvedValueOnce( response( { message: 'Listing failed.' }, false ) )
		.mockResolvedValue( response( list() ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Listing failed.' );
	fireEvent.click( screen.getByRole( 'button', { name: 'Refresh options' } ) );
	await screen.findByText( 'fixture_option' );
	expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
} );
test( 'refetches the last page when a refreshed count shrinks', async () => {
	global.fetch = jest.fn().mockResolvedValue( response( { ...list(), total: 30 } ) );
	render( <AutoloadOptionsManager nonce="nonce" /> );
	fireEvent.click( await screen.findByRole( 'button', { name: 'Next' } ) );
	await screen.findByText( 'Page 2 of 2' );
	global.fetch.mockResolvedValue( response( { ...list(), total: 25 } ) );
	fireEvent.click( screen.getByRole( 'button', { name: 'Refresh options' } ) );
	await screen.findByText( 'Page 1 of 1' );
	expect( global.fetch.mock.calls.slice( -2 ).map( ( call ) => call[ 1 ].body.get( 'page' ) ) ).toEqual( [
		'2',
		'1',
	] );
} );
