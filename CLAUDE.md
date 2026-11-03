# CLAUDE.md

Guidance for working on `myvars/form-flow`. The detail is in [CONTRIBUTING.md](CONTRIBUTING.md) and
[docs/](docs/index.md); this file links to it rather than repeating it.

## Commands

```bash
composer test       # PHPUnit: unit, flow, integration and template tests
composer phpstan    # PHPStan level 7
composer cs         # PHP-CS-Fixer dry run; composer cs-fix applies it
```

CI runs all three. Each must pass.

## What the package is

A Symfony bundle of five controller flows (`FormFlow`, `ActionFlow`, `ConfirmFlow`, `SearchFlow`,
`InlineEditFlow`) plus the `FlowContext`/`FlowModel` value objects. The flows depend on framework
packages, Pagerfanta and the package's own `Contract\` ports, which each app implements
([docs/adapters.md](docs/adapters.md)). It never depends on app code; tests use the doubles in
`tests/Double/`. The templates in `templates/shared/form_flow/` are design-neutral defaults that apps
override at the same path: no CSS framework, no app components ([docs/templates.md](docs/templates.md)).

The docs describe the package as any app sees it. App choices (component names, `publicId`, Mercure, an
app's layering) belong in that app's docs, which link here.

## Code style

Follow [CONTRIBUTING.md › Conventions](CONTRIBUTING.md#conventions). The rules most easily missed:

- `declare(strict_types=1)` in every file; the fixer enforces it.
- `readonly` services are fine (PHP 8.4+ native lazy objects); don't remove it to allow a lazy proxy.
- **No request data in a service property.** Long-running workers (FrankenPHP worker mode, Messenger)
  reuse one flow instance across requests.

## Compatibility

SemVer. A change to the public API, templates and their variables included, goes through
`trigger_deprecation('myvars/form-flow', '<version>', …)`, an `#[IgnoreDeprecations]` test that expects
the message, and a CHANGELOG "Deprecated" entry. The old path is removed in the next major.

## Testing

Follow [CONTRIBUTING.md › Testing](CONTRIBUTING.md#testing). A new flow behaviour gets a flow test.

## Releasing

1. A "Prepare x.y.z" PR dates the CHANGELOG entry and moves `extra.branch-alias` in `composer.json`.
2. After it merges, tag `vx.y.z` on `main`; Packagist picks it up.
3. `docs/` is `export-ignore`d, so apps link to the tagged docs on GitHub. Bump those links in each app.

## Workflow

One issue, one branch and one PR per change. Commit messages and PRs are written as a human contributor,
with no AI attribution.
