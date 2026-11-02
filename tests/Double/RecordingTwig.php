<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Double;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Test Twig environment: records the last template rendered and its variables,
 * and returns fixed HTML instead of rendering.
 */
final class RecordingTwig extends Environment
{
    public ?string $template = null;

    /** @var array<string, mixed> */
    public array $vars = [];

    public function __construct(private readonly string $html = '<html></html>')
    {
        parent::__construct(new ArrayLoader());
    }

    /**
     * @param string               $name
     * @param array<string, mixed> $context
     */
    public function render($name, array $context = []): string
    {
        $this->template = $name;
        $this->vars = $context;

        return $this->html;
    }
}
