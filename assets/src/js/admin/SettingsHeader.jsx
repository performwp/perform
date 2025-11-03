import { Button } from '@wordpress/components';

const DOCS_URL = window.performwpSettings?.docsUrl || '#';
const VERSION = window.performwpSettings?.version || '';
const LOGO_URL = window.performwpSettings?.logoUrl || '';

const SettingsHeader = () => (
  <div className="perform-settings-header">
    <img src={LOGO_URL} alt="PerformWP" style={{ height: 60 }} />
    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
      <Button
        variant="tertiary"
        href={DOCS_URL}
        target="_blank"
        rel="noopener noreferrer"
        style={{
          fontWeight: 500,
          textDecoration: 'none',
          display: 'flex',
          alignItems: 'center',
          gap: 4,
        }}
      >
        View Documentation
      </Button>
      <div className="perform-plugin-version">{VERSION}</div>
    </div>
  </div>
);

export default SettingsHeader;
