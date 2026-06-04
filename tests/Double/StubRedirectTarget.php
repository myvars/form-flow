<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Double;

use MyVars\FormFlow\Contract\RedirectTargetInterface;

/**
 * Test adapter for the RedirectTargetInterface port.
 */
final readonly class StubRedirectTarget implements RedirectTargetInterface
{
    /**
     * @param array<string, mixed> $params
     */
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

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->params;
    }

    public function status(): int
    {
        return $this->status;
    }
}
