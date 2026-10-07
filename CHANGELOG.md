# Changelog

All notable changes to `myvars/form-flow` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
