# Action Scheduler diagnostic

The Action Scheduler diagnostic is a manual, read-only current-site snapshot. It reports aggregate action status counts, the age of the oldest pending action, the largest hook and group counts, and action/log row counts when the corresponding tables are available.

The diagnostic never reads action arguments, payloads, or log messages. It does not run, cancel, delete, reschedule, or clean queue entries. A missing Action Scheduler table is reported as a normal unavailable state.

Results are bounded to 20 hook and group aggregates and cached for one hour. Any future cleanup workflow must be specified separately with preview, confirmation, recovery, and optimization-history contracts.
