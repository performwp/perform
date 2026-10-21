# Local site inventory contract

The 1.8.0 site inventory is a manually refreshed, administrator-only snapshot
that describes the shape of the current WordPress site. It is diagnostic input,
not a performance score or an optimization engine.

## Schema and lifecycle

- Option: `perform_site_inventory`, stored with autoload disabled.
- Schema version: `1`.
- Cache lifetime: 24 hours. Expired data remains visible as stale until an
  administrator refreshes it.
- Scope: current site. Multisite snapshots remain per-site and include the
  current site ID; no network-wide enumeration occurs.
- Refresh: authenticated `POST /wp-json/perform/v1/site-inventory` with the
  WordPress REST nonce and `manage_options` capability.

The snapshot contains version facts, bounded plugin identities, active theme
facts, bounded registered post-type metadata and aggregate readable counts,
front-page/posts-page configuration booleans, post-type archive availability,
reliably detected site patterns, enabled Perform module labels, signal
availability states, and collection-cost measurements.

## Cost and bounds

- Plugin identities are capped at 100 records.
- Post types are capped at 30 records and use aggregate status counts only.
- Enabled Perform modules are capped at 50 records.
- Collection runs only after explicit administrator action, never on a public
  request or every admin page load.
- The snapshot records collection duration, memory delta, and peak-memory delta
  for operational review.

## Redaction and interpretation

The inventory never stores post bodies, excerpts, raw queries, option values,
private URLs, post IDs, user/customer identities, request payloads, secrets, or
telemetry. Plugin and theme identities are visible only on the
administrator-only Perform settings screen.

Ownership hints are intentionally conservative: WordPress built-in post types
are high-confidence WordPress ownership; custom post types remain
`Custom or extension` with unknown confidence. Inventory facts must not be used
to label a plugin, theme, or content type as harmful without separate measured
evidence.

Signal states are explicit:

- `measured-locally`: collected from local WordPress APIs.
- `not-available`: the required local API or value was unavailable.
- `needs-separate-test`: lab or field performance data is outside this local
  inventory and must not be inferred.

The dashboard may consume this schema, but it must not duplicate or silently
recompute inventory state.
