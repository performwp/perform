# Scheduled-task pressure diagnostics

Perform provides a manual, read-only WP-Cron diagnostic under **Settings → Perform → Scheduled Tasks**.

## What the check measures

- due events and events more than five minutes overdue;
- recurring scheduled instances;
- the largest five-minute cluster within the next hour;
- the most frequent hook names and aggregate instance counts;
- whether WordPress's built-in cron spawning is disabled.

The scan runs only when requested, is capped at 5,000 event instances and 20 hook rows, and stores the current site's snapshot for one hour. It never reads or stores hook arguments, request payloads, URLs, user data, or task results.

## Safety

Perform does not delete, run, or reschedule events. A frequent or overdue hook is a review signal, not proof that its extension is harmful. Compare repeated snapshots and consult the responsible extension or hosting provider before changing schedules.

Clearing the report removes only Perform's cached diagnostic snapshot. It does not change WordPress cron data.

On multisite, each site's snapshot and scheduled-event inventory remain separate. If `DISABLE_WP_CRON` is enabled, Perform can remind the administrator to verify an external runner, but it cannot confirm that runner from the WordPress request.
