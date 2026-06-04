<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Contract;

/**
 * FormFlow port for a forced redirect target returned by a handler.
 *
 * The app supplies the adapter (e.g. App\Shared\Application\RedirectTarget).
 */
interface RedirectTargetInterface
{
    public function route(): string;

    /**
     * @return array<string, mixed>
     */
    public function params(): array;

    public function status(): int;
}
