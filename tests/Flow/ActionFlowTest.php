<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\ActionFlow;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Double\StubRedirectTarget;
use MyVars\FormFlow\Tests\Double\StubResult;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ActionFlowTest extends TestCase
{
    private function handlerOk(?string $msg = 'Done', ?StubRedirectTarget $rt = null): callable
    {
        return fn (object $cmd): StubResult => StubResult::ok($msg, $rt);
    }

    private function handlerFail(?string $msg = 'Failed'): callable
    {
        return fn (object $cmd): StubResult => StubResult::fail($msg);
    }

    /** Without FlowContext::csrf() the flow must not look at any token. */
    private function csrfNeverChecked(): CsrfTokenManagerInterface
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->never())->method('isTokenValid');

        return $csrf;
    }

    private function csrfExpecting(string $id, string $value, bool $valid): CsrfTokenManagerInterface
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t): bool => $t->getId() === $id && $t->getValue() === $value))
            ->willReturn($valid);

        return $csrf;
    }

    public function testProcessSuccessAddsSuccessFlashAndRedirectsToSuccessRoute(): void
    {
        $request = Request::create('/order', 'POST');
        $context = FlowContext::forCreate(FlowModel::simple('order_item'));

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_item_index', [])
            ->willReturn('/gen/app_order_item_index');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_order_item_index', false, 303)
            ->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfNeverChecked());

        $response = $flow->process($request, new \stdClass(), $this->handlerOk('Saved'), $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Saved'], $flasher->successes);
        self::assertSame([], $flasher->errors);
    }

    public function testProcessFailureAddsErrorFlashAndRedirectsToSuccessRoute(): void
    {
        $request = Request::create('/order', 'POST');
        $context = FlowContext::forCreate(FlowModel::simple('order_item'));

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')->willReturn('/gen/app_order_item_index');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfNeverChecked());

        $response = $flow->process($request, new \stdClass(), $this->handlerFail('Failed'), $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Failed'], $flasher->errors);
        self::assertSame([], $flasher->successes);
    }

    public function testProcessSuccessWithRedirectTargetOverridesSuccessRoute(): void
    {
        $request = Request::create('/order', 'POST');
        $context = FlowContext::forCreate(FlowModel::simple('order_item'));

        $target = new StubRedirectTarget('app_order_item_show', ['id' => 5], 302);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_item_show', ['id' => 5])
            ->willReturn('/gen/app_order_item_show?id=5');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_order_item_show?id=5', false, 302, true)
            ->willReturn(new Response('', 302));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfNeverChecked());

        $response = $flow->process($request, new \stdClass(), $this->handlerOk('Shown', $target), $context);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['Shown'], $flasher->successes);
    }

    public function testProcessSuccessWithNullMessageDoesNotFlash(): void
    {
        $request = Request::create('/order', 'POST');
        $context = FlowContext::forCreate(FlowModel::simple('order_item'));

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/gen/app_order_item_index');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfNeverChecked());

        $response = $flow->process($request, new \stdClass(), $this->handlerOk(null), $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame([], $flasher->successes);
        self::assertSame([], $flasher->errors);
    }

    public function testProcessWithForActionFactoryWorks(): void
    {
        $request = Request::create('/order', 'POST');
        $context = FlowContext::forAction('app_order_show', ['id' => 1]);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_show', ['id' => 1])
            ->willReturn('/gen/app_order_show?id=1');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfNeverChecked());

        $response = $flow->process($request, new \stdClass(), $this->handlerOk('Processed'), $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Processed'], $flasher->successes);
    }

    public function testProcessWithValidCsrfTokenRunsHandler(): void
    {
        $request = Request::create('/task/7/complete', 'POST', ['_token' => 'good-token']);
        $context = FlowContext::forAction('app_task_show', ['id' => 7])->csrf('complete7');

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/gen/app_task_show?id=7');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfExpecting('complete7', 'good-token', true));

        $flow->process($request, new \stdClass(), $this->handlerOk('Completed'), $context);

        self::assertSame(['Completed'], $flasher->successes);
        self::assertSame([], $flasher->errors);
    }

    public function testProcessWithInvalidCsrfTokenSkipsHandlerAndRedirects(): void
    {
        $request = Request::create('/task/7/complete', 'POST', ['_token' => 'bad-token']);
        $context = FlowContext::forAction('app_task_show', ['id' => 7])->csrf('complete7');

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_task_show', ['id' => 7])
            ->willReturn('/gen/app_task_show?id=7');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_task_show?id=7', false, 303)
            ->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfExpecting('complete7', 'bad-token', false));

        $called = false;
        $handler = function () use (&$called): StubResult {
            $called = true;

            return StubResult::ok('Completed');
        };

        $response = $flow->process($request, new \stdClass(), $handler, $context);

        self::assertFalse($called);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Invalid CSRF token.'], $flasher->errors);
        self::assertSame([], $flasher->successes);
    }

    public function testProcessWithMissingCsrfTokenSkipsHandler(): void
    {
        $request = Request::create('/task/7/complete', 'POST');
        $context = FlowContext::forAction('app_task_show', ['id' => 7])->csrf('complete7');

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/gen/app_task_show?id=7');

        $redirector = $this->createStub(RedirectorInterface::class);
        $redirector->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new ActionFlow($flasher, $redirector, $urls, $this->csrfExpecting('complete7', '', false));

        $flow->process($request, new \stdClass(), fn () => self::fail('Handler must not run.'), $context);

        self::assertSame(['Invalid CSRF token.'], $flasher->errors);
    }
}
