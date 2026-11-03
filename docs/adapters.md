# Ports & adapters

FormFlow does not depend on your application's classes. Instead it defines four small **ports**
(interfaces in `MyVars\FormFlow\Contract\`) and expects the application to supply an **adapter** for
each. You write each one once; Symfony wires them up.

This keeps the package upgradeable, testable and reusable without reaching into your domain, and means
your domain never has to import a vendor DTO.

| Port | What it represents | Adapter on this page |
|------|--------------------|----------------------|
| [`ResultInterface`](#result) | the outcome of a command handler | `App\Flow\Result` |
| [`RedirectTargetInterface`](#redirect) | a forced redirect | `App\Flow\RedirectTarget` |
| [`FlasherInterface`](#flasher) | user feedback messages | `App\Flow\FlashMessenger` |
| [`SearchCriteriaInterface`](#criteria) | paging/sort inputs | `App\Flow\SearchCriteria` |

Each block below is a whole file, at the path in its first line; add `<?php` at the top. The
namespace is only a suggestion. If your app already has a result DTO, a flash helper or search
criteria, see [Adding the ports to existing classes](#existing-classes) instead.

The flows inject only one port, `FlasherInterface`, and it needs an alias in `config/services.yaml`
([below](#flasher-alias)). Handlers create results and redirect targets themselves, and controllers
receive search criteria from the request, so the other three need no configuration.

---

<a id="result"></a>
## `ResultInterface`

What your command handlers return. Three getters — is it ok, an optional message, an optional forced
redirect:

```php
interface ResultInterface
{
    public function isOk(): bool;
    public function message(): ?string;
    public function redirect(): ?RedirectTargetInterface;
}
```

```php
// src/Flow/Result.php
namespace App\Flow;

use MyVars\FormFlow\Contract\RedirectTargetInterface;
use MyVars\FormFlow\Contract\ResultInterface;

final readonly class Result implements ResultInterface
{
    private function __construct(
        private bool $ok,
        private ?string $message,
        private ?RedirectTarget $redirect,
    ) {
    }

    public static function ok(?string $message = null, ?RedirectTarget $redirect = null): self
    {
        return new self(true, $message, $redirect);
    }

    public static function fail(?string $message = null): self
    {
        return new self(false, $message, null);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function redirect(): ?RedirectTargetInterface
    {
        return $this->redirect;
    }
}
```

A handler returns `Result::ok('Task created')` or `Result::fail('Task not found.')`; the flows flash the
message ([flashes](flows.md#flashes)). Add properties the flows don't read, such as a payload for a
non-HTTP caller, as your app needs them.

`ResultInterface` is never autowired (handlers hand back concrete instances), so it needs no DI config.

---

<a id="redirect"></a>
## `RedirectTargetInterface`

Returned inside a `Result` when a handler wants the flow to redirect somewhere specific instead of the
success route, for example to the newly created entity's show page. `FormFlow` follows it only on
success, `ActionFlow` on either outcome ([redirect precedence](flows.md#redirect-precedence)).

```php
interface RedirectTargetInterface
{
    public function route(): string;
    /** @return array<string, mixed> */
    public function params(): array;
    public function status(): int;
}
```

```php
// src/Flow/RedirectTarget.php
namespace App\Flow;

use MyVars\FormFlow\Contract\RedirectTargetInterface;

final readonly class RedirectTarget implements RedirectTargetInterface
{
    /** @param array<string, mixed> $params */
    public function __construct(
        private string $route,
        private array $params = [],
        private int $status = 303,
    ) {
    }

    public function route(): string
    {
        return $this->route;
    }

    /** @return array<string, mixed> */
    public function params(): array
    {
        return $this->params;
    }

    public function status(): int
    {
        return $this->status;
    }
}
```

---

<a id="flasher"></a>
## `FlasherInterface`

Used by the flows to surface success, warning and error messages to the user. Three methods; `null`
messages are ignored:

```php
interface FlasherInterface
{
    public function success(Request $request, ?string $message): void;
    public function warning(Request $request, ?string $message): void;
    public function error(Request $request, ?string $message): void;
}
```

A typical Symfony adapter wraps the session flash bag:

```php
// src/Flow/FlashMessenger.php
namespace App\Flow;

use MyVars\FormFlow\Contract\FlasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class FlashMessenger implements FlasherInterface
{
    public function success(Request $request, ?string $message): void
    {
        $this->add($request, 'success', $message);
    }

    public function warning(Request $request, ?string $message): void
    {
        $this->add($request, 'warning', $message);
    }

    public function error(Request $request, ?string $message): void
    {
        $this->add($request, 'danger', $message);   // the flash type is yours to choose
    }

    private function add(Request $request, string $type, ?string $message): void
    {
        if ($message !== null) {
            /** @var FlashBagAwareSessionInterface $session */
            $session = $request->getSession();
            $session->getFlashBag()->add($type, $message);
        }
    }
}
```

<a id="flasher-alias"></a>
Point the port at it in `config/services.yaml`, under `services:`:

```yaml
# config/services.yaml
services:
    # ...
    MyVars\FormFlow\Contract\FlasherInterface: '@App\Flow\FlashMessenger'
```

Symfony doesn't create this alias by itself, even when `FlashMessenger` is the only implementation:
without it, any action that uses a flow fails with "Cannot autowire service". Your layout renders the
flashes ([getting started](getting-started.md#layout) shows one way).

---

<a id="criteria"></a>
## `SearchCriteriaInterface`

The paging/sort inputs `SearchFlow` needs:

```php
interface SearchCriteriaInterface
{
    public function getQuery(): ?string;
    public function getSort(): string;
    public function getSortDirection(): string;
    public function getLimit(): int;
    public function getPage(): int;
}
```

One base class serves every index. The controller fills it from the query string with
[`#[MapQueryString]`](https://symfony.com/doc/current/controller.html#mapping-the-whole-query-string), so
its properties are the query parameters. The getters are what the flow and your repository read, and
they only ever return safe values: a sort outside the whitelist falls back to the first entry, and the
limit is capped.

```php
// src/Flow/SearchCriteria.php
namespace App\Flow;

use MyVars\FormFlow\Contract\SearchCriteriaInterface;

abstract class SearchCriteria implements SearchCriteriaInterface
{
    /** The fields an index may sort by. The first is the default. */
    protected const array SORTS = ['id'];

    private const int MAX_LIMIT = 50;

    public ?string $query = null;

    public ?string $sort = null;

    public ?string $sortDirection = null;

    public int $page = 1;

    public int $limit = 10;

    public function getQuery(): ?string
    {
        $query = trim((string) $this->query);

        return $query === '' ? null : $query;
    }

    public function getSort(): string
    {
        return in_array($this->sort, static::SORTS, true) ? (string) $this->sort : static::SORTS[0];
    }

    public function getSortDirection(): string
    {
        return strtoupper((string) $this->sortDirection) === 'DESC' ? 'DESC' : 'ASC';
    }

    public function getPage(): int
    {
        return max(1, $this->page);
    }

    public function getLimit(): int
    {
        return min(max(1, $this->limit), self::MAX_LIMIT);
    }
}
```

A value that doesn't fit its property's type, such as `?page=abc`, is refused by `#[MapQueryString]` with
a 404 before the flow runs.

Each index extends it with its own sort fields and filters (`TaskSearchCriteria` in
[getting started](getting-started.md#criteria)). Because `getSort()` only returns a whitelisted field,
a repository can put it in a query's `ORDER BY`. Your repository's `findByCriteria()` reads the criteria
and returns a Pagerfanta adapter; the flow handles pagination.

---

<a id="existing-classes"></a>
## Adding the ports to existing classes

An app that already has these classes can implement the ports on them rather than adding new ones.

- **A result DTO:** keep it and add the three getters. Code that reads its own properties or factory
  methods is unaffected.
- **A redirect value object or a flash helper:** add the interface and its methods the same way.
- **A search criteria interface:** make it `extends` the port. Every criteria class, repository and
  reader you already have keeps working unchanged, and the flow can type against the port:

```php
namespace App\Search;

use MyVars\FormFlow\Contract\SearchCriteriaInterface as FormFlowSearchCriteriaInterface;

interface SearchCriteriaInterface extends FormFlowSearchCriteriaInterface
{
    // your app's own extra criteria methods can live here too
}
```

The port's method names and return types must match exactly; add a getter where yours differs.
