import { Button } from '@wordpress/components';
import { ArrowTopRightOnSquareIcon } from '@heroicons/react/24/outline';

const DOCS_URL = window.performwpSettings?.docsUrl || '#';
const VERSION = window.performwpSettings?.version || '';
const LOGO_URL = window.performwpSettings?.logoUrl || '';

const SettingsHeader = () => (
	<div className="perform-settings-header">
		<img src={ LOGO_URL } alt="PerformWP" className="perform-settings-header__logo" />
		<div className="perform-settings-header__actions">
			<Button
				variant="tertiary"
				href={ DOCS_URL }
				target="_blank"
				rel="noopener noreferrer"
				icon={ <ArrowTopRightOnSquareIcon className="perform-ui-icon" aria-hidden="true" /> }
				iconPosition="right"
			>
				View Documentation
			</Button>
			<div className="perform-plugin-version">{ VERSION }</div>
		</div>
	</div>
);

export default SettingsHeader;
