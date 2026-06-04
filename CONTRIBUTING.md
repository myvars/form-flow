# Contributing

## Local setup

```bash
composer install
```

## Quality gates

All three must pass before a change is merged:

```bash
composer test       # PHPUnit (unit + flow + bundle integration)
composer phpstan    # PHPStan level 7
composer cs         # php-cs-fixer (dry-run); use `composer cs-fix` to apply
```

## Conventions

- Code style: `@Symfony` rules, no Yoda conditions, spaced concatenation (`'a' . $b`).
- Full type hints on every method; `readonly` and constructor promotion where possible.
- The package must depend only on framework packages and its own `Contract\` ports —
  never on a consuming application's classes. Tests use the doubles in `tests/Double/`.
- The package ships design-neutral default templates (`templates/shared/form_flow/*`); apps override any
  by providing their own at the same path. Keep the defaults component- and CSS-framework-free.
