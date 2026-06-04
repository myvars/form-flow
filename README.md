# myvars/form-flow

Generic Symfony controller-flow coordinators — the **FormFlow** pattern. Thin controllers delegate
to five flow types:

| Flow | Purpose |
|------|---------|
| `FormFlow` | Create/update with Symfony forms |
| `ActionFlow` | State transitions (approve, complete, …) |
| `ConfirmFlow` | Confirm-then-execute (delete, cancel, …) with CSRF |
| `SearchFlow` | Paginated index pages |
| `InlineEditFlow` | Inline field editing via Turbo Frames |

The package owns only the **logic**. It depends on framework + Pagerfanta + its own `Contract\` ports —
never on app code. The consuming app supplies **adapters** and **templates**.

## Install

```bash
composer require myvars/form-flow
```

Enable the bundle (`config/bundles.php`):

```php
MyVars\FormFlow\FormFlowBundle::class => ['all' => true],
```

## Adapters the app must provide

The flows depend on these ports (`MyVars\FormFlow\Contract\`). The app provides one implementation of
each; Symfony autowires them by interface (single implementation → automatic alias, or alias explicitly):

| Port | App implements / extends with |
|------|-------------------------------|
| `ResultInterface` (`isOk()`, `message()`, `redirect()`) | the command-handler result DTO |
| `RedirectTargetInterface` (`route()`, `params()`, `status()`) | the forced-redirect DTO |
| `FlasherInterface` (`success/warning/error`) | the flash-message helper |
| `SearchCriteriaInterface` (paging/sort getters) | the app's search-criteria interface `extends` this |

`InlineEditFlow` does **not** flush — the `onSave` callback owns persistence and returns whether anything
changed. `SearchFlow::search()` takes a Pagerfanta `AdapterInterface` (e.g.
`$repository->findByCriteria($criteria)`), so the package never touches the persistence layer.

## Template contract (app-owned)

The package ships no Twig. The app must provide these templates:

- `shared/form_flow/base.html.twig` — rendered by Form/Action/Confirm/Search flows.
- `shared/form_flow/inline_edit_display.html.twig`
- `shared/form_flow/inline_edit_form.html.twig`
- `shared/form_flow/inline_edit_success.stream.html.twig`

Variables passed to `base.html.twig`: `flowModel`, `flowModelPlural`, `flowOperation`, `template`,
`routes`, plus per-flow `result` / `results` / `form` / `confirmKey` / `flowBackLink` / `flowAllowDelete`.
