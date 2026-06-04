# Ports & adapters

FormFlow refuses to know anything about your application. No peeking at your `Result` class, no opinions
about your flash messages. Instead it defines four tiny **ports** (interfaces in
`MyVars\FormFlow\Contract\`) and politely asks you to supply an **adapter** for each. Symfony wires them
up; you write maybe forty lines, once.

Why bother? Because it means the package can be upgraded, tested and reused without ever reaching into
your domain — and your domain never has to import a vendor's DTO. Good fences, good neighbours.

| Port | What it represents | You usually already have this |
|------|--------------------|-------------------------------|
| [`ResultInterface`](#result) | the outcome of a command handler | your `Result` DTO |
| [`RedirectTargetInterface`](#redirect) | a forced redirect | a small value object |
| [`FlasherInterface`](#flasher) | user feedback messages | your flash helper |
| [`SearchCriteriaInterface`](#criteria) | paging/sort inputs | your search criteria base |

> **The lazy path:** if a port has exactly one implementation in your app, Symfony auto-aliases the
> interface to it and you configure *nothing*. Explicit aliases (shown below) are only needed when you
> have more than one candidate and Symfony, quite reasonably, refuses to guess.

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

The trick: keep your existing `Result` exactly as it is and just *add* the getters. Nothing that already
reads `$result->ok` or calls `Result::ok()` needs to change.

```php
use MyVars\FormFlow\Contract\RedirectTargetInterface;
use MyVars\FormFlow\Contract\ResultInterface;

final readonly class Result implements ResultInterface
{
    public function __construct(
        public bool $ok,
        public ?string $message = null,
        public mixed $payload = null,
        public ?RedirectTarget $redirect = null,
    ) {
    }

    public static function ok(?string $message = null, mixed $payload = null, ?RedirectTarget $redirect = null): self
    {
        return new self(true, $message, $payload, $redirect);
    }

    public static function fail(?string $message = null, mixed $payload = null): self
    {
        return new self(false, $message, $payload);
    }

    // --- the port, satisfied without disturbing anything above ---
    public function isOk(): bool { return $this->ok; }
    public function message(): ?string { return $this->message; }
    public function redirect(): ?RedirectTargetInterface { return $this->redirect; }
}
```

`ResultInterface` is never autowired (handlers hand back concrete instances), so it needs no DI config.

---

<a id="redirect"></a>
## `RedirectTargetInterface`

Returned inside a successful `Result` when a handler wants the flow to land somewhere specific instead of
the default success route — "you created a thing, now go look at the thing."

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
use MyVars\FormFlow\Contract\RedirectTargetInterface;

final readonly class RedirectTarget implements RedirectTargetInterface
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public string $route,
        public array $params = [],
        public int $redirectStatus = 303,
    ) {
    }

    public function route(): string { return $this->route; }
    /** @return array<string, mixed> */
    public function params(): array { return $this->params; }
    public function status(): int { return $this->redirectStatus; }
}
```

---

<a id="flasher"></a>
## `FlasherInterface`

How the flows tell your user that things went well (or didn't). Three methods, `null` messages ignored:

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
use MyVars\FormFlow\Contract\FlasherInterface;
use Symfony\Component\HttpFoundation\Request;

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
        $this->add($request, 'danger', $message);   // Bootstrap calls red "danger"; we don't argue
    }

    private function add(Request $request, string $type, ?string $message): void
    {
        if ($message !== null) {
            /** @var \Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface $session */
            $session = $request->getSession();
            $session->getFlashBag()->add($type, $message);
        }
    }
}
```

If it's your only flasher, you're done. Otherwise, point the port at it:

```yaml
# config/services.yaml
services:
    MyVars\FormFlow\Contract\FlasherInterface: '@App\Shared\UI\Http\FlashMessenger'
```

---

<a id="criteria"></a>
## `SearchCriteriaInterface`

The paging/sort inputs `SearchFlow` needs. The elegant move here is to make your **existing** search
criteria interface `extends` the port — every criteria class, repository and reader you already have
keeps working untouched, and the flow can type against the port:

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

```php
namespace App\Shared\Application\Search;

use MyVars\FormFlow\Contract\SearchCriteriaInterface as FormFlowSearchCriteriaInterface;

interface SearchCriteriaInterface extends FormFlowSearchCriteriaInterface
{
    // your app's own extra criteria methods can live here too
}
```

Your concrete criteria (e.g. `TaskSearchCriteria`) already implement this interface, so they satisfy the
port for free. Your repositories keep returning a Pagerfanta adapter from `findByCriteria()`; the flow
does the rest.
