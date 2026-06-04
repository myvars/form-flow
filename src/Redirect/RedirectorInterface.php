<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Redirect;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abstraction for redirects that works with Turbo and non‑Turbo requests.
 * Implementations decide whether to emit a Turbo stream or a classic redirect.
 */
interface RedirectorInterface
{
    /**
     * Redirect to $url.
     *
     * @param bool $refresh       When true with Turbo, enables smart navigation (compare paths)
     * @param bool $forceNavigate When true with Turbo, always navigate to URL (skip path comparison)
     */
    public function to(Request $request, string $url, bool $refresh = false, int $status = 303, bool $forceNavigate = false): Response;
}
