# FormFlow documentation

`myvars/form-flow` factors the repetitive mechanics of CRUD-style Symfony controllers into five
reusable *flow* coordinators, so controller actions stay thin and consistent instead of repeating the
same handle-form, flash and redirect logic in every action.

## Contents

1. [Getting started](getting-started.md): one resource end to end, in a new app
2. [Installation & wiring](installation.md)
3. [Using the flows](flows.md)
4. [Ports & adapters](adapters.md)
5. [Template contract](templates.md)

## Concepts

**Flows** are stateless services that orchestrate one HTTP interaction end to end: build a form,
validate, map to a command, dispatch it, flash feedback, and redirect (Turbo-aware). There are five —
`FormFlow`, `ActionFlow`, `ConfirmFlow`, `SearchFlow`, `InlineEditFlow`.

**`FlowContext`** is a small declarative object describing one flow run — the operation, the model, the
success route, CSRF key, redirect behaviour. Build it with a factory (`forCreate`, `forUpdate`,
`forDelete`, `forConfirm`, `forAction`, `forFilter`, `forSearch`) and refine it fluently.

**`FlowModel`** derives a display name, template directory and route names from a context/entity pair by
convention (`FlowModel::create('catalog', 'product')`), so you don't repeat strings.

**Ports** (`Contract\*`) are the only contracts the flows depend on. The application provides one
adapter per port ([Ports & adapters](adapters.md)). This is what keeps the package free of application code.

**Templates** are rendered from documented paths. The bundle ships design-neutral defaults at those paths,
so the flows render out of the box, and the application overrides any of them with its own styled version
(see [templates](templates.md)). Your design system stays yours.

## How a request flows

```
Controller action
  └─ $flow->form(... FlowContext ...)
        ├─ build & handle the Symfony form
        ├─ on valid submit: $mapper(formData) -> command
        │                    $handler(command) -> ResultInterface
        │                       ├─ ok  -> Flasher::success + Turbo-aware redirect
        │                       └─ fail -> 422 + Flasher::error, re-render
        └─ on GET / invalid: render the app's template
```
