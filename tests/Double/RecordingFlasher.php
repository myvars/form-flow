<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Double;

use MyVars\FormFlow\Contract\FlasherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test adapter for the FlasherInterface port: records messages in memory
 * (null messages are skipped, mirroring a real flash messenger).
 */
final class RecordingFlasher implements FlasherInterface
{
    /** @var list<string> */
    public array $successes = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $errors = [];

    public function success(Request $request, ?string $message): void
    {
        if ($message !== null) {
            $this->successes[] = $message;
        }
    }

    public function warning(Request $request, ?string $message): void
    {
        if ($message !== null) {
            $this->warnings[] = $message;
        }
    }

    public function error(Request $request, ?string $message): void
    {
        if ($message !== null) {
            $this->errors[] = $message;
        }
    }
}
