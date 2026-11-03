# Getting started

This guide builds one complete resource, a `Task`, in a new Symfony app: a paginated index with a
search box and a filter, create, show, edit and delete pages, a "mark done" action and an inline
rename. Every class it uses is on this page or on [Ports & adapters](adapters.md). The other docs use the
same `Task` resource, so once you have built it their examples fit straight in.

1. [Install](#install)
2. [Add the adapters](#adapters)
3. [Set up the layout](#layout)
4. [Choose the names](#names)
5. [The entity and repository](#entity)
6. [The search criteria](#criteria)
7. [The form](#form)
8. [Commands, mappers and handlers](#handlers)
9. [The filter](#filter)
10. [The controller](#controller)
11. [The templates](#templates)
12. [Try it](#try-it)
13. [Test it](#test-it)

Each PHP block is a whole file, at the path in its first line; add `<?php` at the top.

<a id="install"></a>
## 1. Install

Start from a Symfony 8.1 web app (PHP 8.5). The `--webapp` skeleton already has Doctrine, forms,
validation, Twig, Turbo and the maker bundle:

```bash
symfony new demo --webapp
cd demo
composer require myvars/form-flow pagerfanta/doctrine-orm-adapter
```

Symfony Flex enables the bundle. `pagerfanta/doctrine-orm-adapter` lets the repository hand a Doctrine
query to `SearchFlow` ([step 5](#entity)).

Point the app at a database. SQLite needs no server; in `.env`, replace the `DATABASE_URL` line with:

```bash
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_%kernel.environment%.db"
```

Set it in `.env`, not `.env.local`: the tests in [step 13](#test-it) run in the `test` environment,
which doesn't read `.env.local`. The file name includes the environment, so the tests get a database of
their own.

To work on the package and an app together, see [installation](installation.md#working-on-the-package-and-an-app-together).

<a id="adapters"></a>
## 2. Add the adapters

The flows reach your app through four small interfaces. Copy the four classes from
[Ports & adapters](adapters.md) into `src/Flow/`:

| File | What it is |
|------|------------|
| [`src/Flow/Result.php`](adapters.md#result) | what a handler returns: ok or failed, a message, an optional redirect |
| [`src/Flow/RedirectTarget.php`](adapters.md#redirect) | a route to redirect to, returned inside a `Result` |
| [`src/Flow/FlashMessenger.php`](adapters.md#flasher) | adds the flows' messages to the session flash bag |
| [`src/Flow/SearchCriteria.php`](adapters.md#criteria) | the base for every index's paging, sorting and query |

The flows inject the flasher, so point its interface at your class. Add the alias at the end of
`config/services.yaml`, under `services:`:

```yaml
    MyVars\FormFlow\Contract\FlasherInterface: '@App\Flow\FlashMessenger'
```

The other three need no configuration.

<a id="layout"></a>
## 3. Set up the layout

The flows render `shared/form_flow/base.html.twig`, which the bundle ships as a bare fragment: no
`<html>`, no layout. Give it yours by creating the same path in your app, which takes precedence:

```twig
{# templates/shared/form_flow/base.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ flowModelPlural }}{% endblock %}

{% block body %}
    {{ include([template, 'shared/form_flow/' ~ flowOperation ~ '.html.twig', 'shared/form_flow/missing.html.twig']) }}
{% endblock %}
```

The `include` is the bundle's own: the entity's template for the operation if it has one, else the
bundle's default ([template resolution](templates.md#template-resolution)).

The flows report outcomes as flash messages, so the layout has to print them. Add this to
`templates/base.html.twig`, at the start of `<body>`:

```twig
{% for type, messages in app.flashes %}
    {% for message in messages %}
        <p class="flash flash-{{ type }}">{{ message }}</p>
    {% endfor %}
{% endfor %}
```

<a id="names"></a>
## 4. Choose the names

`FlowModel` derives a resource's route names and template directory from two words, a context and an
entity, so the flows can link between pages without being told every route. This guide uses
`FlowModel::create('demo', 'task')`:

| Route name | Used for |
|------------|----------|
| `app_demo_task_index` | the index; also where a create, filter or delete goes by default |
| `app_demo_task_new` | the create form |
| `app_demo_task_show` | one task |
| `app_demo_task_delete_confirm` | the delete confirmation page |
| `app_demo_task_delete` | the delete itself |
| `app_demo_task_search_filter` | the filter form |

The templates live in `templates/demo/task/`. Name your routes this way and the defaults link up; a route
outside the convention is fine (`app_demo_task_edit` below), you just name it yourself where it's used.
`FlowModel::simple('task')` drops the context (`app_task_*`, `templates/task/`). See the
[`FlowModel` reference](flows.md#flowmodel).

<a id="entity"></a>
## 5. The entity and repository

```php
// src/Entity/Task.php
namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[UniqueEntity('title', message: 'There is already a task with this title.')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column]
    private bool $done = false;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function rename(string $title): void
    {
        $this->title = $title;
    }

    public function complete(): void
    {
        $this->done = true;
    }
}
```

The repository's `findByCriteria()` builds the query and wraps it in a Pagerfanta adapter. `SearchFlow`
does the paging, so the repository never sees a page number.

```php
// src/Repository/TaskRepository.php
namespace App\Repository;

use App\Entity\Task;
use App\Task\TaskSearchCriteria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return AdapterInterface<Task>
     */
    public function findByCriteria(TaskSearchCriteria $criteria): AdapterInterface
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.' . $criteria->getSort(), $criteria->getSortDirection());

        if ($criteria->getQuery() !== null) {
            $qb->andWhere('t.title LIKE :query')->setParameter('query', '%' . $criteria->getQuery() . '%');
        }

        if ($criteria->done !== null) {
            $qb->andWhere('t.done = :done')->setParameter('done', $criteria->done);
        }

        return new QueryAdapter($qb);
    }
}
```

Create the table:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

<a id="criteria"></a>
## 6. The search criteria

The task index's criteria: the [base class](adapters.md#criteria) for paging, sorting and the query,
the fields the index may sort by, and one filter of its own.

```php
// src/Task/TaskSearchCriteria.php
namespace App\Task;

use App\Flow\SearchCriteria;

final class TaskSearchCriteria extends SearchCriteria
{
    protected const array SORTS = ['id', 'title', 'done'];

    public ?bool $done = null;
}
```

`/task?query=docs&done=0&sort=title&page=2` fills it, through `#[MapQueryString]` in the
[controller](#controller).

<a id="form"></a>
## 7. The form

The form edits a form model, not the entity: the entity only changes in a handler, after the form is
valid. `fromEntity()` fills it for an edit, including the id, which comes from the route and never from a
form field ([why](flows.md#a-records-id-comes-from-the-route)).

```php
// src/Form/TaskForm.php
namespace App\Form;

use App\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskForm
{
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    public static function fromEntity(Task $task): self
    {
        $form = new self();
        $form->id = $task->getId();
        $form->title = $task->getTitle();

        return $form;
    }
}
```

```php
// src/Form/TaskType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TaskForm>
 */
final class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TaskForm::class]);
    }
}
```

<a id="handlers"></a>
## 8. Commands, mappers and handlers

A flow never changes data itself. It turns the form into a **command** with a **mapper**, passes the
command to a **handler**, and reports the handler's [`Result`](adapters.md#result). The handler is where
the work happens, and it knows nothing about HTTP, so the same handler can serve a console command or a
message queue.

The commands are plain data:

```php
// src/Task/CreateTask.php
namespace App\Task;

final readonly class CreateTask
{
    public function __construct(public string $title)
    {
    }
}
```

```php
// src/Task/UpdateTask.php
namespace App\Task;

final readonly class UpdateTask
{
    public function __construct(public int $id, public string $title)
    {
    }
}
```

```php
// src/Task/DeleteTask.php
namespace App\Task;

final readonly class DeleteTask
{
    public function __construct(public int $id)
    {
    }
}
```

```php
// src/Task/CompleteTask.php
namespace App\Task;

final readonly class CompleteTask
{
    public function __construct(public int $id)
    {
    }
}
```

`DeleteTask` has a public `id` because `ConfirmFlow` scopes its CSRF token to it
([ConfirmFlow](flows.md#confirmflow)).

The mappers turn a valid form model into a command. Keep them to copying fields: no lookups and no side
effects, so a mapper can't fail. (When the names match, the package's
[`ObjectMapper`](flows.md#mapping-with-objectmapper) can stand in for one.)

```php
// src/Form/Mapper/CreateTaskMapper.php
namespace App\Form\Mapper;

use App\Form\TaskForm;
use App\Task\CreateTask;

final class CreateTaskMapper
{
    public function __invoke(TaskForm $form): CreateTask
    {
        return new CreateTask((string) $form->title);
    }
}
```

```php
// src/Form/Mapper/UpdateTaskMapper.php
namespace App\Form\Mapper;

use App\Form\TaskForm;
use App\Task\UpdateTask;

final class UpdateTaskMapper
{
    public function __invoke(TaskForm $form): UpdateTask
    {
        return new UpdateTask((int) $form->id, (string) $form->title);
    }
}
```

The handlers do the work and return a `Result`. Its message becomes the flash. A failed result
re-renders the form with a 422. `CreateTaskHandler` checks the entity's rules, which catch what the form
model can't, such as a duplicate title, and on success sends the user to the new task with a
`RedirectTarget`.

```php
// src/Task/CreateTaskHandler.php
namespace App\Task;

use App\Entity\Task;
use App\Flow\RedirectTarget;
use App\Flow\Result;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateTaskHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(CreateTask $command): Result
    {
        $task = new Task($command->title);

        $violations = $this->validator->validate($task);
        if (count($violations) > 0) {
            return Result::fail((string) $violations[0]->getMessage());
        }

        $this->em->persist($task);
        $this->em->flush();

        return Result::ok('Task created', new RedirectTarget('app_demo_task_show', ['id' => $task->getId()]));
    }
}
```

```php
// src/Task/UpdateTaskHandler.php
namespace App\Task;

use App\Flow\Result;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UpdateTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(UpdateTask $command): Result
    {
        $task = $this->tasks->find($command->id);
        if ($task === null) {
            return Result::fail('Task not found.');
        }

        $task->rename($command->title);

        $violations = $this->validator->validate($task);
        if (count($violations) > 0) {
            return Result::fail((string) $violations[0]->getMessage());
        }

        $this->em->flush();

        return Result::ok('Task updated');
    }
}
```

```php
// src/Task/DeleteTaskHandler.php
namespace App\Task;

use App\Flow\Result;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeleteTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(DeleteTask $command): Result
    {
        $task = $this->tasks->find($command->id);
        if ($task === null) {
            return Result::fail('Task not found.');
        }

        $this->em->remove($task);
        $this->em->flush();

        return Result::ok('Task deleted');
    }
}
```

```php
// src/Task/CompleteTaskHandler.php
namespace App\Task;

use App\Flow\Result;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CompleteTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CompleteTask $command): Result
    {
        $task = $this->tasks->find($command->id);
        if ($task === null) {
            return Result::fail('Task not found.');
        }

        if ($task->isDone()) {
            return Result::fail('This task is already done.');
        }

        $task->complete();
        $this->em->flush();

        return Result::ok('Task done');
    }
}
```

<a id="filter"></a>
## 9. The filter

The filter is a form over the search criteria. Submitting it redirects to the index with the chosen
values in the query string, so a filtered index is an ordinary URL you can bookmark or share
([filter forms](flows.md#filter-forms)).

The form type edits `TaskSearchCriteria` directly. The hidden fields carry the current search and sort
through the filter, and the page is left out, so a new filter starts at page 1:

```php
// src/Form/TaskFilterType.php
namespace App\Form;

use App\Task\TaskSearchCriteria;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TaskSearchCriteria>
 */
final class TaskFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('done', ChoiceType::class, [
                'label' => 'Status',
                'choices' => ['Open' => false, 'Done' => true],
                'placeholder' => 'Any',
                'required' => false,
            ])
            ->add('query', HiddenType::class)
            ->add('sort', HiddenType::class)
            ->add('sortDirection', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TaskSearchCriteria::class]);
    }
}
```

The criteria already hold everything the handler needs, so the filter has no command class of its own:
the controller's mapper passes the criteria through. The handler builds the index URL's query string:

```php
// src/Task/TaskFilterHandler.php
namespace App\Task;

use App\Flow\RedirectTarget;
use App\Flow\Result;

final readonly class TaskFilterHandler
{
    public function __invoke(TaskSearchCriteria $criteria): Result
    {
        $params = array_filter([
            'query' => $criteria->getQuery(),
            'sort' => $criteria->getSort(),
            'sortDirection' => $criteria->getSortDirection(),
            'done' => $criteria->done === null ? null : (int) $criteria->done,
        ], static fn (mixed $value): bool => $value !== null);

        return Result::ok(redirect: new RedirectTarget('app_demo_task_index', $params));
    }
}
```

<a id="controller"></a>
## 10. The controller

Each action is one call to a flow. The flows are services, type-hinted like any other.

```php
// src/Controller/TaskController.php
namespace App\Controller;

use App\Entity\Task;
use App\Form\Mapper\CreateTaskMapper;
use App\Form\Mapper\UpdateTaskMapper;
use App\Form\TaskFilterType;
use App\Form\TaskForm;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Task\CompleteTask;
use App\Task\CompleteTaskHandler;
use App\Task\CreateTaskHandler;
use App\Task\DeleteTask;
use App\Task\DeleteTaskHandler;
use App\Task\TaskFilterHandler;
use App\Task\TaskSearchCriteria;
use App\Task\UpdateTaskHandler;
use Doctrine\ORM\EntityManagerInterface;
use MyVars\FormFlow\ActionFlow;
use MyVars\FormFlow\ConfirmFlow;
use MyVars\FormFlow\FormFlow;
use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\SearchFlow;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController extends AbstractController
{
    private function model(): FlowModel
    {
        return FlowModel::create('demo', 'task');
    }

    #[Route('/task', name: 'app_demo_task_index', methods: ['GET'])]
    public function index(
        Request $request,
        TaskRepository $repository,
        SearchFlow $flow,
        #[MapQueryString] TaskSearchCriteria $criteria = new TaskSearchCriteria(),
    ): Response {
        return $flow->search(
            request: $request,
            adapter: $repository->findByCriteria($criteria),
            criteria: $criteria,
            context: FlowContext::forSearch($this->model()),
        );
    }

    #[Route('/task/search/filter', name: 'app_demo_task_search_filter', methods: ['GET', 'POST'])]
    public function searchFilter(
        Request $request,
        TaskFilterHandler $handler,
        FormFlow $flow,
        #[MapQueryString] TaskSearchCriteria $criteria = new TaskSearchCriteria(),
    ): Response {
        return $flow->form(
            request: $request,
            formType: TaskFilterType::class,
            data: $criteria,
            mapper: static fn (TaskSearchCriteria $criteria): TaskSearchCriteria => $criteria,
            handler: $handler,
            context: FlowContext::forFilter($this->model()),
        );
    }

    #[Route('/task/new', name: 'app_demo_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateTaskMapper $mapper, CreateTaskHandler $handler, FormFlow $flow): Response
    {
        return $flow->form(
            request: $request,
            formType: TaskType::class,
            data: new TaskForm(),
            mapper: $mapper,
            handler: $handler,
            context: FlowContext::forCreate($this->model()),
        );
    }

    #[Route('/task/{id<\d+>}', name: 'app_demo_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        return $this->render('demo/task/show.html.twig', ['task' => $task]);
    }

    #[Route('/task/{id<\d+>}/edit', name: 'app_demo_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, UpdateTaskMapper $mapper, UpdateTaskHandler $handler, FormFlow $flow): Response
    {
        return $flow->form(
            request: $request,
            formType: TaskType::class,
            data: TaskForm::fromEntity($task),
            mapper: $mapper,
            handler: $handler,
            context: FlowContext::forUpdate($this->model())
                ->successRoute('app_demo_task_show', ['id' => $task->getId()]),
        );
    }

    #[Route('/task/{id<\d+>}/delete/confirm', name: 'app_demo_task_delete_confirm', methods: ['GET'])]
    public function deleteConfirm(Task $task, ConfirmFlow $flow): Response
    {
        return $flow->confirm(
            entity: $task,
            context: FlowContext::forDelete($this->model()),
        );
    }

    #[Route('/task/{id<\d+>}/delete', name: 'app_demo_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, DeleteTaskHandler $handler, ConfirmFlow $flow): Response
    {
        return $flow->execute(
            request: $request,
            command: new DeleteTask((int) $task->getId()),
            handler: $handler,
            context: FlowContext::forDelete($this->model()),
        );
    }

    #[Route('/task/{id<\d+>}/complete', name: 'app_demo_task_complete', methods: ['POST'])]
    public function complete(Request $request, Task $task, CompleteTaskHandler $handler, ActionFlow $flow): Response
    {
        return $flow->process(
            request: $request,
            command: new CompleteTask((int) $task->getId()),
            handler: $handler,
            context: FlowContext::forAction('app_demo_task_show', ['id' => $task->getId()])
                ->csrf('complete' . $task->getId()),
        );
    }

    #[Route('/task/{id<\d+>}/inline/title', name: 'app_demo_task_inline_title', methods: ['GET', 'POST'])]
    public function inlineTitle(Request $request, Task $task, InlineEditFlow $flow, EntityManagerInterface $em): Response
    {
        return $flow->handleField(
            request: $request,
            value: $task->getTitle(),
            onSave: function (?string $value) use ($task, $em): bool {
                $changed = $task->getTitle() !== $value;
                $task->rename((string) $value);
                $em->flush();

                return $changed;
            },
            context: InlineEditContext::create(
                frameId: 'inline-edit-task-' . $task->getId() . '-title',
                displayTemplate: 'demo/task/_inline_title.html.twig',
                entity: $task,
                property: 'title',
            ),
        );
    }
}
```

What each action relies on:

- **index:** `#[MapQueryString]` fills the criteria from the query string. `SearchFlow` paginates the
  repository's adapter and renders `demo/task/index.html.twig`.
- **searchFilter:** the [filter](#filter). The criteria are the form's data, so the form opens with the
  index's current values when you link to it with the index's query string.
- **new:** the handler's `RedirectTarget` sends the user to the new task. Without it, the flow would go to
  the context's success route, the index.
- **show:** not a flow. A page that only displays renders its template directly.
- **edit:** the success route is the task, known before the handler runs, so it is set on the context.
- **deleteConfirm, delete:** `ConfirmFlow` renders a confirmation page, then checks the CSRF token
  (`delete` followed by the task id) before calling the handler.
- **complete:** a state change with no form. It is `POST` only, and `->csrf()` makes `ActionFlow`
  check the posted token ([ActionFlow](flows.md#actionflow)).
- **inlineTitle:** one endpoint serves the title, its edit form and the save, inside a Turbo Frame.
  `property: 'title'` checks the value against `Task::$title`'s rules before `onSave` runs
  ([InlineEditFlow](flows.md#inlineeditflow)). It checks the property's own rules only, not the
  class-level `UniqueEntity`.

Three habits keep flow controllers easy to read: call the flows with named arguments, keep the
`FlowModel` in one private `model()` method, and keep each action to the one `return $flow->…` call,
with flashes and redirects left to the flow and the handler.

<a id="templates"></a>
## 11. The templates

The bundle's default templates already render the form pages. You write the ones that need to know
what a task looks like:

| Page | Template | Who writes it |
|------|----------|---------------|
| new | `shared/form_flow/create.html.twig` | the bundle: the whole form, a Save button, a link back to the index |
| edit | `shared/form_flow/update.html.twig` | the bundle |
| filter | `shared/form_flow/filter.html.twig` | the bundle |
| delete confirmation | `shared/form_flow/delete.html.twig` | the bundle: posts to the delete route with a token scoped to `delete` and the task id |
| index | `demo/task/index.html.twig` | you: the bundle's default shows only a count and paging, as it can't know the columns |
| show | `demo/task/show.html.twig` | you |
| inline title | `demo/task/_inline_title.html.twig` | you |

To change one of the bundle's pages for tasks only, add the template under `demo/task/` with the
operation's name (`demo/task/create.html.twig`). To change it for every resource, override it at its
`shared/form_flow/` path. The [template contract](templates.md) lists every template and its variables.

The index receives the page of results as `results`, a
[Pagerfanta](https://www.babdev.com/open-source/packages/pagerfanta/docs/4.x/usage) object, and the
route names as `routes`:

```twig
{# templates/demo/task/index.html.twig #}
<h1>{{ flowModelPlural }}</h1>

<form method="get" action="{{ path(routes.index) }}">
    <input type="search" name="query" value="{{ app.request.query.get('query') }}" aria-label="Search">
    <button type="submit">Search</button>
</form>

<p>
    <a href="{{ path(routes.filter, app.request.query.all) }}">Filter</a>
    <a href="{{ path(routes.new) }}">New task</a>
</p>

<table>
    <thead>
        <tr>
            <th><a href="{{ path(routes.index, app.request.query.all|merge({sort: 'title', page: 1})) }}">Title</a></th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        {% for task in results %}
            <tr>
                <td><a href="{{ path(routes.show, {id: task.id}) }}">{{ task.title }}</a></td>
                <td>{{ task.done ? 'Done' : 'Open' }}</td>
            </tr>
        {% else %}
            <tr><td colspan="2">No tasks found.</td></tr>
        {% endfor %}
    </tbody>
</table>

{% if results.haveToPaginate %}
    <p>
        {% if results.hasPreviousPage %}
            <a href="{{ path(routes.index, app.request.query.all|merge({page: results.previousPage})) }}">Previous</a>
        {% endif %}
        Page {{ results.currentPage }} of {{ results.nbPages }}
        {% if results.hasNextPage %}
            <a href="{{ path(routes.index, app.request.query.all|merge({page: results.nextPage})) }}">Next</a>
        {% endif %}
    </p>
{% endif %}
```

The show page is an ordinary page, so it extends the layout itself. "Mark done" is a form, not a link,
because it changes data; its token id matches the controller's `->csrf('complete' . $task->getId())`.

```twig
{# templates/demo/task/show.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ task.title }}{% endblock %}

{% block body %}
    {{ include('demo/task/_inline_title.html.twig') }}

    <p>Status: {{ task.done ? 'Done' : 'Open' }}</p>

    {% if not task.done %}
        <form method="post" action="{{ path('app_demo_task_complete', {id: task.id}) }}">
            <input type="hidden" name="_token" value="{{ csrf_token('complete' ~ task.id) }}">
            <button type="submit">Mark done</button>
        </form>
    {% endif %}

    <p>
        <a href="{{ path('app_demo_task_edit', {id: task.id}) }}">Edit</a>
        <a href="{{ path('app_demo_task_delete_confirm', {id: task.id}) }}">Delete</a>
        <a href="{{ path('app_demo_task_index') }}">All tasks</a>
    </p>
{% endblock %}
```

The inline title is the display half of the inline edit: a Turbo Frame with the controller's `frameId`,
holding the title and a link to the edit form. The link stays inside the frame, so Turbo swaps the
form in place of the title. `InlineEditFlow` renders this partial for display mode with the entity as
`task`, a name it takes from the class.

```twig
{# templates/demo/task/_inline_title.html.twig #}
<turbo-frame id="inline-edit-task-{{ task.id }}-title">
    <h1>{{ task.title }}</h1>
    <a href="{{ path('app_demo_task_inline_title', {id: task.id, edit: 1}) }}">Rename</a>
</turbo-frame>
```

<a id="try-it"></a>
## 12. Try it

```bash
symfony serve
```

Open `/task` and create a task. Then try each failure: an empty title (the form comes back with its
error), a title that's already taken (an error flash from the handler), and "Mark done" twice from two
tabs (the second gets "This task is already done.").

A form inside a Turbo Frame, such as one in a modal, needs one more piece of JavaScript before its
redirects work: [register the `redirect` stream action](installation.md#redirect-stream-action). Nothing
in this guide needs it.

<a id="test-it"></a>
## 13. Test it

The flows are tested in this package. Your tests check your wiring: the routes, the templates, and what
each handler's outcome looks like over HTTP. A functional test drives the app as a browser would, so the
CSRF tokens come from the pages that render them.

```bash
composer require --dev symfony/test-pack
```

```php
// tests/Controller/TaskControllerTest.php
namespace App\Tests\Controller;

use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TaskControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testCreateRedirectsToTheNewTask(): void
    {
        $this->client->request('GET', '/task/new');
        $this->client->submitForm('Save', ['task[title]' => 'Write the docs']);

        $task = $this->em->getRepository(Task::class)->findOneBy(['title' => 'Write the docs']);
        self::assertNotNull($task);
        self::assertResponseRedirects('/task/' . $task->getId(), 303);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Task created');
    }

    public function testCreateWithoutATitleRendersTheFormWith422(): void
    {
        $this->client->request('GET', '/task/new');
        $this->client->submitForm('Save', ['task[title]' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateWithATakenTitleShowsTheHandlersError(): void
    {
        $this->addTask('Write the docs');

        $this->client->request('GET', '/task/new');
        $this->client->submitForm('Save', ['task[title]' => 'Write the docs']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash-danger', 'There is already a task with this title.');
    }

    public function testMarkDoneRejectsAPostWithoutAToken(): void
    {
        $task = $this->addTask('Write the docs');

        $this->client->request('POST', '/task/' . $task->getId() . '/complete');

        self::assertResponseRedirects('/task/' . $task->getId(), 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-danger', 'Invalid CSRF token.');
        self::assertSelectorExists('button:contains("Mark done")');
    }

    public function testDeleteFromTheConfirmationPageGoesToTheIndex(): void
    {
        $task = $this->addTask('Write the docs');

        $this->client->request('GET', '/task/' . $task->getId() . '/delete/confirm');
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/task', 303);
        self::assertNull($this->em->getRepository(Task::class)->find($task->getId()));
    }

    public function testInlineSaveInAFrameAnswersWithARefreshStream(): void
    {
        $task = $this->addTask('Write the docs');
        $frame = ['HTTP_TURBO_FRAME' => 'inline-edit-task-' . $task->getId() . '-title'];

        $crawler = $this->client->request('GET', '/task/' . $task->getId() . '/inline/title?edit=1', server: $frame);
        $this->client->submit(
            $crawler->selectButton('Save')->form(),
            ['inline_field[value]' => 'Write better docs'],
            $frame + ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html'],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<turbo-stream action="refresh"', (string) $this->client->getResponse()->getContent());
    }

    private function addTask(string $title): Task
    {
        $task = new Task($title);
        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }
}
```

```bash
php bin/phpunit
```

What to check, and the status each case answers with, is in [testing your controllers](flows.md#testing).

## Next

- [Using the flows](flows.md): every flow's options, the responses, Turbo-aware redirects and flashes.
- [Template contract](templates.md): restyling the bundle's pages.
- A confirmation that isn't a delete (cancel, archive): [`forConfirm()`](flows.md#confirmflow).
- A form on another page, in its own frame: [embedded forms](flows.md#embedded-forms).
