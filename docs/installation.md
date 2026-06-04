# Installation & wiring

## 1. Require the package

```bash
composer require myvars/form-flow
```

### Installing from a path repository (local development)

While the package lives alongside your app rather than on Packagist, add it as a Composer
[path repository](https://getcomposer.org/doc/05-repositories.md#path):

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

For production, publish a tagged VCS repository (or private Packagist) instead of a path symlink.

## 2. Enable the bundle

Symfony Flex registers it automatically. Otherwise:

```php
// config/bundles.php
return [
    // ...
    MyVars\FormFlow\FormFlowBundle::class => ['all' => true],
];
```

The bundle registers the five flow services, the `TurboAwareRedirector`, the `AutoUpdateGuard` and the
`InlineFieldType`. They are autowired into your controllers by type-hint.

## 3. Provide the port adapters

The flows depend on four ports in `MyVars\FormFlow\Contract\`. Your app provides one implementation of
each; see **[Ports & adapters](adapters.md)** for full examples.

| Port | Your adapter |
|------|--------------|
| `ResultInterface` | the DTO your command handlers return |
| `RedirectTargetInterface` | a forced-redirect DTO |
| `FlasherInterface` | your flash-message helper |
| `SearchCriteriaInterface` | your search-criteria base interface `extends` this |

Symfony automatically aliases an interface to its single implementation, so a sole adapter usually needs
no configuration. If you have several implementations, alias explicitly:

```yaml
# config/services.yaml
services:
    MyVars\FormFlow\Contract\FlasherInterface: '@App\Shared\UI\Http\FlashMessenger'
```

## 4. Provide the templates

The bundle ships no Twig. Create the templates it renders (and the variables it passes) as described in
the **[Template contract](templates.md)** — `templates/shared/form_flow/base.html.twig` and the three
`inline_edit_*` templates.
