import { TabPanel, Card, CardHeader, CardBody, ToggleControl, TextControl } from '@wordpress/components';
import { useState, useMemo } from '@wordpress/element';

const FIELD_COMPONENTS = {
  toggle: ToggleControl,
  text: TextControl,
};

const renderField = (field, value, onChange) => {
  const { type, id, name, desc, ...rest } = field;
  const Component = FIELD_COMPONENTS[type] || null;
  if (!Component) return <div key={id}>Unsupported field type: {type}</div>;

  // Map props for each field type
  const props = {
    key: id,
    label: name,
    help: desc,
    value: value,
    onChange: (val) => onChange(id, val),
    ...rest,
  };
  if (type === 'toggle') {
    props.checked = !!value;
    delete props.value;
    props.onChange = (checked) => onChange(id, checked);
  }
  return <Component {...props} />;
};

const SettingsNav = () => {
  const tabs = window.performwpSettings?.tabs || {};
  const fields = window.performwpSettings?.fields || {};
  const tabKeys = Object.keys(tabs);
  const [activeTab, setActiveTab] = useState(tabKeys[0] || '');
  const [fieldValues, setFieldValues] = useState({});

  if (!tabKeys.length) return null;

  const tabPanelTabs = tabKeys.map((slug) => ({
    name: slug,
    title: tabs[slug],
  }));

  // Memoize cards/sections for the current tab
  const cards = useMemo(() => fields[activeTab] || [], [fields, activeTab]);

  const handleFieldChange = (id, value) => {
    setFieldValues((prev) => ({ ...prev, [id]: value }));
  };

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
            <CardHeader style={{ alignItems: 'flex-start', flexDirection: 'column' }}>
              <h3 className='perform-card-title'>{card.title}</h3>
              {card.description && (
                <p className='perform-card-description'>{card.description}</p>
              )}
            </CardHeader>
            {card.fields && card.fields.length > 0 && (
              <CardBody>
                {card.fields.map((field) =>
                  renderField(field, fieldValues[field.id], handleFieldChange)
                )}
              </CardBody>
            )}
          </Card>
        ))}
      </div>
    </>
  );
};

export default SettingsNav;
