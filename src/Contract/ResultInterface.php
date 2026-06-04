<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Contract;

/**
 * FormFlow port for the outcome of a command handler.
 *
 * Flows read results exclusively through these getters so they never depend on
 * a concrete app DTO. The app supplies the adapter (e.g. App\Shared\Application\Result).
 */
interface ResultInterface
{
    public function isOk(): bool;

    public function message(): ?string;

    public function redirect(): ?RedirectTargetInterface;
}
