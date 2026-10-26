# Using the flows

Every flow is a stateless service you type-hint in a controller action. Each takes a **`FlowContext`**
describing the run. This page shows a complete, copy-pasteable example for all five, then documents
`FlowContext` and `FlowModel`.

- [FormFlow — create & update](#formflow)
- [ActionFlow — state transitions](#actionflow)
- [ConfirmFlow — confirm then execute](#confirmflow)
- [SearchFlow — paginated index](#searchflow)
- [InlineEditFlow — inline field editing](#inlineeditflow)
- [`FlowContext` reference](#flowcontext)
- [`FlowModel` reference](#flowmodel)

---

<a id="formflow"></a>
## FormFlow — create & update

Handles `GET` (render form) and `POST` (validate → map → dispatch → redirect) for a Symfony form. On a
handler failure it re-renders at `422` with an error flash; on an invalid submit it re-renders with the
form errors.

```php
public function form(
    Request $request,
    string $formType,        // FQCN of your FormType
    mixed $data,             // the form's bound DTO/entity
    callable $mapper,        // fn($formData): object  — form DTO -> command
    callable $handler,       // fn(object $command): ResultInterface
    FlowContext $context,
    array $formOptions = [],
): Response;
```

```php
#[Route('/task/{id}/edit', name: 'app_demo_task_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, Task $task, UpdateTaskMapper $mapper, UpdateTaskHandler $handler, FormFlow $flow): Response
{
    return $flow->form(
        request: $request,
        formType: TaskType::class,
        data: TaskForm::fromEntity($task),
        mapper: $mapper,
        handler: $handler,
        context: FlowContext::forUpdate(FlowModel::create('demo', 'task'))
            ->allowDelete(true)
            ->successRoute('app_demo_task_show', ['id' => (string) $task->getId()]),
    );
}
```

> **Mappers** are simple `__invoke` callables (`form DTO → command`). Keep them in
> `UI/Http/Form/Mapper/`. A handler returns a [`ResultInterface`](adapters.md#result); return
> `Result::ok(redirect: new RedirectTarget(...))` to force a specific destination.

---

<a id="actionflow"></a>
## ActionFlow — state transitions

For actions that change state without a form (complete, approve, publish…). Dispatches the command,
flashes the outcome, and redirects.

```php
public function process(Request $request, object $command, callable $handler, FlowContext $context): Response;
```

```php
#[Route('/task/{id}/complete', name: 'app_demo_task_complete', methods: ['POST'])]
public function complete(Request $request, Task $task, CompleteTaskHandler $handler, ActionFlow $flow): Response
{
    return $flow->process(
        request: $request,
        command: new CompleteTask($task->getId()),
        handler: $handler,
        context: FlowContext::forAction('app_demo_task_show', ['id' => (string) $task->getId()])
            ->csrf('complete' . $task->getId()),
    );
}
```

An action changes state, so route it as `POST` and add `->csrf($tokenId)`. The flow then checks the
posted `_token` field against that id before calling the handler; on a mismatch it flashes
"Invalid CSRF token." and redirects to the success route without running the action. Pass the full
token id and render the trigger as a form with the same one:

```twig
<form method="post" action="{{ path('app_demo_task_complete', {id: task.id}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('complete' ~ task.id) }}">
    <button type="submit">Mark complete</button>
</form>
```

Without `->csrf()` no token is checked. Use `ConfirmFlow` instead when the action needs a confirmation
page first.

---

<a id="confirmflow"></a>
## ConfirmFlow — confirm then execute

A two-step confirmable action: a confirmation page (`GET`) and a CSRF-validated execution (`POST`).
Delete is the canonical case; other confirmable actions (cancel, rewind, archive…) get their **own CSRF
key** via `forConfirm()` so two actions on the same entity never share a token.

```php
public function confirm(object $entity, FlowContext $context): Response;          // GET — render the prompt
public function execute(Request $request, object $command, callable $handler, FlowContext $context): Response; // POST
```

```php
#[Route('/task/{id}/delete/confirm', name: 'app_demo_task_delete_confirm', methods: ['GET'])]
public function deleteConfirm(Task $task, ConfirmFlow $flow): Response
{
    return $flow->confirm($task, FlowContext::forDelete(FlowModel::create('demo', 'task')));
}

#[Route('/task/{id}/delete', name: 'app_demo_task_delete', methods: ['POST'])]
public function delete(Request $request, Task $task, DeleteTaskHandler $handler, ConfirmFlow $flow): Response
{
    return $flow->execute(
        request: $request,
        command: new DeleteTask($task->getId()),
        handler: $handler,
        context: FlowContext::forDelete(FlowModel::create('demo', 'task')),
    );
}
```

The command passed to `execute()` must expose an `id` (`$command->id`); the CSRF token is scoped to
`confirmKey . id`. For a non-delete action:

```php
FlowContext::forConfirm(FlowModel::create('purchasing', 'purchase_order'), 'rewind')
    ->successRoute('app_purchasing_purchase_order_show', ['id' => (string) $po->getId()]);
```

> The confirmation template receives a `confirmKey` variable — render your `ConfirmDialog` token with
> it (`csrfId: confirmKey ~ entity.id`). See the [template contract](templates.md).

---

<a id="searchflow"></a>
## SearchFlow — paginated index

Renders an index page from a Pagerfanta adapter, and redirects to page 1 (with a warning flash) when an
out-of-range page is requested.

```php
public function search(Request $request, AdapterInterface $adapter, SearchCriteriaInterface $criteria, FlowContext $context): Response;
```

```php
#[Route('/task/', name: 'app_demo_task_index', methods: ['GET'])]
public function index(
    Request $request,
    TaskRepository $repository,
    SearchFlow $flow,
    #[MapQueryString] TaskSearchCriteria $criteria = new TaskSearchCriteria(),
): Response {
    return $flow->search(
        request: $request,
        adapter: $repository->findByCriteria($criteria),   // returns a Pagerfanta AdapterInterface
        criteria: $criteria,
        context: FlowContext::forSearch(FlowModel::create('demo', 'task')),
    );
}
```

> `SearchFlow` takes an **adapter**, not a repository — it never touches persistence. Your repository's
> `findByCriteria()` returns the Pagerfanta adapter; the flow paginates it. `TaskSearchCriteria`
> implements your app's `SearchCriteriaInterface`, which `extends` the package port.

---

<a id="inlineeditflow"></a>
## InlineEditFlow — inline field editing

A single endpoint serving three modes over Turbo Frames: display (`GET`), edit form (`GET ?edit`), and
save (`POST` → a Turbo Stream that refreshes the page). The **callback owns persistence** and reports
whether anything changed.

```php
public function handleField(
    Request $request,
    mixed $value,                          // current field value (a Closure returning it is deprecated, see below)
    callable $onSave,                      // fn(mixed $value): bool  — apply + persist; return "changed?"
    InlineEditContext $context,
    array $formOptions = [],               // InlineFieldType options (constraints, field_type, …)
): Response;
```

```php
#[Route('/task/{id}/inline/title', name: 'app_demo_task_inline_title', methods: ['GET', 'POST'])]
public function inlineTitle(Request $request, Task $task, InlineEditFlow $flow, FlusherInterface $flusher): Response
{
    return $flow->handleField(
        request: $request,
        value: $task->getTitle(),
        onSave: function (mixed $value) use ($task, $flusher): bool {
            $task->rename((string) $value);

            return $flusher->flush();   // return whether the DB actually changed (controls the flash)
        },
        context: InlineEditContext::create(
            frameId: 'inline-edit-task-' . $task->getId() . '-title',
            displayTemplate: 'demo/task/_inline_title.html.twig',
            entity: $task,
            property: 'title',   // check the value against Task::$title's validation rules
        ),
    );
}
```

`InlineEditContext::create()` parameters:

| Parameter | Default | Purpose |
|-----------|---------|---------|
| `frameId` | — | Turbo Frame id (must match the `InlineEdit` component) |
| `displayTemplate` | — | template rendered on success/display |
| `entity` | — | the entity being edited |
| `cancelUrl` | request path | URL that returns display mode |
| `entityVarName` | derived from class | template variable name for the entity |
| `displayTemplateVars` | `[]` | extra variables for the display template |
| `successMessage` | `'Updated successfully'` | flash on change; `null` to disable |
| `property` | `null` | entity property the value is saved to; its validation rules are checked before `onSave` |

### Validating against the entity

An inline edit runs no handler, so nothing validates the entity for you. Set `property` and the flow
checks the submitted value against that property's own rules (`#[Assert\NotBlank]`, `#[Assert\Length]`,
…) with `ValidatorInterface::validatePropertyValue()`, after the form is valid and before `onSave`.
Each violation shows on the field, the form comes back with a 422, and `onSave` is not called. The
entity stays the one place the rules are declared, so don't copy them into `formOptions`.

This needs `symfony/validator`. Setting `property` without a validator, or naming a property the
entity does not have, throws a `LogicException` on the first request. For a rule that does not belong
on the entity, `formOptions['constraints']` still works and is checked first.

Limits:
- Only the `Default` group is checked; rules in other groups are skipped.
- Only the property's own rules are checked, not class-level ones (`UniqueEntity`, a class `Callback`).
- The value is checked as the form submits it (a string for the default text field), before `onSave`
  converts it. Use `property` for fields whose submitted value already has the property's type.

### After a save

A successful save answers with `<turbo-stream action="refresh">`, as the other flows do after a save
(`TurboAwareRedirector`). Turbo requests the page again, so the field comes back in display mode and
every other copy of the value — a breadcrumb, a page heading, the `<title>` — is rendered from what
was stored, with nothing to mark up. The flash set on save shows on the refreshed page, wherever your
layout renders flashes. The stream keeps the scroll position (`scroll="preserve"`); whether the page
is replaced or morphed follows its own `turbo-refresh-method` meta tag, `replace` by default (Turbo's
[page refreshes](https://turbo.hotwired.dev/handbook/page_refreshes)).

- Each save costs one more request: the page itself.
- Anything typed into another open inline edit on the same page is lost, as with any refresh.
- The refresh reloads the browser's current URL, not the field's page. An inline edit shown inside
  another frame (a modal, a lazily loaded panel) is lost with that frame's content, and one whose value
  is part of the page's URL (a slug) refreshes an address that no longer exists. Override the success
  template for those.
- To keep the old behaviour (replace only the field's frame), override the success template — see
  [templates](templates.md#inline-edit-templates).

#### Deprecated: mirroring the value with a Closure

Before 1.5 the success stream replaced only the field's frame, and copies elsewhere on the page were
kept in step by passing `value:` as a Closure and marking them `data-inline-edit-mirror="<frameId>"`.
The refresh makes that unnecessary. A Closure still works through 1.x and triggers a deprecation: the
flow calls it for the form's starting value and again after `onSave`, and passes the result to the
success template as `value` and `mirrorText` (that value as text, for `string`, `int`, `float` and
`Stringable`; otherwise `null`). The default template no longer uses them; an overriding template can.
Pass the value itself instead (`value: $task->getTitle()`). Both are removed in 2.0.

---

<a id="flowcontext"></a>
## `FlowContext` reference

Create a context with a factory, then refine it fluently.

### Factories

| Factory | For | Notes |
|---------|-----|-------|
| `forCreate(FlowModel)` | `FormFlow` create | success route defaults to `…_index` |
| `forUpdate(FlowModel)` | `FormFlow` update | |
| `forFilter(FlowModel)` | `FormFlow` filter form | |
| `forSearch(FlowModel)` | `SearchFlow` | index operation |
| `forAction(string $route, array $params = [])` | `ActionFlow` | no model, just a success route |
| `forDelete(FlowModel)` | `ConfirmFlow` delete | CSRF key `delete`, smart navigation on |
| `forConfirm(FlowModel, string $key)` | `ConfirmFlow` non-delete | CSRF key `$key` |

### Fluent refinements

| Method | Effect |
|--------|--------|
| `->template(string $path)` | override the rendered template |
| `->successRoute(string $route, array $params = [])` | set the post-success redirect target |
| `->csrf(string $tokenId)` | `ActionFlow` requires a matching `_token` before running the action |
| `->allowDelete(bool)` | expose a delete affordance to the template |

---

<a id="flowmodel"></a>
## `FlowModel` reference

`FlowModel` derives display names, the template directory and route names by convention, so you write
them once.

```php
FlowModel::create('catalog', 'manufacturer');
// displayName:  "Manufacturer"        plural(): "Manufacturers"
// templateDir:  "catalog/manufacturer"
// routes:       app_catalog_manufacturer_{index,new,show,delete,delete_confirm,search_filter}
// template('create') => "catalog/manufacturer/create.html.twig"

FlowModel::simple('customer');                       // no bounded-context prefix -> "app_customer_*"
FlowModel::create('pricing', 'vat_rate', 'VAT Rate', 'VAT Rates');   // explicit display name + plural
FlowModel::simple('pricing')->withDisplayName('Product Cost');       // copy with a new label
```

Route names can be overridden individually via `FlowRoutes::with(...)` if your routes don't follow the
convention.
