<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Contract;

use Symfony\Component\HttpFoundation\Request;

/**
 * FormFlow port for user feedback messages.
 *
 * The app supplies the adapter (e.g. App\Shared\UI\Http\FlashMessenger).
 */
interface FlasherInterface
{
    public function success(Request $request, ?string $message): void;

    public function warning(Request $request, ?string $message): void;

    public function error(Request $request, ?string $message): void;
}
