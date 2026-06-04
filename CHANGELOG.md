# Changelog

All notable changes to `myvars/form-flow` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
