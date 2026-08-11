# Admin asset audit

The Admin Asset Audit is an opt-in, local diagnostic for scripts and styles loaded on WordPress admin screens.

- It records stable screen IDs, asset handles, asset type, and a likely source label.
- It never stores asset URLs, query strings, nonces, request data, or user data.
- Collection is limited to 30 screens, 100 assets per screen, and seven days per site.
- Repeated presence is a review signal. It does not prove an asset is unnecessary or safe to disable.
- Perform does not dequeue, deregister, or otherwise modify third-party admin assets.

Enable the audit under **Perform → Advanced**, visit representative admin screens, and review **Admin Assets**. Clear the snapshot when the investigation is complete.
