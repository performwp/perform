# AGENTS.md

Guidance for AI coding agents working on the Perform WordPress plugin.

## Project Snapshot
- Plugin: `Perform`
- Stack:
  - PHP (WordPress plugin, PSR-4 autoload `Perform\\` -> `src/`)
  - JS/CSS via `@wordpress/scripts` + webpack output in `assets/dist`
  - Freemius SDK via Composer

## Branch And Release Workflow
- `develop` is the default integration branch for workflow-policy and documentation changes unless current release evidence proves a different base.
- Release-scoped work uses `release/<milestone>` branches. Verify the live branch list each run instead of hardcoding a single active release branch here.
- If an issue or milestone maps to an existing `release/<milestone>` branch, create the work branch from that release branch and set the PR base explicitly.
- Verify the current public version and release line from live repo metadata such as `readme.txt`, GitHub releases, and WordPress.org signals rather than relying on a static value in this file.
- Release-readiness checks are project-specific: evaluate minor releases on a 30-day cadence and patch releases on a 7-day cadence within the same minor line.
- Treat `x.9.x` as the pre-major rollover boundary: if that line is exhausted, the next planned release target should become `(x+1).0.0`.
- At cadence checkpoints, verify the active milestone due date and latest public release live, then flag owner approval when a patch or minor candidate is justified by security, regression, compatibility, packaging, or high-impact stability/performance needs.

## Live Rehydration Checklist
- Start every task with `git status --short --branch` and `git rev-parse --show-toplevel`; stop or switch context if the checkout is dirty, detached, or not the Perform repo.
- Fetch the remote branch list before choosing a base branch, then verify whether the target milestone has an existing `release/<milestone>` branch.
- Check `perform.php`, `readme.txt`, the latest GitHub release, open PRs, open issues, and the active milestone before editing or reporting release status.
- Confirm local Git identity is `Mehul Gohil <hello@mehulgohil.com>` before creating commits.
- Keep the final report tied to verified sources: branch/base, issue or PR URLs, validation run, and any intentionally skipped gates.

## Contributor PR Compatibility
- When a task references a contributor PR, issue, branch, or URL, inspect that exact item before broad queue scans or inferred replacement work.
- Treat human contributor PRs separately from bot/dependency PRs; review their diff scope, base branch, conflicts, CI, and current-code relevance before deciding next action.
- Prefer actionable review comments before closure: note what is useful, what blocks merge, what proof is missing, and what change would make the PR acceptable.
- If maintainer replacement work is needed, create the replacement PR first, reference the contributor PR, preserve credit where appropriate, and close only with a clear reason and replacement link.
- Do not merge dirty, failing, stale-base, or broad-churn contributor PRs without first reconciling conflicts, validation, and compatibility against the current codebase.

## Non-Negotiable Compatibility Rules
- Do not rename existing option/meta keys used by released versions.
- Preserve backward compatibility with legacy settings sections:
  - `perform_common`
  - `perform_ssl`
  - `perform_cdn`
  - `perform_woocommerce`
  - `perform_advanced`
- `perform_settings` is the standard canonical settings store for Perform.
- Add/maintain an automatic migration routine that migrates legacy settings into `perform_settings` when `perform_settings` does not exist.
- Keep runtime reads tolerant of both consolidated and legacy settings during migration windows.
- Do not change public hook names unless absolutely required; if required, add compatibility shims.

## Repository Layout
- Bootstrap: `perform.php`
- Core plugin wiring: `src/Plugin.php`
- Admin (settings UI + ajax save): `src/Admin/**`
- Frontend/shared hooks: `src/Includes/**`
- Feature modules: `src/Modules/**`
  - Module registry: `src/Modules/Registry.php`
  - Module loader: `src/Modules/Loader.php`
  - Module contract: `src/Modules/ModuleInterface.php`
- Assets source: `assets/src/**`
- Built assets: `assets/dist/**`
- Uninstall logic: `uninstall.php`

## Module System Expectations
- New modules must implement `Perform\Modules\ModuleInterface`.
- Register modules only through `src/Modules/Registry.php`.
- Loader enforces module interface and lifecycle:
  - `should_load(): bool`
  - `register(): void`
- If a module needs settings injection, extend `AbstractModule` and use `get_setting()`.

## Security and Runtime Guardrails
- Frontend-only behavior must be scoped carefully:
  - avoid running on admin, ajax, cron, rest, cli unless intended
- Redirect logic:
  - never run unguarded in constructor/register; attach to request hook
  - avoid redirect loops and skip if `headers_sent()`
- Output-buffer based rewrites are expensive:
  - avoid in non-HTML contexts
  - keep regex operations minimal and guarded
- All settings persistence must enforce:
  - capability checks
  - nonce validation
  - per-field sanitization

## Performance Guardrails
- Do not enqueue heavy admin assets globally.
- Scope admin scripts/styles to plugin screens only.
- Avoid repeated expensive lookups in hot paths; cache per-request where practical.
- Keep module registration lightweight; no expensive work during bootstrap.

## UI/UX Implementation Rules
- Prefer WordPress Design System components for plugin UI wherever possible.
- Build settings/admin UI using React with `@wordpress/components`, `@wordpress/element`, and related WordPress packages.
- Avoid custom UI primitives when equivalent WP Design System components exist.

## Build, Lint, and Analysis Commands
- Node runtime:
  - Use Node.js 24.x (`.nvmrc` and `.node-version` are authoritative for this release line)
- JS/CSS dev build:
  - `npm run start`
- JS/CSS prod build:
  - `npm run build`
- JS/CSS lint:
  - `npm run lint`
- PHP code style:
  - `composer check-cs`
- PHP lint:
  - `composer lint`
- PHPStan:
  - `composer phpstan`
- PHPUnit:
  - `composer test`
- Playwright smoke tests:
  - `npm run test:e2e:ci`

## Testing and Validation Before Commit
- Minimum for PHP changes:
  - `php -l` on changed PHP files
  - `composer lint` when feasible
- For module/settings changes:
  - verify module still loads via `Registry` + `Loader`
  - verify option compatibility with existing keys
- For admin UI/settings changes:
  - verify save flow still works via `perform_save_settings` ajax
  - verify the settings page script path matches built artifact names
- For tooling/CI changes:
  - preserve the Node 24 Active LTS policy unless the release plan changes
  - keep GitHub Actions on maintained action versions
  - run or document any unrun Composer, npm, PHPUnit, and Playwright validation gates

## CI Notes
- GitHub workflows include:
  - `CodeStyle` (PHPCS)
  - `Lint` (parallel-lint)
  - `Run PHPStan`
  - `Security`
  - release/pre-release packaging and deployment
- Keep changes aligned with existing workflow assumptions; avoid introducing new required secrets/tools without updating workflows.

## Release and Versioning Notes
- Keep release-safe changes on `release/*` branches.
- Do not silently change plugin version constants/headers unless part of an explicit release task.
- If behavior changes in public-facing optimization modules, prefer additive compatibility switches.

## WordPress Coding Conventions
- Follow WPCS conventions configured in `phpcs.xml.dist`.
- Escape output on render, sanitize input on save.
- Prefer `__()`, `esc_html__()`, etc. with text domain `perform`.

## Commit Guidance for Agents
- Make small, logical commits.
- Commit message style:
  - imperative, scoped, and outcome-oriented
  - example: `Harden SSL and CDN module runtime scope`
- Do not include unrelated formatting-only churn in functional commits.

## When Unsure
- Default to preserving runtime behavior for existing installs.
- Prefer compatibility over cleanup if both cannot be done safely in one patch.
- Document assumptions in PR/commit notes.
