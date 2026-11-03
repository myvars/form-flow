# Installation & wiring

## 1. Require the package

```bash
composer require myvars/form-flow
```

The package is on [Packagist](https://packagist.org/packages/myvars/form-flow).

### Working on the package and an app together

To try a change to the package in an app before it is released, point the app at a local checkout with a
Composer [path repository](https://getcomposer.org/doc/05-repositories.md#path):

```jsonc
// composer.json
{
    "repositories": [
        { "type": "path", "url": "../form-flow" }
    ],
    "require": {
        "myvars/form-flow": "@dev"
    }
}
```

Composer symlinks the package into `vendor/`. **Docker note:** the symlink points outside your project
mount, so it dangles inside containers. Mount the package at the symlink target in your dev/test
services:

```yaml
# compose.override.yaml
services:
    php:
        volumes:
            - ../form-flow:/form-flow   # vendor/myvars/form-flow -> ../../../form-flow
```

Remove the path repository and require a released version again before you commit the app.

## 2. Enable the bundle

Symfony Flex registers it automatically. Otherwise:

```php
// config/bundles.php
return [
    // ...
    MyVars\FormFlow\FormFlowBundle::class => ['all' => true],
];
```

The bundle registers the five flow services, the `TurboAwareRedirector`
([Turbo-aware redirects](flows.md#turbo-aware-redirects)), the `AutoUpdateGuard`
([auto-update submits](flows.md#auto-update-submits)) and the `InlineFieldType`. They are autowired into
your controllers by type-hint.

## 3. Provide the port adapters

The flows depend on four ports in `MyVars\FormFlow\Contract\`. Your app provides one implementation of
each; see **[Ports & adapters](adapters.md)** for full examples.

| Port | Your adapter |
|------|--------------|
| `ResultInterface` | the DTO your command handlers return |
| `RedirectTargetInterface` | a forced-redirect DTO |
| `FlasherInterface` | your flash-message helper |
| `SearchCriteriaInterface` | the base class of your search criteria |

The flows inject only `FlasherInterface`. Alias it to your adapter; Symfony doesn't create the alias by
itself, even for a single implementation:

```yaml
# config/services.yaml
services:
    # ...
    MyVars\FormFlow\Contract\FlasherInterface: '@App\Flow\FlashMessenger'
```

The other three need no configuration.

## 4. Templates (optional)

The bundle ships **design-neutral default templates**, so the flows render out of the box — nothing is
required here. To style them, override any by providing your own at the same path
(`templates/shared/form_flow/...`); your app's `templates/` wins. See the **[templates guide](templates.md)**
for the full list and the variables each receives.

<a id="redirect-stream-action"></a>
## 5. Register the `redirect` stream action

For a form submitted inside a Turbo Frame (a modal, say), `TurboAwareRedirector` can answer with
`<turbo-stream action="redirect" url="…">` to navigate the whole page
([Turbo-aware redirects](flows.md#turbo-aware-redirects)). `redirect` is not one of Turbo's built-in
[stream actions](https://turbo.hotwired.dev/reference/streams), and Symfony UX Turbo doesn't add it.
Without your own, Turbo rejects the stream with an `unknown action` error: the flash is set but the page
doesn't move. Register it once in your JavaScript entry point (Turbo 7.2+, which exports `StreamActions`):

```js
// assets/app.js
import { StreamActions, visit } from '@hotwired/turbo';

StreamActions.redirect = function () {
    visit(this.getAttribute('url'), { action: 'advance' });
};
```

Keep it a `function`, not an arrow function: Turbo calls it with the `<turbo-stream>` element as `this`.
