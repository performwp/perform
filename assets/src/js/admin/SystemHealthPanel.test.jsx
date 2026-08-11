/* eslint-env jest */

import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import SystemHealthPanel from './SystemHealthPanel';

const health = {
	summary: { good: 1, review: 1, actionRecommended: 1, unavailable: 0 },
	signals: [
		{
			id: 'php-version',
			label: 'PHP version',
			value: '8.3.0',
			status: 'good',
			recommendation: 'Actively tested.',
		},
		{
			id: 'memory-limit',
			label: 'WordPress memory limit',
			value: '128M',
			status: 'review',
			recommendation: 'Review memory pressure first.',
		},
		{
			id: 'uploads',
			label: 'Uploads directory',
			value: 'Needs review',
			status: 'action-recommended',
			recommendation: 'Review storage permissions.',
		},
	],
};

describe( 'SystemHealthPanel', () => {
	it( 'shows actionable local status without exposing server configuration', () => {
		render( <SystemHealthPanel health={ health } /> );

		expect( screen.getByText( '2 to review' ) ).toBeInTheDocument();
		expect( screen.getByText( 'PHP version' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Good' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Action recommended' ) ).toBeInTheDocument();
		expect( screen.getByText( /Paths, credentials, request data/ ) ).toBeInTheDocument();
	} );

	it( 'handles unavailable signal data', () => {
		render( <SystemHealthPanel health={ {} } /> );

		expect( screen.getByText( 'No actions suggested' ) ).toBeInTheDocument();
		expect( screen.getByText( 'System health signals are unavailable in this environment.' ) ).toBeInTheDocument();
	} );
} );
