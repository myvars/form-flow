<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\Redirect;

use MyVars\FormFlow\Redirect\TurboAwareRedirector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TurboAwareRedirectorTest extends TestCase
{
    private TurboAwareRedirector $redirector;

    protected function setUp(): void
    {
        $this->redirector = new TurboAwareRedirector();
    }

    private function getContent(Response $response): string
    {
        $content = $response->getContent();
        self::assertIsString($content);

        return $content;
    }

    public function testTurboFrameHeaderReturnsStreamResponse(): void
    {
        $request = new Request();
        $request->headers->set('Turbo-Frame', 'frame-id');

        $response = $this->redirector->to($request, '/target');

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/vnd.turbo-stream.html', $response->headers->get('Content-Type'));
    }

    public function testTurboRequestWithoutRefreshEmitsRefreshStream(): void
    {
        $request = new Request();
        $request->headers->set('turbo-frame', 'f');

        $response = $this->redirector->to($request, '/any');

        self::assertStringContainsString('<turbo-stream action="refresh">', $this->getContent($response));
    }

    public function testTurboRequestWithRefreshAndDifferentPathEmitsRedirectStream(): void
    {
        $request = new Request();
        $request->headers->set('turbo-frame', 'f');
        $request->headers->set('referer', 'http://localhost/old-path');

        $response = $this->redirector->to($request, '/new-path', refresh: true);

        $content = $this->getContent($response);
        self::assertStringContainsString('<turbo-stream action="redirect"', $content);
        self::assertStringContainsString('url="/new-path"', $content);
    }

    public function testTurboRequestWithRefreshAndSamePathEmitsRefreshStream(): void
    {
        $request = new Request();
        $request->headers->set('turbo-frame', 'f');
        $request->headers->set('referer', 'http://localhost/same-path');

        $response = $this->redirector->to($request, '/same-path', refresh: true);

        self::assertStringContainsString('<turbo-stream action="refresh">', $this->getContent($response));
    }

    public function testForceNavigateEmitsRedirectStream(): void
    {
        $request = new Request();
        $request->headers->set('turbo-frame', 'f');

        $response = $this->redirector->to($request, '/target', forceNavigate: true);

        $content = $this->getContent($response);
        self::assertStringContainsString('<turbo-stream action="redirect"', $content);
        self::assertStringContainsString('url="/target"', $content);
    }

    public function testNonTurboRequestReturnsRedirectResponse(): void
    {
        $request = new Request();

        $response = $this->redirector->to($request, '/dest', status: 302);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/dest', $response->getTargetUrl());
    }

    public function testDefaultStatusIs303(): void
    {
        $request = new Request();

        $response = $this->redirector->to($request, '/dest');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(303, $response->getStatusCode());
    }
}
