import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock( './SettingsHeader', () => ( { onTabChange } ) => (
	<button onClick={ () => onTabChange( 'inventory' ) }>Insights navigation</button>
) );
jest.mock( './DiagnosticsPanel', () => () => null );
jest.mock( './SettingsNav', () => ( { fieldValues, onFieldChange, diagnostics, insightsNavigation } ) => (
	<>
		<label htmlFor="fixture-preconnect">
			Preconnect
			<input
				id="fixture-preconnect"
				value={ fieldValues.preconnect }
				onChange={ ( event ) => onFieldChange( 'preconnect', event.target.value ) }
			/>
		</label>
		<label htmlFor="fixture-token">
			Token
			<input
				id="fixture-token"
				value={ fieldValues.cloudflare_api_token }
				onChange={ ( event ) => onFieldChange( 'cloudflare_api_token', event.target.value ) }
			/>
		</label>
		<span>{ diagnostics.summary }</span>
		<span>Insights navigation count: { insightsNavigation }</span>
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
					{ id: 'cloudflare_api_token', type: 'text' },
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
					{
						id: 'fallback_ttl',
						type: 'select',
						options: { 300: '5 minutes', 900: '15 minutes' },
					},
				],
			},
		],
	},
	saved: {
		preconnect: '//original.example.test',
		page_cache_swr_ttl: '',
		cloudflare_api_token: '__PERFORM_MASKED_SECRET__',
	},
};
const SettingsApp = require( './SettingsApp' ).default;

const edit = ( value ) =>
	fireEvent.change( screen.getByLabelText( 'Preconnect' ), {
		target: { value },
	} );
const retry = () => screen.getByRole( 'button', { name: 'Retry save' } );
const success = {
	success: true,
	data: {
		message: 'Settings saved.',
		diagnostics: { summary: 'Refreshed diagnostics' },
	},
};

