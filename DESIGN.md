# Perform admin design baseline

This document is the canonical design reference for Perform's WordPress admin
experience. It keeps new work consistent with WordPress while giving Perform a
recognizable, accessible product voice.

## Baseline

- Start with the [WordPress Design System Figma
  library](https://www.figma.com/community/file/1436359662053949167/wordpress-design-system)
  and the [WordPress Design
  Handbook](https://make.wordpress.org/design/handbook/).
- Use `@wordpress/components` for established admin controls. Evaluate
  `@wordpress/ui` only when its relevant component contract is stable enough for
  the plugin's supported WordPress versions.
- Use familiar WordPress interaction patterns, language, capabilities, nonces,
  notices, focus behavior, and keyboard behavior.
- Use [Heroicons](https://heroicons.com/) outline icons for product actions and
  navigation when WordPress does not already provide a semantic control. Do not
  mix Dashicons, copied SVG paths, emoji, or unrelated icon sets in a migrated
  surface.

## Perform foundations

### Color

| Token | Value | Use |
| --- | --- | --- |
| `--perform-color-brand` | `#0046d1` | Primary actions, active navigation, links |
| `--perform-color-brand-hover` | `#0037a8` | Primary hover and pressed state |
| `--perform-color-focus` | `#2271b1` | Keyboard focus ring |
| `--perform-color-text` | `#1d2327` | Primary text |
| `--perform-color-text-muted` | `#50575e` | Descriptions and supporting text |
| `--perform-color-border` | `#dcdcde` | Dividers and control boundaries |
| `--perform-color-surface` | `#ffffff` | Main settings surface |
| `--perform-color-canvas` | `#f0f0f1` | WordPress admin canvas |
| `--perform-color-success` | `#008a20` | Confirmed healthy or completed state |
| `--perform-color-warning` | `#996800` | Caution requiring review |
| `--perform-color-danger` | `#b32d2e` | Destructive actions and errors |

Color never carries meaning by itself. Pair status color with text and, when
useful, a consistent icon.

### Type and spacing

- Inherit the WordPress admin system font stack.
- Navigation: `14px`, weight `600`, line-height `1.4`, with a minimum `44px`
  target height.
- Section title: `20px`, weight `600`, line-height `1.3`.
- Field title: `14px`, weight `600`, line-height `1.4`.
- Description: `13px`, weight `400`, line-height `1.5`.
- Use the spacing scale `4, 8, 12, 16, 24, 32, 40px`.
- Use outline icons at `16px` for compact inline actions, `20px` for navigation
  and standard actions, and `24px` only for prominent status or feature cues.

## Settings layout

Settings pages use one full-width field-row pattern:

1. A section heading and plain-language introduction establish the purpose.
2. Each row places the title and description in the left region.
3. The corresponding WordPress control is aligned in the right region.
4. Rows stack into one column at narrow widths, with the control immediately
   after its description.
5. Dividers, not nested cards, establish rhythm within a settings section.

Existing setting IDs, option keys, defaults, sanitization, capability checks,
nonces, and module behavior are compatibility contracts. A visual migration
must not silently change them.

## Interaction and content

- Prefer one clear primary action per context. Destructive actions require
  confirmation and visible progress.
- Use verbs that describe the result: “Export CSV”, “Clear activity”, “Save
  settings”.
- Explain outcomes in plain language before implementation detail.
- Keep loading, success, empty, and error states available to assistive
  technology through an appropriate live region.
- Disabled controls must remain legible and explain why they are unavailable
  when the reason is not obvious.
- All controls need an accessible name, visible focus, keyboard operation, and
  at least a `44px` target where the control is primarily navigational or an
  action.

## When custom UI is acceptable

Custom UI is appropriate only when an available WordPress component cannot
express the required behavior, and when the custom behavior:

- preserves WordPress admin semantics and accessibility;
- uses the tokens and icon system above;
- has tests for its state transitions and failure behavior;
- does not duplicate a stable native control without a documented reason.

## Required UI proof

Every material admin UI pull request includes:

- screenshots of every affected tab at desktop and narrow widths;
- keyboard and visible-focus proof for navigation and actions;
- checks for long translated text, empty data, disabled controls, progress,
  success, and failure states relevant to the change;
- automated component tests for shared components and stateful actions;
- confirmation that setting keys, saving, sanitization, and module behavior are
  unchanged;
- production frontend build plus lint, PHP, security, and relevant browser
  checks.

Review custom UI against the WordPress baseline, this document, and the
[WordPress Accessibility
Handbook](https://make.wordpress.org/accessibility/handbook/).
