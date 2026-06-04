# Template contract

The bundle ships **no Twig**. This is on purpose: your buttons, your cards, your dialogs, your Tailwind
classes — a vendor package has no business shipping any of that. Instead the flows render a handful of
template *paths* that your app provides, and pass a documented set of variables. Implement these once
and every flow has a consistent UI.

Think of it as an interface, but for markup.

## Required templates

| Path | Rendered by | Variables |
|------|-------------|-----------|
| `shared/form_flow/base.html.twig` | `FormFlow`, `ConfirmFlow`, `SearchFlow` | see below |
| `shared/form_flow/inline_edit_display.html.twig` | `InlineEditFlow` (display) | `context` |
| `shared/form_flow/inline_edit_form.html.twig` | `InlineEditFlow` (edit) | `form`, `context`, `cancelUrl` |
| `shared/form_flow/inline_edit_success.stream.html.twig` | `InlineEditFlow` (saved) | `context` |

## Variables passed to `base.html.twig`

Always present:

| Variable | Type | Meaning |
|----------|------|---------|
| `flowModel` | `string` | display name, e.g. `"Task"` |
| `flowModelPlural` | `string` | plural display name, e.g. `"Tasks"` |
| `flowOperation` | `string` | one of `create`, `update`, `delete`, `filter`, `action`, `index` |
| `template` | `string` | the operation-specific template to include (e.g. `demo/task/create.html.twig`) |
| `routes` | `FlowRoutes` | route names: `routes.index`, `.new`, `.show`, `.delete`, `.deleteConfirm`, `.filter` |

Added per flow:

| Variable | Added by | Type |
|----------|----------|------|
| `form` | `FormFlow` | `FormView` |
| `result` | `FormFlow` (the bound data), `ConfirmFlow` (the entity) | mixed |
| `flowBackLink` | `FormFlow` | `?string` |
| `flowAllowDelete` | `FormFlow` | `bool` |
| `results` | `SearchFlow` | `Pagerfanta` |
| `confirmKey` | `ConfirmFlow` | `string` — CSRF key for the confirm dialog |

## A minimal `base.html.twig`

`base` is a thin shell: it sets up the page chrome and `include`s the operation-specific template the
controller chose (or the one `FlowModel` derived). Wire it to your own layout and components.

```twig
{# templates/shared/form_flow/base.html.twig #}
{% extends 'layout.html.twig' %}

{% block title %}{{ flowModel }}{% endblock %}

{% block body %}
    <turbo-frame id="body">
        {% if flowBackLink is defined and flowBackLink %}
            <a href="{{ flowBackLink }}" class="back-link">&larr; Back</a>
        {% endif %}

        {# Hand off to the operation-specific template with everything it needs. #}
        {{ include(template) }}
    </turbo-frame>
{% endblock %}
```

Your per-operation templates (`create.html.twig`, `index.html.twig`, `delete.html.twig`, …) render the
actual `form`, `results` table, or confirmation prompt. For the confirmation prompt, scope the CSRF
token to the action with `confirmKey`:

```twig
{# demo/task/delete.html.twig — rendered for the confirm step #}
<form method="post" action="{{ path(routes.delete, { id: result.id }) }}">
    <input type="hidden" name="_token" value="{{ csrf_token(confirmKey ~ result.id) }}">
    <p>Delete <strong>{{ result.title }}</strong>? This cannot be undone (the usual ominous warning).</p>
    <button type="submit">Delete</button>
</form>
```

> The token id must be `confirmKey ~ entity.id` — that's exactly what `ConfirmFlow::execute()` validates.
> Use the same `confirmKey` the context was built with (`delete` by default, or your custom key from
> `forConfirm()`).

## Inline-edit templates

These are small Turbo-Frame fragments. `context` is the `InlineEditContext`, so the entity is available
as `context.entity` (or under your `entityVarName`).

```twig
{# shared/form_flow/inline_edit_form.html.twig #}
<turbo-frame id="{{ context.frameId }}">
    {{ form_start(form, { action: cancelUrl }) }}
        {{ form_widget(form.value) }}
        <button type="submit">Save (Enter)</button>
        <a href="{{ cancelUrl }}" data-turbo-frame="{{ context.frameId }}">Cancel</a>
    {{ form_end(form) }}
</turbo-frame>
```

```twig
{# shared/form_flow/inline_edit_success.stream.html.twig #}
<turbo-stream action="replace" target="{{ context.frameId }}">
    <template>{{ include(context.displayTemplate, context.displayTemplateVars) }}</template>
</turbo-stream>
```

That's the whole contract. Style it however you like — the package will never know, and never judge.
