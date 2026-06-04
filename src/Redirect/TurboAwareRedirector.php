<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Redirect;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Turbo\TurboBundle;

/**
 * Turbo‑aware redirector that:
 *  - Detects Turbo requests via headers/format/accept.
 *  - Emits a Turbo stream refresh response when appropriate.
 *  - Navigates away when the target URL differs from the referer (e.g., delete from show page).
 *  - Falls back to a normal RedirectResponse otherwise.
 */
final readonly class TurboAwareRedirector implements RedirectorInterface
{
    private const string TURBO_STREAM_MIME = 'text/vnd.turbo-stream.html';

    /**
     * Redirect or produce a Turbo stream depending on the incoming request.
     * Keeps the `Content-Type` correct for Turbo so it processes the stream.
     */
    public function to(Request $request, string $url, bool $refresh = false, int $status = 303, bool $forceNavigate = false): Response
    {
        // Detect Turbo frame request via header.
        // Only use Turbo stream behavior when there's an actual frame context.
        // Without a frame, action="refresh" would refresh the current page (e.g., edit page)
        // instead of the parent page, so we fall back to a regular redirect.
        $hasTurboFrameHeader = $request->headers->has('turbo-frame') || $request->headers->has('Turbo-Frame');

        if ($hasTurboFrameHeader) {
            // Tell Symfony to emit Turbo Stream.
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            // Determine if we need to navigate away vs refresh in place.
            // - $forceNavigate = true: Always navigate (handler explicitly wants to redirect)
            // - $refresh = true: Smart navigation (compare paths, used by FlowContext::forDelete)
            // - Otherwise: Refresh in place
            $shouldNavigate = $forceNavigate || ($refresh && $this->shouldNavigateAway($request, $url));

            return new Response(
                $this->buildStream($shouldNavigate ? $url : null),
                Response::HTTP_OK,
                ['Content-Type' => self::TURBO_STREAM_MIME]
            );
        }

        // Regular (non-Turbo) fallback - emit a proper redirect so browser follows it.
        return new RedirectResponse($url, $status);
    }

    private function buildStream(?string $navigateUrl): string
    {
        if ($navigateUrl !== null) {
            return sprintf(
                '<turbo-stream action="redirect" url="%s"></turbo-stream>',
                htmlspecialchars($navigateUrl, ENT_QUOTES)
            );
        }

        return '<turbo-stream action="refresh"></turbo-stream>';
    }

    /**
     * Determine if we need to navigate away from the current page.
     *
     * Compares the target URL path with the referer path. If they differ,
     * we should navigate to the new URL instead of refreshing in place.
     * This handles cases like deleting an entity from its show page.
     */
    private function shouldNavigateAway(Request $request, string $targetUrl): bool
    {
        $referer = $request->headers->get('referer');
        if ($referer === null || $referer === '') {
            return false;
        }

        $refererPath = parse_url($referer, PHP_URL_PATH);
        $targetPath = parse_url($targetUrl, PHP_URL_PATH);

        // If we can't parse the paths, default to refresh behavior
        if ($refererPath === false || $refererPath === null || $targetPath === false || $targetPath === null) {
            return false;
        }

        // Navigate away if the paths are different
        return $refererPath !== $targetPath;
    }
}
