# System health recommendations

Perform shows a compact, read-only system health report inside **Settings → Perform → Site Inventory**. The report uses local WordPress and PHP runtime APIs; it does not make external requests or change hosting configuration.

## Signals

The report can show PHP version, WordPress memory limit, maximum execution time, OPcache, persistent object cache, database version, bounded HTTP/server hints, upload-directory writability, and debug-logging state. Each signal is marked **Good**, **Review**, **Action recommended**, or **Unavailable** with a plain-language next step.

Statuses are guidance, not a root-cause diagnosis. Hosting limits and compatibility should be reviewed before production changes.

## Privacy and multisite

- No filesystem paths, credentials, request data, log contents, private configuration values, or telemetry are collected.
- Server software is reduced to a known family name and does not expose its full version string.
- Debug-log contents and paths are never read.
- On multisite, the report describes the current site and shared runtime; it does not enumerate other sites.
