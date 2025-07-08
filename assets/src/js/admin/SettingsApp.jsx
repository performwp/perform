import { Panel, PanelBody, Button } from '@wordpress/components';
import { createElement } from '@wordpress/element';

const SettingsApp = () => (
  <Panel header="Perform Settings" className="perform-settings-panel">
    <PanelBody title="General" initialOpen={true}>
      <p>This is the WordPress-powered settings app. Add your fields here.</p>
      <Button isPrimary>Save Settings</Button>
    </PanelBody>
  </Panel>
);

export default SettingsApp;
