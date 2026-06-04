# Ports & adapters

FormFlow does not depend on your application's classes. Instead it defines four small **ports**
(interfaces in `MyVars\FormFlow\Contract\`) and expects the application to supply an **adapter** for
each. Symfony wires them up; you implement each one once.

This keeps the package upgradeable, testable and reusable without reaching into your domain, and means
your domain never has to import a vendor DTO.

| Port | What it represents | You usually already have this |
|------|--------------------|-------------------------------|
| [`ResultInterface`](#result) | the outcome of a command handler | your `Result` DTO |
| [`RedirectTargetInterface`](#redirect) | a forced redirect | a small value object |
| [`FlasherInterface`](#flasher) | user feedback messages | your flash helper |
| [`SearchCriteriaInterface`](#criteria) | paging/sort inputs | your search criteria base |

> **Single implementation:** if a port has exactly one implementation in your app, Symfony auto-aliases
> the interface to it and no configuration is needed. Explicit aliases (shown below) are only required
> when more than one candidate exists.

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

Keep your existing `Result` as it is and add the getters. Existing code that reads `$result->ok` or
calls `Result::ok()` is unaffected.

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

    // --- the port ---
    public function isOk(): bool { return $this->ok; }
    public function message(): ?string { return $this->message; }
    public function redirect(): ?RedirectTargetInterface { return $this->redirect; }
}
```

`ResultInterface` is never autowired (handlers hand back concrete instances), so it needs no DI config.

---

<a id="redirect"></a>
## `RedirectTargetInterface`

Returned inside a successful `Result` when a handler wants the flow to redirect somewhere specific
instead of the default success route (for example, to the newly created entity's show page).

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
        $this->add($request, 'danger', $message);   // 'danger' aligns with Bootstrap's alert class
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

The paging/sort inputs `SearchFlow` needs. Make your **existing** search criteria interface `extends` the
port: every criteria class, repository and reader you already have keeps working unchanged, and the flow
can type against the port:

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
port automatically. Your repositories keep returning a Pagerfanta adapter from `findByCriteria()`; the
flow handles pagination.
