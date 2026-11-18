import '@testing-library/jest-dom';
import { fireEvent, render, screen, within } from '@testing-library/react';

window.performwpSettings = {
	docsUrl: 'https://docs.example.test/perform',
	logoUrl: 'https://example.test/perform.svg',
	version: '1.8.0',
};

if ( ! window.HTMLElement.prototype.scrollIntoView ) {
	window.HTMLElement.prototype.scrollIntoView = jest.fn();
}

const SettingsHeader = require( './SettingsHeader' ).default;

describe( 'settings header navigation', () => {
	it( 'uses links in a semantic list and marks the current workspace', () => {
		render( <SettingsHeader activeTab="dashboard" onTabChange={ jest.fn() } /> );

		const navigation = screen.getByRole( 'navigation', { name: 'Perform settings' } );
		const list = within( navigation ).getByRole( 'list' );

		expect( within( list ).getAllByRole( 'listitem' ) ).toHaveLength( 6 );
		expect( within( navigation ).getByRole( 'link', { name: 'Overview' } ) ).toHaveAttribute(
			'aria-current',
			'page'
		);
		expect( within( navigation ).getByRole( 'link', { name: 'Cache' } ) ).toHaveAttribute(
			'href',
			expect.stringContaining( 'tab=cache' )
		);
	} );

	it( 'keeps ordinary navigation in-app and preserves modified link clicks', () => {
		const onTabChange = jest.fn();
		render( <SettingsHeader activeTab="dashboard" onTabChange={ onTabChange } /> );

		const cacheLink = screen.getByRole( 'link', { name: 'Cache' } );
		const ordinaryClick = fireEvent.click( cacheLink );
		expect( ordinaryClick ).toBe( false );
		expect( onTabChange ).toHaveBeenCalledWith( 'cache' );

		onTabChange.mockClear();
		const modifiedClick = fireEvent.click( cacheLink, { ctrlKey: true } );
		expect( modifiedClick ).toBe( true );
		expect( onTabChange ).not.toHaveBeenCalled();
	} );
} );
