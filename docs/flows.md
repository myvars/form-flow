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
        context: FlowContext::forAction('app_demo_task_show', ['id' => (string) $task->getId()]),
    );
}
```

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
save (`POST` → Turbo Stream). The **callback owns persistence** and reports whether anything changed.

```php
public function handleField(
    Request $request,
    mixed $value,                          // current field value
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
        ),
        formOptions: ['constraints' => [new Assert\NotBlank()]],
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
