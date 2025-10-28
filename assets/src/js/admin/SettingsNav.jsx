import { TabPanel, Card, CardHeader, CardBody, ToggleControl, TextControl, SelectControl } from '@wordpress/components';
import { useState, useMemo } from '@wordpress/element';

const FIELD_COMPONENTS = {
  toggle: ToggleControl,
  text: TextControl,
  select: SelectControl,
};

const normalizeOptions = (options) => {
  if (!options) return [];
  if (Array.isArray(options)) {
    if (options.length === 0) return [];
    if (typeof options[0] === 'object' && (options[0].label !== undefined || options[0].value !== undefined)) {
      return options.map((opt) => ({ label: opt.label ?? String(opt.value), value: opt.value ?? opt.label }));
    }
    return options.map((opt) => ({ label: String(opt), value: opt }));
  }
  if (typeof options === 'object') {
    return Object.keys(options).map((key) => ({ label: options[key], value: key }));
  }
  return [];
};

const renderField = (field, value, onChange) => {
  const { type = 'text', id, name, desc, options, placeholder, style: fieldStyle, className: fieldClass, ...rest } = field;
  const Component = FIELD_COMPONENTS[type] || null;
  if (!Component) return <div key={id}>Unsupported field type: {type}</div>;

  const common = {
    // key moved to wrapper
    label: name,
    help: desc,
    ...rest,
  };

  if (type === 'toggle') {
    return (
      <ToggleControl
        {...common}
        checked={!!value}
        onChange={(checked) => onChange(id, checked)}
      />
    );
  }

  if (type === 'text') {
    return (
      <TextControl
        {...common}
        value={value ?? ''}
        placeholder={placeholder}
        onChange={(val) => onChange(id, val)}
      />
    );
  }

  if (type === 'select') {
    const opts = normalizeOptions(options);
    return (
      <SelectControl
        {...common}
        className={fieldClass ?? 'perform-select-control'}
        value={value ?? (opts[0] ? opts[0].value : '')}
        options={opts}
        onChange={(val) => onChange(id, val)}
      />
    );
  }

  return <Component {...common} value={value} onChange={(val) => onChange(id, val)} />;
};

const SettingsNav = ({ tabs: propTabs, fields: propFields, activeTab: propActiveTab, onTabChange: propOnTabChange, fieldValues: propFieldValues, onFieldChange: propOnFieldChange }) => {
  const tabs = propTabs || window.performwpSettings?.tabs || {};
  const fields = propFields || window.performwpSettings?.fields || {};
  const tabKeys = Object.keys(tabs);

  const [internalActiveTab, setInternalActiveTab] = useState(tabKeys[0] || '');
  const activeTab = propActiveTab ?? internalActiveTab;
  const onTabChange = propOnTabChange ?? setInternalActiveTab;

  const [internalFieldValues, setInternalFieldValues] = useState({});
  const fieldValues = propFieldValues ?? internalFieldValues;
  const onFieldChange = propOnFieldChange ?? ((id, val) => setInternalFieldValues((p) => ({ ...p, [id]: val })));

  if (!tabKeys.length) return null;

  const tabPanelTabs = tabKeys.map((slug) => ({
    name: slug,
    title: tabs[slug],
  }));

  const cards = useMemo(() => fields[activeTab] || [], [fields, activeTab]);

  return (
    <>
      <div className="perform-settings-content">
        <TabPanel
          className="perform-settings-tab-panel"
          tabs={tabPanelTabs}
          initialTabName={activeTab}
          onSelect={onTabChange}
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
                  {card.fields.map((field) => (
                    <div key={field.id} className="perform-field" style={{ marginBottom: 16 }}>
                      {renderField(field, fieldValues[field.id], onFieldChange)}
                    </div>
                  ))}
                </CardBody>
              )}
            </Card>
          ))}
        </div>
      </div>
    </>
  );
};

export default SettingsNav;
