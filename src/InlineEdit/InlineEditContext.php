<?php

namespace MyVars\FormFlow\InlineEdit;

/**
 * Context for inline edit operations.
 *
 * Contains all information needed to render the inline edit form
 * and the success response that replaces it.
 */
final readonly class InlineEditContext
{
    /**
     * @param string               $frameId             Turbo Frame ID (matches InlineEdit component)
     * @param string               $displayTemplate     Template to render on success (the InlineEdit wrapper)
     * @param string|null          $cancelUrl           URL to load display mode (derived from request if null)
     * @param object               $entity              The entity being edited
     * @param string               $entityVarName       Variable name for entity in template (e.g., "manufacturer")
     * @param array<string, mixed> $displayTemplateVars Additional variables for the display template
     * @param string|null          $successMessage      Flash message on success (null for no message)
     */
    private function __construct(
        public string $frameId,
        public string $displayTemplate,
        public ?string $cancelUrl,
        public object $entity,
        public string $entityVarName,
        public array $displayTemplateVars = [],
        public ?string $successMessage = 'Updated successfully',
    ) {
    }

    /**
     * Create a new inline edit context.
     *
     * @param string               $frameId             Turbo Frame ID (e.g., "inline-edit-manufacturer-xxx-name")
     * @param string               $displayTemplate     Template path (e.g., "catalog/manufacturer/_inline_name.html.twig")
     * @param object               $entity              The entity being edited
     * @param string|null          $cancelUrl           URL to load display mode (derived from request if null)
     * @param string|null          $entityVarName       Variable name for entity in template (auto-derived if null)
     * @param array<string, mixed> $displayTemplateVars Additional template variables
     * @param string|null          $successMessage      Flash message (null to disable)
     */
    public static function create(
        string $frameId,
        string $displayTemplate,
        object $entity,
        ?string $cancelUrl = null,
        ?string $entityVarName = null,
        array $displayTemplateVars = [],
        ?string $successMessage = 'Updated successfully',
    ): self {
        return new self(
            frameId: $frameId,
            displayTemplate: $displayTemplate,
            cancelUrl: $cancelUrl,
            entity: $entity,
            entityVarName: $entityVarName ?? self::deriveEntityVarName($entity),
            displayTemplateVars: $displayTemplateVars,
            successMessage: $successMessage,
        );
    }

    /**
     * Derive a template variable name from an entity class.
     *
     * E.g., App\Catalog\Domain\Model\Manufacturer\Manufacturer -> "manufacturer"
     */
    private static function deriveEntityVarName(object $entity): string
    {
        $className = new \ReflectionClass($entity)->getShortName();

        return lcfirst($className);
    }
}
