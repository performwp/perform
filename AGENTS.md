# AGENTS.md

Guidance for AI coding agents working on the Perform WordPress plugin.

## Project Snapshot
- Plugin: `Perform`
- Current public line: `1.5.1`
- Active release branch in this workspace: `release/1.6.0`
- Stack:
  - PHP (WordPress plugin, PSR-4 autoload `Perform\\` -> `src/`)
  - JS/CSS via `@wordpress/scripts` + webpack output in `assets/dist`
  - Freemius SDK via Composer

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
- JS/CSS dev build:
  - `npm run start`
- JS/CSS prod build:
  - `npm run build`
- PHP code style:
  - `composer check-cs`
- PHP lint:
  - `composer lint`
- PHPStan:
  - `composer phpstan`

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
