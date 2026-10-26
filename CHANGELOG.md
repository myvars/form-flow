# Changelog

All notable changes to `myvars/form-flow` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-10-26

### Changed

- `InlineEditFlow`'s default success template, `shared/form_flow/inline_edit_success.stream.html.twig`,
  is now `<turbo-stream action="refresh" scroll="preserve">`, as the other flows answer after a save. The page is rendered
  again, so a breadcrumb or heading showing the edited value is current, and the flash set on save shows
  on the refreshed page. It used to replace only the field's frame (and update `data-inline-edit-mirror`
  elements). Apps that override the template are unaffected. Costs: one more page request per save,
  and the refresh reloads the browser's current URL, so an inline edit inside a modal or another frame,
  or one that changes the URL (a slug), needs the replace-only override in `docs/templates.md`.
- `symfony/deprecation-contracts` is now required (it was already installed with every Symfony component).

### Deprecated

- Passing `InlineEditFlow::handleField()`'s `$value` as a Closure, and the success template's `value` and
  `mirrorText` variables (the mirror stream). They still work and are removed in 2.0; pass the value
  itself.

## [1.4.0] - 2026-10-17

### Added

- `InlineEditContext::create(property: …)`: the entity property an inline edit saves to. When set,
  `InlineEditFlow` checks the submitted value against that property's validation rules before `onSave`,
  shows each violation on the field and re-renders with a 422 without calling `onSave`. Needs
  `symfony/validator` (now suggested). A `property` the flow cannot check (no validator, or a property the
  entity does not have) throws a `LogicException` on the first request. Without `property` nothing changes.
- `InlineEditFlow` takes an optional `ValidatorInterface`, autowired when the validator is installed.

## [1.3.1] - 2026-10-16

### Fixed

- `ConfirmFlow::execute()` documents the command id as `int|string|\Stringable`, so an app that passes a
  typed id value object (a ULID wrapper, say) from a file with `declare(strict_types=1)` passes PHPStan.
  Runtime behaviour is unchanged: the id was always only concatenated into the CSRF token id.

## [1.3.0] - 2026-10-13

### Added

- `FlowContext::csrf(string $tokenId)`: an opt-in CSRF check for `ActionFlow`. When set, `process()`
  compares the posted `_token` field with that token id before calling the handler; on a mismatch it
  flashes "Invalid CSRF token." and redirects to the success route. Without it nothing changes.

### Changed

- `ConfirmFlow::execute()` now delegates its token check to `ActionFlow` (same token id, same
  behaviour). Constructors changed to match: `ActionFlow` takes a `CsrfTokenManagerInterface`, and
  `ConfirmFlow` no longer takes one or a `FlasherInterface`. Both are autowired, so this only affects
  code that constructs them by hand.

## [1.2.0] - 2026-10-07

### Added

- `InlineEditFlow::handleField()` accepts the current value as a **`Closure`**. The flow calls it for
  the form's starting value and again after `onSave`, and passes the stored result to the success
  template as `value`, plus `mirrorText` (that value as text, or `null` when it is not text-like).
- The default `inline_edit_success.stream.html.twig` sends a second stream that updates every element
  marked `data-inline-edit-mirror="<frameId>"`, so copies of the field outside its Turbo Frame (a
  breadcrumb, a heading) no longer go stale after an inline edit. Non-breaking: with a plain (non-Closure)
  value nothing extra is sent, and an app's own override of the template is unaffected until it adopts
  the new variables.

## [1.1.0] - 2026-06-04

### Added

- Design-neutral **default templates** (`templates/shared/form_flow/*`) so the flows render out of the
  box. The bundle registers them in Twig's main namespace at lower priority than the app's `templates/`,
  so an app overrides any default by providing its own file at the same path. Non-breaking: existing
  apps with their own `shared/form_flow/*` templates are unaffected (theirs win).

## [1.0.0] - 2026-06-04

### Added

- Initial release, extracted from the in-house application skeleton.
- Five flow coordinators: `FormFlow`, `ActionFlow`, `ConfirmFlow`, `SearchFlow`, `InlineEditFlow`.
- `View\` value objects: `FlowContext`, `FlowModel`, `FlowRoutes`, `FormOperation`, `TemplateContext`.
- Turbo-aware redirection (`RedirectorInterface` / `TurboAwareRedirector`).
- `Contract\` ports — `ResultInterface`, `RedirectTargetInterface`, `FlasherInterface`,
  `SearchCriteriaInterface` — so the package never depends on application code.
- `FormFlowBundle` auto-registering the flows and their internal services.
- Full unit, flow, and bundle-integration test suites.
