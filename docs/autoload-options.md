# Autoload options

The Database screen shows a dated audit summary and a single paginated table of current autoloaded option metadata and applicable actions, largest first. The **Changed by Perform** view also includes options no longer autoloaded. After a successful change or undo, Perform refreshes the measured audit totals. If that refresh fails, or a write has an uncertain outcome, the previous totals are marked as potentially out of date; refresh the audit to measure again.

## Review before changing

Search option names, move between pages, or use **Changed by Perform** to see options tracked by this site's undo journal. Values are never displayed, edited, or deleted. A large option is not necessarily unused. Consult the owning plugin or theme before changing its loading policy, and check affected functionality afterward.

Management requires WordPress 6.4 or later and a verified InnoDB options table. Other database engines and older WordPress versions remain read-only. Core bootstrap/security options, transient data, theme/widget configuration, and Perform's own options are protected. Unrecognized policies are also protected. Protection is a conservative guardrail, not a guarantee that every other option is safe to change.

The labeled **Disable autoload** action applies immediately; review the option before selecting it. The reported signed byte delta is the stored option's contribution to autoloaded bytes under the current WordPress policy, not a measured page-speed improvement or a reduction in total database size. WordPress before 6.6 uses `yes`/`no`; newer versions use `on`/`off` and may also contain automatic policies.

## Undo and conflicts

Undo restores the exact original policy string, including automatic policies such as `auto`, `auto-on`, and `auto-off`. It does not replace the stored value. It is available only while the same option row, value fingerprint, and applied policy still match the recorded change. Changes by another plugin, expired review tokens, or deletion/recreation of an option require a fresh review and can make Undo unavailable. Requests are never automatically replayed after an uncertain response.

A per-site, non-autoloaded journal stores only option identifiers, original/applied policies, a value fingerprint, and change time. It holds at most 200 tracked options. A full journal rejects new tracked options rather than discarding rollback evidence. Undo removes that option's journal entry. There is no deletion, value editing, bulk mutation, or journal-purge interface.

## Safety and operational limits

- Every endpoint requires the current site's `manage_options` capability and a management nonce.
- Site-bound, expiring tokens detect stale option identities, policies, and value fingerprints.
- Row locks, conditional updates, and an InnoDB transaction keep the policy and undo record together. Failure rolls back both.
- Relevant option, negative-lookup, and aggregate autoload caches are invalidated after the transaction. No site/CDN page-cache purge is performed or required to edit the database policy itself.
- Multisite changes and journal entries apply only to the current site's options table, not network options or other sites.
- No option values are returned to the browser, stored in the journal, or sent externally. Names themselves may reveal installed extensions; access remains administrator-only.

As with core option writes, arbitrary third-party database/cache writers and unavailable cache infrastructure are outside the transaction boundary. Check the refreshed state and affected application behavior before treating a change as operationally successful. This tool is not an automatic database cleanup or performance guarantee.
