# FormFlow

[![CI](https://github.com/myvars/form-flow/actions/workflows/ci.yml/badge.svg)](https://github.com/myvars/form-flow/actions/workflows/ci.yml)

**Thin controllers, consistent flows.** `myvars/form-flow` is a small Symfony bundle that factors the
repetitive parts of CRUD-style controllers — form handling, validation feedback, CSRF-guarded
confirmation, pagination, Turbo-aware redirects — into five reusable *flow* coordinators. Your
controller actions stay one call long, and every create/update/delete action follows the same shape.

```php
#[Route('/task/new', name: 'app_demo_task_new', methods: ['GET', 'POST'])]
public function new(Request $request, CreateTaskMapper $mapper, CreateTaskHandler $handler, FormFlow $flow): Response
{
    return $flow->form(
        request: $request,
        formType: TaskType::class,
        data: new TaskForm(),
        mapper: $mapper,      // form DTO -> command
        handler: $handler,    // command -> ResultInterface
        context: FlowContext::forCreate(FlowModel::create('demo', 'task')),
    );
}
```

The task classes in this example (`TaskForm`, `TaskType`, the mapper and the handler) are built in
[getting started](docs/getting-started.md).

## The five flows

| Flow | Purpose |
|------|---------|
| `FormFlow` | Create/update with Symfony forms — validate, map to a command, dispatch, flash, redirect |
| `ActionFlow` | State transitions without a form (approve, complete, …) |
| `ConfirmFlow` | Confirm-then-execute (delete, cancel, rewind, …) with per-action CSRF scoping |
| `SearchFlow` | Paginated index pages from a Pagerfanta adapter |
| `InlineEditFlow` | Single-field inline editing over Turbo Frames |

Which one fits a given action: [choosing a flow](docs/flows.md#choosing-a-flow).

## Design

The package owns **logic only**. It depends on framework packages, Pagerfanta and its own `Contract\`
ports — never on application code. The consuming app supplies two things:

- **Adapters** for the ports (`ResultInterface`, `RedirectTargetInterface`, `FlasherInterface`,
  `SearchCriteriaInterface`), small classes it writes once ([docs/adapters.md](docs/adapters.md)).
- **Templates** — the bundle ships **design-neutral defaults** so flows render out of the box; your app
  overrides any of them by placing its own styled version at the same `templates/shared/form_flow/...`
  path (your `templates/` wins). See [docs/templates.md](docs/templates.md).

This keeps the flows decoupled from your domain, your DTOs and your design system.

## Requirements

- PHP 8.5+
- Symfony 8.1+ components: Config, DependencyInjection, Form, HttpFoundation, HttpKernel, ObjectMapper,
  OptionsResolver, Routing and Security CSRF
- Twig 3
- Pagerfanta 4 (`pagerfanta/core`)
- Symfony UX Turbo 3.1+

Optional: `symfony/validator`, to check an inline edit against the entity property's rules
(`InlineEditContext` `property`, see [inline editing](docs/flows.md#validating-against-the-entity)).

## Installation

```bash
composer require myvars/form-flow
```

Enable the bundle (if Symfony Flex did not):

```php
// config/bundles.php
return [
    // ...
    MyVars\FormFlow\FormFlowBundle::class => ['all' => true],
];
```

New to the package? **[Getting started](docs/getting-started.md)** builds one complete resource in a new
app, from the adapters to the tests. The wiring on its own (adapters, templates, the `redirect` Turbo
Stream action) is in **[docs/installation.md](docs/installation.md)**.

## Documentation

- **[Getting started](docs/getting-started.md)** — one resource end to end in a new app: adapters, entity, form, handlers, controller, templates, tests
- **[Installation & wiring](docs/installation.md)** — bundle setup, the adapters you implement, the `redirect` stream action, path-repo/Docker notes
- **[Using the flows](docs/flows.md)** — which flow to use, a worked controller example for every flow, filter forms, testing your controllers; responses, Turbo-aware redirects and flashes; `FlowContext`/`FlowModel`
- **[Ports & adapters](docs/adapters.md)** — implementing `Result`, `RedirectTarget`, `Flasher`, `SearchCriteria`
- **[Template contract](docs/templates.md)** — the templates the flows render, how to override them, and their variables

## Quality

```bash
composer test        # PHPUnit — unit, flow, integration and template tests
composer phpstan     # PHPStan level 7
composer cs          # php-cs-fixer (dry-run)
```

How tests are written: [CONTRIBUTING.md › Testing](CONTRIBUTING.md#testing).

## License

Released under the [MIT License](LICENSE).
