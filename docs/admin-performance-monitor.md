# Admin Performance Monitor

The Admin Performance Monitor is an opt-in, per-site diagnostic for WordPress admin requests. Enable it under **Perform > Advanced**, use wp-admin normally, and review the aggregate report under **Perform > Admin Monitor**.

## What it measures

- request context type: admin page, AJAX, REST, or cron-adjacent;
- a bounded WordPress screen ID or safe AJAX action identifier;
- request count, average and maximum duration;
- maximum observed peak memory and query count;
- a non-identifying capability bucket.

The report highlights contexts that cross review thresholds. A threshold is evidence worth investigating, not a root-cause diagnosis or an automatic optimization recommendation.

## Privacy and overhead boundaries

- Monitoring is disabled by default. Disabled mode registers no capture hooks and writes no report data.
- Perform does not enable `SAVEQUERIES` or store query text.
- Perform does not store full URLs, query arguments, request payloads, cookies, nonces, IP addresses, usernames, or email addresses.
- Invalid, unregistered, or potentially sensitive AJAX action identifiers are grouped as `unknown-action`.
- Storage is a non-autoloaded per-site option capped at 50 aggregate contexts with 14-day retention.
- Multisite collection remains per site. No network-wide report is implemented.
- The administrator can clear the current site's aggregates from the report.

## Interpreting the report

Review a context when its worst observed duration, memory, or query count crosses the displayed threshold. Reproduce the workflow, compare repeated samples, and use a dedicated profiler when query-level attribution is required. Perform does not automatically disable plugins, remove dashboard widgets, rewrite queries, or change frontend behavior from this report.
