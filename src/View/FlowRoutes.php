<?php

namespace MyVars\FormFlow\View;

/**
 * Typed bag of route names for a flow, derived by convention from the model
 * prefix but individually overridable via with().
 */
final readonly class FlowRoutes
{
    public function __construct(
        public ?string $index = null,
        public ?string $new = null,
        public ?string $show = null,
        public ?string $delete = null,
        public ?string $deleteConfirm = null,
        public ?string $filter = null,
    ) {
    }

    /** Derive all route names from a shared prefix (e.g. "app_catalog_product"). */
    public static function fromPrefix(string $prefix): self
    {
        return new self(
            index: $prefix . '_index',
            new: $prefix . '_new',
            show: $prefix . '_show',
            delete: $prefix . '_delete',
            deleteConfirm: $prefix . '_delete_confirm',
            filter: $prefix . '_search_filter',
        );
    }

    /** Return a copy with selectively overridden route names. */
    public function with(
        ?string $index = null,
        ?string $new = null,
        ?string $show = null,
        ?string $delete = null,
        ?string $deleteConfirm = null,
        ?string $filter = null,
    ): self {
        return new self(
            index: $index ?? $this->index,
            new: $new ?? $this->new,
            show: $show ?? $this->show,
            delete: $delete ?? $this->delete,
            deleteConfirm: $deleteConfirm ?? $this->deleteConfirm,
            filter: $filter ?? $this->filter,
        );
    }
}
