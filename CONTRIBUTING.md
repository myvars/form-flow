# Contributing

## Local setup

```bash
composer install
```

## Quality gates

All three must pass before a change is merged:

```bash
composer test       # PHPUnit: unit, flow, integration and template tests
composer phpstan    # PHPStan level 7
composer cs         # php-cs-fixer (dry-run); use `composer cs-fix` to apply
```

## Testing

How each test is written. Bring a test in line when you change it.

| Topic | Convention |
|-------|------------|
| Kinds | **Unit** (`tests/Unit/`, folders mirror `src/`): one class on its own, such as a value object, the guard, the mapper or the redirector. **Flow** (`tests/Flow/`, one file per flow): a flow driven through its public method with the port doubles. A new flow behaviour gets a flow test. `InlineEditEntityRulesTest` is the one extra file: it runs `InlineEditFlow` with a real form factory and validator. **Integration** (`tests/Integration/`): the bundle's service definitions in a compiled `ContainerBuilder`. **Templates** (`tests/Templates/`): each shipped default template rendered with real Twig and `strict_variables` |
| Class | `final`, extends `TestCase`. Test methods are named `testXxx` and say what happens (`testInvalidPostRendersFormWith422`), with no `#[Test]` attribute |
| Layout | Tests first, then the private helpers at the bottom of the class (`InlineEditEntityRulesTest`). A data provider goes just above the test that uses it |
| Assertions | `self::assert*()`, which are static. Expected exceptions: `$this->expectException()` and `$this->expectExceptionMessage()` before the call that throws. No boolean `$this->callback()` for arguments: when it fails, PHPUnit can't say which part was wrong. Pass the expected value to `with()` (`new CsrfToken('delete5', 'bad-token')`), or record the argument and assert on it |
| Doubles | The port doubles in `tests/Double/` (`RecordingFlasher`, `StubResult`, `StubRedirectTarget`, `StubSearchCriteria`) rather than mocks of the `Contract\` interfaces. `RecordingTwig` when a test checks what a flow rendered: it keeps the last template and its variables, and the test checks only the variables it is about (`DefaultTemplatesTest` covers the template contract). `createStub()` unless the test expects a call. `createMock()` with `expects()` only for a call that is the behaviour under test, such as the redirect arguments or the CSRF token id |
| "Must not run" | Pass `fn () => self::fail('Handler must not run.')` as the handler, or `fn (): bool => self::fail('onSave must not run')` as `onSave`, not a `$called` flag checked afterwards. `InlineEditFlow` turns an exception from `onSave` into a form error, so an inline edit test must fail on an unexpected form error too: the form stub's `addError()` fails the test (`InlineEditFlowTest`), or the test checks the rendered form's errors (`InlineEditEntityRulesTest::fieldErrors()`) |
| Deprecations | The suite fails on any deprecation (`failOnDeprecation`). A test of a deprecated path has `#[IgnoreDeprecations]` and calls `$this->expectUserDeprecationMessage()` with the exact message (`InlineEditFlowTest`) |

## Conventions

- Code style: `@Symfony` rules, no Yoda conditions (`$value === null`, not `null === $value`;
  `composer cs` rejects them, `composer cs-fix` rewrites them), spaced concatenation (`'a' . $b`).
- `declare(strict_types=1)` in every file (`composer cs` rejects a file without it, `composer cs-fix`
  adds it).
- Full type hints on every method; `readonly` and constructor promotion where possible, services
  included. On PHP 8.4+ Symfony makes lazy services with native lazy objects, which work with
  `final readonly` classes.
- No request data in a service property. The flows are shared services, and long-running workers
  (FrankenPHP worker mode, Messenger) let one instance serve many requests, so a flow reads the request
  it is given and keeps nothing from it.
- The package must depend only on framework packages and its own `Contract\` ports —
  never on a consuming application's classes. Tests use the doubles in `tests/Double/`.
- The package ships design-neutral default templates (`templates/shared/form_flow/*`); apps override any
  by providing their own at the same path. Keep the defaults component- and CSS-framework-free.
