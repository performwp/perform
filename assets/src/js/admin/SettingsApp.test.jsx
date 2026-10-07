/* eslint-env jest */
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock( './SettingsHeader', () => () => null );
jest.mock( './DiagnosticsPanel', () => () => null );
jest.mock( './SettingsNav', () => ( { fieldValues, onFieldChange, diagnostics } ) => (
	<>
		<label htmlFor="fixture-preconnect">
			Preconnect
			<input
				id="fixture-preconnect"
				value={ fieldValues.preconnect }
				onChange={ ( event ) => onFieldChange( 'preconnect', event.target.value ) }
			/>
		</label>
		<span>{ diagnostics.summary }</span>
	</>
) );

window.performwpSettings = {
	activeTab: 'assets',
	tabs: { assets: 'Assets' },
	fields: {
		assets: [
			{
				fields: [
					{ id: 'preconnect', type: 'textarea' },
					{
						id: 'page_cache_ttl',
						type: 'select',
						default: '3600',
						options: { 300: '5 minutes', 3600: '1 hour' },
					},
					{
						id: 'page_cache_swr_ttl',
						type: 'select',
						default: '21600',
						options: { 900: '15 minutes', 21600: '6 hours' },
					},
				],
			},
		],
	},
	saved: { preconnect: '//original.example.test', page_cache_swr_ttl: '' },
};
const SettingsApp = require( './SettingsApp' ).default;

const edit = ( value ) => fireEvent.change( screen.getByLabelText( 'Preconnect' ), { target: { value } } );
const save = () => screen.getByRole( 'button', { name: 'Save Settings' } );
const success = {
	success: true,
	data: { message: 'Settings saved.', diagnostics: { summary: 'Refreshed diagnostics' } },
};

describe( 'settings acknowledged baseline', () => {
	beforeEach( () => {
		window.fetch = jest.fn().mockResolvedValue( { json: async () => success } );
	} );

	it( 'disables Save only when current values match the acknowledged submission', async () => {
		render( <SettingsApp /> );
		expect( save() ).toBeDisabled();
		edit( '//submitted.example.test' );
		fireEvent.click( save() );
		await screen.findByText( 'Settings saved.' );
		expect( save() ).toBeDisabled();
		expect( screen.getByText( 'Refreshed diagnostics' ) ).toBeInTheDocument();
		const submitted = JSON.parse( window.fetch.mock.calls[ 0 ][ 1 ].body.get( 'data' ) );
		expect( submitted.page_cache_ttl ).toBe( '3600' );
		expect( submitted.page_cache_swr_ttl ).toBe( '21600' );
		edit( '//later.example.test' );
		expect( save() ).toBeEnabled();
		edit( '//submitted.example.test' );
		expect( save() ).toBeDisabled();
	} );

	it( 'retains edits made while a request is pending and submits them on the next save', async () => {
		let finish;
		window.fetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
		);
		render( <SettingsApp /> );
		edit( '//submitted.example.test' );
		fireEvent.click( save() );
		expect( screen.getByRole( 'button', { name: /Saving/ } ) ).toBeDisabled();
		edit( '//pending.example.test' );
		await act( async () => finish( { json: async () => success } ) );
		expect( save() ).toBeEnabled();
		expect( JSON.parse( window.fetch.mock.calls[ 0 ][ 1 ].body.get( 'data' ) ).preconnect ).toBe(
			'//submitted.example.test'
		);
		fireEvent.click( save() );
		await waitFor( () => expect( save() ).toBeDisabled() );
		expect( JSON.parse( window.fetch.mock.calls[ 1 ][ 1 ].body.get( 'data' ) ).preconnect ).toBe(
			'//pending.example.test'
		);
	} );

	it( 'keeps edits dirty after a rejected save and permits retry', async () => {
		window.fetch.mockResolvedValueOnce( {
			json: async () => ( { success: false, data: { message: 'Rejected save.' } } ),
		} );
		render( <SettingsApp /> );
		edit( '//rejected.example.test' );
		fireEvent.click( save() );
		await screen.findByRole( 'alert' );
		expect( save() ).toBeEnabled();
		fireEvent.click( save() );
		await screen.findByText( 'Settings saved.' );
		expect( save() ).toBeDisabled();
	} );

	it( 'keeps edits dirty after an interrupted response', async () => {
		window.fetch.mockRejectedValueOnce( new Error( 'Connection interrupted.' ) );
		render( <SettingsApp /> );
		edit( '//interrupted.example.test' );
		fireEvent.click( save() );
		await screen.findByText( 'Connection interrupted.' );
		expect( save() ).toBeEnabled();
		edit( '//original.example.test' );
		expect( save() ).toBeDisabled();
	} );
} );
