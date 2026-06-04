<?php

namespace MyVars\FormFlow\View;

/**
 * Small immutable bag of Twig variables used across flow templates..
 */
final readonly class TemplateContext
{
    public function __construct(
        public string $flowModel,
        public string $flowOperation,
        public string $template,
        public ?FlowRoutes $routes = null,
        public ?string $flowModelPlural = null,
    ) {
    }

    /**
     * Create a context from a FlowModel value object.
     */
    public static function from(FlowModel $model, string $operation, ?string $template = null, ?FlowRoutes $routes = null): self
    {
        return new self(
            flowModel: $model->displayName,
            flowOperation: $operation,
            template: $template ?? $model->template($operation),
            routes: $routes ?? $model->routes,
            flowModelPlural: $model->plural(),
        );
    }

    /**
     * Export keys expected by Twig templates.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'flowModel' => $this->flowModel,
            'flowOperation' => $this->flowOperation,
            'template' => $this->template,
            'routes' => $this->routes,
            'flowModelPlural' => $this->flowModelPlural,
        ];
    }
}
