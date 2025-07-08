import { TabPanel } from '@wordpress/components';

const SettingsNav = ({ tabs = {}, currentTab, onTabChange }) => {
  const tabKeys = Object.keys(tabs);
  if (!tabKeys.length) return null;

  const tabPanelTabs = tabKeys.map((slug) => ({
    name: slug,
    title: tabs[slug],
  }));

  return (
	<TabPanel
	className="perform-settings-tab-panel"
	tabs={tabPanelTabs}
	initialTabName={currentTab || tabKeys[0]}
	onSelect={onTabChange}
	>
	{() => null}
	</TabPanel>
  );
};

export default SettingsNav;