describe( 'settings acknowledged baseline', () => {
	beforeEach( () => {
		window.history.replaceState( {}, '', '?tab=assets' );
		window.fetch = jest.fn().mockResolvedValue( { json: async () => success } );
	} );

	it( 'signals a reset each time top-level Insights is selected', () => {
		render( <SettingsApp /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Insights navigation' } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Insights navigation' } ) );
		expect( screen.getByText( 'Insights navigation count: 2' ) ).toBeInTheDocument();
	} );

	it( 'moves WordPress admin notices below the product header and restores them on unmount', () => {
		const wpBodyContent = document.createElement( 'div' );
		wpBodyContent.id = 'wpbody-content';
		const notice = document.createElement( 'div' );
		notice.className = 'notice notice-success';
		const secondNotice = document.createElement( 'div' );
		secondNotice.className = 'notice notice-info';
		const page = document.createElement( 'div' );
		page.id = 'perform-settings-page';
		wpBodyContent.append( notice, secondNotice, page );
		document.body.appendChild( wpBodyContent );

		const view = render( <SettingsApp />, { container: page } );
		try {
			expect( notice.parentElement ).toHaveClass( 'perform-settings-notices' );
			expect( secondNotice.parentElement ).toBe( notice.parentElement );
			view.unmount();
			expect( Array.from( wpBodyContent.children ) ).toEqual( [ notice, secondNotice, page ] );
		} finally {
			view.unmount();
			wpBodyContent.remove();
		}
	} );

	it( 'does not save on hydration and submits only the edited field', async () => {
		render( <SettingsApp /> );
		expect( window.fetch ).not.toHaveBeenCalled();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' );
		await new Promise( ( resolve ) => setTimeout( resolve, 700 ) );
		expect( window.fetch ).not.toHaveBeenCalled();
		edit( '//submitted.example.test' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Changes will save automatically' );
		await waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' ) );
		expect( screen.getByText( 'Refreshed diagnostics' ) ).toBeInTheDocument();
		const submitted = JSON.parse( window.fetch.mock.calls[ 0 ][ 1 ].body.get( 'data' ) );
		expect( submitted ).toEqual( {
			preconnect: '//submitted.example.test',
		} );
		edit( '//later.example.test' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Changes will save automatically' );
		edit( '//submitted.example.test' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' );
		await new Promise( ( resolve ) => setTimeout( resolve, 700 ) );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'debounces rapid text edits into one request with the final value', async () => {
		render( <SettingsApp /> );
		edit( '//first.example.test' );
		edit( '//second.example.test' );
		edit( '//final.example.test' );
		await waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );
		expect( JSON.parse( window.fetch.mock.calls[ 0 ][ 1 ].body.get( 'data' ) ).preconnect ).toBe(
			'//final.example.test'
		);
	} );

	it( 'serializes saves and submits the newest edits after an older response', async () => {
		let finish;
		window.fetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
		);
		render( <SettingsApp /> );
		edit( '//submitted.example.test' );
		await waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Saving settings' );
		edit( '//pending.example.test' );
		await new Promise( ( resolve ) => setTimeout( resolve, 700 ) );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		await act( async () => finish( { json: async () => success } ) );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Changes will save automatically' );
		expect( JSON.parse( window.fetch.mock.calls[ 0 ][ 1 ].body.get( 'data' ) ).preconnect ).toBe(
			'//submitted.example.test'
		);
		await waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 2 ) );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' ) );
		expect( JSON.parse( window.fetch.mock.calls[ 1 ][ 1 ].body.get( 'data' ) ).preconnect ).toBe(
			'//pending.example.test'
		);
	} );

	it( 'shows a persistent error without looping and permits retry', async () => {
		window.fetch.mockResolvedValueOnce( {
			json: async () => ( {
				success: false,
				data: { message: 'Rejected save.' },
			} ),
		} );
		render( <SettingsApp /> );
		edit( '//rejected.example.test' );
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'Rejected save.' );
		expect( screen.getByRole( 'status' ) ).toHaveAttribute( 'data-state', 'error' );
		await new Promise( ( resolve ) => setTimeout( resolve, 700 ) );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		fireEvent.click( retry() );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' ) );
		expect( window.fetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'requires explicit retry after a lost response even when the edit is reverted', async () => {
		window.fetch.mockRejectedValueOnce( new Error( 'Connection interrupted.' ) );
		render( <SettingsApp /> );
		edit( '//interrupted.example.test' );
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'may have succeeded' );
		expect( retry() ).toBeEnabled();
		edit( '//original.example.test' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Save not confirmed' );
		expect( screen.getByRole( 'status' ) ).toHaveAttribute( 'data-state', 'error' );
		const pendingEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( pendingEvent );
		expect( pendingEvent.defaultPrevented ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 700 ) );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		fireEvent.click( retry() );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' ) );
		expect( JSON.parse( window.fetch.mock.calls[ 1 ][ 1 ].body.get( 'data' ) ) ).toEqual( {
			preconnect: '//original.example.test',
		} );
		const savedEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( savedEvent );
		expect( savedEvent.defaultPrevented ).toBe( false );
	} );

	it( 'does not retain uncertainty after a confirmed rejection and revert', async () => {
		window.fetch.mockResolvedValueOnce( {
			json: async () => ( {
				success: false,
				data: { message: 'Rejected save.' },
			} ),
		} );
		render( <SettingsApp /> );
		edit( '//rejected.example.test' );
		await screen.findByRole( 'alert' );
		edit( '//original.example.test' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' );
		expect( screen.queryByRole( 'button', { name: 'Retry save' } ) ).not.toBeInTheDocument();
	} );

	it( 'keeps newer edits dirty when a retry acknowledges an earlier value', async () => {
		let finishRetry;
		window.fetch.mockRejectedValueOnce( new Error( 'Lost response' ) ).mockReturnValueOnce(
			new Promise( ( resolve ) => {
				finishRetry = resolve;
			} )
		);
		render( <SettingsApp /> );
		edit( '//first.example.test' );
		await screen.findByRole( 'alert' );
		fireEvent.click( retry() );
		edit( '//newer.example.test' );
		await act( async () => finishRetry( { json: async () => success } ) );
		await waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 3 ) );
		expect( JSON.parse( window.fetch.mock.calls[ 2 ][ 1 ].body.get( 'data' ) ) ).toEqual( {
			preconnect: '//newer.example.test',
		} );
	} );

	it( 'does not claim a masked secret retry restored an uncertain value', async () => {
		window.fetch.mockRejectedValueOnce( new Error( 'Lost response' ) );
		render( <SettingsApp /> );
		fireEvent.change( screen.getByLabelText( 'Token' ), {
			target: { value: 'replacement' },
		} );
		await screen.findByRole( 'alert' );
		fireEvent.change( screen.getByLabelText( 'Token' ), {
			target: { value: '__PERFORM_MASKED_SECRET__' },
		} );
		fireEvent.click( retry() );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'Enter the secret again' );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'keeps failure and retry visible when navigating to a report', async () => {
		window.fetch.mockResolvedValueOnce( { ok: false } );
		render( <SettingsApp /> );
		edit( '//report.example.test' );
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent( 'may have succeeded' );
		window.history.pushState( {}, '', '?tab=diagnostics' );
		act( () => window.dispatchEvent( new Event( 'popstate' ) ) );
		expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();
		expect( retry() ).toBeVisible();
	} );

	it( 'guards navigation while unsaved and releases the guard after acknowledgement', async () => {
		render( <SettingsApp /> );
		edit( '//pending.example.test' );
		const pendingEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( pendingEvent );
		expect( pendingEvent.defaultPrevented ).toBe( true );
		await waitFor( () => expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'All changes saved' ) );
		const savedEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( savedEvent );
		expect( savedEvent.defaultPrevented ).toBe( false );
	} );
} );
