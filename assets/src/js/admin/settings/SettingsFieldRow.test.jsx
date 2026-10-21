/* eslint-env jest */

import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';
import SettingsFieldRow, { normalizeOptions } from './SettingsFieldRow';

describe( 'SettingsFieldRow', () => {
	it( 'renders a shared accessible toggle row', () => {
		const onChange = jest.fn();

		render(
			<SettingsFieldRow
				field={ {
					id: 'enable_feature',
					name: 'Enable feature',
					desc: 'Turns on the feature for visitors.',
					type: 'toggle',
				} }
				value={ false }
				onChange={ onChange }
			/>
		);

		const toggle = screen.getByRole( 'checkbox', { name: 'Enable feature' } );
		expect( toggle ).toHaveAccessibleDescription( 'Turns on the feature for visitors.' );

		fireEvent.click( toggle );
		expect( onChange ).toHaveBeenCalledWith( 'enable_feature', true );
	} );

	it( 'keeps field descriptions separate from native controls', () => {
		render(
			<SettingsFieldRow
				field={ {
					id: 'service_url',
					name: 'Service URL',
					desc: 'Where Perform should send requests.',
					type: 'text',
				} }
				value="https://example.com"
				onChange={ jest.fn() }
			/>
		);

		expect( screen.getByRole( 'textbox', { name: 'Service URL' } ) ).toHaveValue( 'https://example.com' );
		expect( screen.getByText( 'Where Perform should send requests.' ) ).toBeVisible();
	} );

	it( 'normalizes associative select options', () => {
		expect( normalizeOptions( { fast: 'Fast', balanced: 'Balanced' } ) ).toEqual( [
			{ label: 'Fast', value: 'fast' },
			{ label: 'Balanced', value: 'balanced' },
		] );
	} );

	it( 'renders accessible select and textarea controls', () => {
		const onSelectChange = jest.fn();
		const { unmount } = render(
			<SettingsFieldRow
				field={ {
					id: 'cache_mode',
					name: 'Cache mode',
					desc: 'Choose how aggressively Perform caches pages.',
					type: 'select',
					options: { balanced: 'Balanced', fast: 'Fast' },
				} }
				value="balanced"
				onChange={ onSelectChange }
			/>
		);

		const select = screen.getByRole( 'combobox', { name: 'Cache mode' } );
		expect( select ).toHaveAccessibleDescription( 'Choose how aggressively Perform caches pages.' );
		fireEvent.change( select, { target: { value: 'fast' } } );
		expect( onSelectChange ).toHaveBeenCalledWith( 'cache_mode', 'fast' );
		unmount();

		const onTextareaChange = jest.fn();
		render(
			<SettingsFieldRow
				field={ {
					id: 'preconnect',
					name: 'Preconnect',
					desc: 'Enter one origin per line.',
					type: 'textarea',
				} }
				value="//example.com"
				onChange={ onTextareaChange }
			/>
		);

		const textarea = screen.getByRole( 'textbox', { name: 'Preconnect' } );
		expect( textarea ).toHaveAccessibleDescription( 'Enter one origin per line.' );
		fireEvent.change( textarea, { target: { value: '//cdn.example.com' } } );
		expect( onTextareaChange ).toHaveBeenCalledWith( 'preconnect', '//cdn.example.com' );
	} );

	it( 'keeps long translated copy readable beside a disabled control', () => {
		const translatedName =
			'Enable a deliberately long translated performance setting name without truncating its meaning';
		const translatedDescription =
			'This deliberately long translated description verifies that the shared field layout preserves the complete explanation for non-technical site owners.';

		render(
			<SettingsFieldRow
				field={ {
					id: 'translated_feature',
					name: translatedName,
					desc: translatedDescription,
					type: 'toggle',
					disabled: true,
				} }
				value
				onChange={ jest.fn() }
			/>
		);

		expect( screen.getByText( translatedName ) ).toBeVisible();
		expect( screen.getByText( translatedDescription ) ).toBeVisible();
		expect( screen.getByRole( 'checkbox', { name: translatedName } ) ).toBeDisabled();
	} );
} );
