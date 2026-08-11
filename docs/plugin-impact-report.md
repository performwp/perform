# Plugin impact report

The Plugin Impact report combines two existing local evidence sources: the active-plugin Site Inventory and the opt-in Admin Asset Audit.

It reports high-confidence admin script and style ownership by plugin slug and sampled screen. It does not rank plugins, call an extension slow, or imply that an observed asset is safe to disable.

Perform intentionally reports the following as unavailable in this release:

- frontend asset ownership by request context;
- database query contribution by plugin;
- callback execution time by plugin;
- memory contribution by plugin.

Those signals require dedicated instrumentation and validation before they can support a reliable customer decision. No external benchmark or telemetry service is used.
