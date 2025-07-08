import { TabPanel, Card, CardHeader, CardBody } from '@wordpress/components';
import { useState, useMemo } from '@wordpress/element';

const SettingsNav = () => {
  const tabs = window.performwpSettings?.tabs || {};
  const fields = window.performwpSettings?.fields || {};
  const tabKeys = Object.keys(tabs);
  const [activeTab, setActiveTab] = useState(tabKeys[0] || '');

  if (!tabKeys.length) return null;

  const tabPanelTabs = tabKeys.map((slug) => ({
    name: slug,
    title: tabs[slug],
  }));

  // Memoize cards/sections for the current tab
  const cards = useMemo(() => fields[activeTab] || [], [fields, activeTab]);

  return (
    <>
      <TabPanel
        className="perform-settings-tab-panel"
        tabs={tabPanelTabs}
        initialTabName={activeTab}
        onSelect={setActiveTab}
      >
        {() => null}
      </TabPanel>
      <div className="perform-settings-cards">
        {cards.map((card, idx) => (
          <Card key={idx} style={{ marginBottom: '24px' }}>
            <CardHeader>
              <strong>{card.title}</strong>
            </CardHeader>
            {card.description && (
              <CardBody>
                <p>{card.description}</p>
              </CardBody>
            )}
            {card.fields && card.fields.length > 0 && (
              <CardBody>
                <ul>
                  {card.fields.map((field, fidx) => (
                    <li key={fidx}>{field.name}</li>
                  ))}
                </ul>
              </CardBody>
            )}
          </Card>
        ))}
      </div>
    </>
  );
};

export default SettingsNav;
