import SettingsHeader from './SettingsHeader';
import SettingsNav from './SettingsNav';

const SettingsApp = () => {
  const tabs = window.performwpSettings?.tabs || {};
  return (
    <>
      <SettingsHeader />
      <SettingsNav tabs={tabs} />
    </>
  );
};

export default SettingsApp;
