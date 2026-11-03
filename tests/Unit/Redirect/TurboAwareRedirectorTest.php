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

    public function testFrameSubmissionReturnsStreamResponse(): void
    {
        $request = $this->frameSubmission();

        $response = $this->redirector->to($request, '/target');

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/vnd.turbo-stream.html', $response->headers->get('Content-Type'));
    }

    public function testTurboRequestWithoutRefreshEmitsRefreshStream(): void
    {
        $request = $this->frameSubmission();

        $response = $this->redirector->to($request, '/any');

        self::assertStringContainsString('<turbo-stream action="refresh">', $this->getContent($response));
    }

    public function testTurboRequestWithRefreshAndDifferentPathEmitsRedirectStream(): void
    {
        $request = $this->frameSubmission();
        $request->headers->set('referer', 'http://localhost/old-path');

        $response = $this->redirector->to($request, '/new-path', refresh: true);

        $content = $this->getContent($response);
        self::assertStringContainsString('<turbo-stream action="redirect"', $content);
        self::assertStringContainsString('url="/new-path"', $content);
    }

    public function testTurboRequestWithRefreshAndSamePathEmitsRefreshStream(): void
    {
        $request = $this->frameSubmission();
        $request->headers->set('referer', 'http://localhost/same-path');

        $response = $this->redirector->to($request, '/same-path', refresh: true);

        self::assertStringContainsString('<turbo-stream action="refresh">', $this->getContent($response));
    }

    public function testForceNavigateEmitsRedirectStream(): void
    {
        $request = $this->frameSubmission();

        $response = $this->redirector->to($request, '/target', forceNavigate: true);

        $content = $this->getContent($response);
        self::assertStringContainsString('<turbo-stream action="redirect"', $content);
        self::assertStringContainsString('url="/target"', $content);
    }

    public function testFrameNavigationWithoutStreamAcceptReturnsRedirectResponse(): void
    {
        // A link or GET form inside a frame: Turbo follows the redirect and renders the target in the frame.
        $request = Request::create('/index?page=99');
        $request->headers->set('Turbo-Frame', 'f');
        $request->headers->set('Accept', 'text/html, application/xhtml+xml');

        $response = $this->redirector->to($request, '/index?page=1', forceNavigate: true);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/index?page=1', $response->getTargetUrl());
    }

    public function testFrameRequestWithoutStreamAcceptReturnsRedirectResponseWhateverTheMethod(): void
    {
        $request = Request::create('/form', 'POST');
        $request->headers->set('Turbo-Frame', 'f');

        $response = $this->redirector->to($request, '/target', refresh: true);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/target', $response->getTargetUrl());
    }

    public function testFrameNavigationOptedIntoStreamsReturnsStreamResponse(): void
    {
        // A GET link or form with data-turbo-stream.
        $request = Request::create('/index');
        $request->headers->set('Turbo-Frame', 'f');
        $request->headers->set('Accept', 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml');

        $response = $this->redirector->to($request, '/target');

        self::assertSame('text/vnd.turbo-stream.html', $response->headers->get('Content-Type'));
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

    /**
     * A form submitted inside a frame, with the headers Turbo sends.
     */
    private function frameSubmission(): Request
    {
        $request = Request::create('/form', 'POST');
        $request->headers->set('Turbo-Frame', 'f');
        $request->headers->set('Accept', 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml');

        return $request;
    }

    private function getContent(Response $response): string
    {
        $content = $response->getContent();
        self::assertIsString($content);

        return $content;
    }
}
