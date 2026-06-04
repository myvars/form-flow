<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Double;

use MyVars\FormFlow\Contract\RedirectTargetInterface;
use MyVars\FormFlow\Contract\ResultInterface;

/**
 * Test adapter for the ResultInterface port.
 */
final readonly class StubResult implements ResultInterface
{
    private function __construct(
        private bool $ok,
        private ?string $message,
        private ?RedirectTargetInterface $redirect,
    ) {
    }

    public static function ok(?string $message = null, ?RedirectTargetInterface $redirect = null): self
    {
        return new self(true, $message, $redirect);
    }

    public static function fail(?string $message = null): self
    {
        return new self(false, $message, null);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function redirect(): ?RedirectTargetInterface
    {
        return $this->redirect;
    }
}
