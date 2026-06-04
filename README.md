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

## The five flows

| Flow | Purpose |
|------|---------|
| `FormFlow` | Create/update with Symfony forms — validate, map to a command, dispatch, flash, redirect |
| `ActionFlow` | State transitions without a form (approve, complete, …) |
| `ConfirmFlow` | Confirm-then-execute (delete, cancel, rewind, …) with per-action CSRF scoping |
| `SearchFlow` | Paginated index pages from a Pagerfanta adapter |
| `InlineEditFlow` | Single-field inline editing over Turbo Frames |

## Design

The package owns **logic only**. It depends on framework packages, Pagerfanta and its own `Contract\`
ports — never on application code. The consuming app supplies two things:

- **Adapters** for the ports (`ResultInterface`, `RedirectTargetInterface`, `FlasherInterface`,
  `SearchCriteriaInterface`), which Symfony autowires by interface.
- **Templates** — the bundle ships **design-neutral defaults** so flows render out of the box; your app
  overrides any of them by placing its own styled version at the same `templates/shared/form_flow/...`
  path (your `templates/` wins). See [docs/templates.md](docs/templates.md).

This keeps the flows decoupled from your domain, your DTOs and your design system.

## Requirements

- PHP 8.5+
- Symfony 8.1+ (Form, HttpFoundation, Routing, Security CSRF, Twig, UX Turbo)

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

Then provide the port adapters and templates — see **[docs/installation.md](docs/installation.md)**.

## Documentation

- **[Installation & wiring](docs/installation.md)** — bundle setup, the adapters you implement, path-repo/Docker notes
- **[Using the flows](docs/flows.md)** — a worked controller example for every flow, plus `FlowContext`/`FlowModel`
- **[Ports & adapters](docs/adapters.md)** — implementing `Result`, `RedirectTarget`, `Flasher`, `SearchCriteria`
- **[Template contract](docs/templates.md)** — required templates and the variables passed to them

## Quality

```bash
composer test       # PHPUnit — unit, flow and bundle-integration suites
composer phpstan     # PHPStan level 7
composer cs          # php-cs-fixer (dry-run)
```

## License

Released under the [MIT License](LICENSE).
