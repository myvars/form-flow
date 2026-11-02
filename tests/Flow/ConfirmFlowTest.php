<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\ActionFlow;
use MyVars\FormFlow\ConfirmFlow;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Double\RecordingTwig;
use MyVars\FormFlow\Tests\Double\StubResult;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\FlowRoutes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final class ConfirmFlowTest extends TestCase
{
    public function testConfirmRendersBaseTemplate(): void
    {
        $entity = (object) ['id' => 5, 'name' => 'Test'];
        $context = FlowContext::forDelete(FlowModel::simple('order_item'));

        $twig = new RecordingTwig('<html>confirm</html>');
        $actionFlow = new ActionFlow(new RecordingFlasher(), $this->redirector(), $this->urls(), $this->createStub(CsrfTokenManagerInterface::class));
        $flow = new ConfirmFlow($twig, $actionFlow);

        $response = $flow->confirm($entity, $context);

        self::assertSame('delete', $context->getConfirmKey());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<html>confirm</html>', $response->getContent());
        self::assertSame(FlowModel::BASE_TEMPLATE, $twig->template);
        self::assertSame($entity, $twig->vars['result']);
        self::assertSame('delete', $twig->vars['flowOperation']);
        self::assertSame('Order Item', $twig->vars['flowModel']);
        self::assertInstanceOf(FlowRoutes::class, $twig->vars['routes']);
        self::assertSame('app_order_item_delete', $twig->vars['routes']->delete);
        self::assertSame('delete', $twig->vars['confirmKey']);
    }

    public function testExecuteInvalidCsrfAddsErrorFlashAndRedirects(): void
    {
        $request = Request::create('/order-item/5/delete', 'POST');
        $request->request->set('_token', 'bad-token');

        $context = FlowContext::forDelete(FlowModel::simple('order_item'));

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/app_order_item_index', true, 303)
            ->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $redirector, $this->urls(), $this->csrfExpecting('delete5', 'bad-token', false));
        $flow = new ConfirmFlow($this->createStub(Environment::class), $actionFlow);

        $response = $flow->execute($request, (object) ['id' => 5], fn (): null => null, $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Invalid CSRF token.'], $flasher->errors);
        self::assertSame([], $flasher->successes);
    }

    public function testExecuteValidCsrfDelegatesToActionFlowProcess(): void
    {
        $request = Request::create('/order-item/9/delete', 'POST');
        $request->request->set('_token', 'good-token');

        $context = FlowContext::forDelete(FlowModel::simple('order_item'));

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $this->redirector(), $this->urls(), $this->csrfExpecting('delete9', 'good-token', true));
        $flow = new ConfirmFlow($this->createStub(Environment::class), $actionFlow);

        $response = $flow->execute($request, (object) ['id' => 9], fn (object $cmd): StubResult => StubResult::ok('Deleted'), $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Deleted'], $flasher->successes);
        self::assertSame([], $flasher->errors);
    }

    public function testExecuteAcceptsAStringableId(): void
    {
        $request = Request::create('/order-item/01ARZ3NDEKTSV4RRFFQ69G5FAV/delete', 'POST');
        $request->request->set('_token', 'good-token');

        $context = FlowContext::forDelete(FlowModel::simple('order_item'));

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $this->redirector(), $this->urls(), $this->csrfExpecting('delete01ARZ3NDEKTSV4RRFFQ69G5FAV', 'good-token', true));
        $flow = new ConfirmFlow($this->createStub(Environment::class), $actionFlow);

        // A typed id value object, as an app with ULID keys passes it
        $id = new class implements \Stringable {
            public function __toString(): string
            {
                return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            }
        };

        $flow->execute($request, (object) ['id' => $id], fn (object $cmd): StubResult => StubResult::ok('Deleted'), $context);

        self::assertSame(['Deleted'], $flasher->successes);
    }

    public function testForConfirmScopesCsrfTokenToCustomKey(): void
    {
        $request = Request::create('/purchase-order/7/rewind', 'POST');
        $request->request->set('_token', 'good-token');

        $context = FlowContext::forConfirm(FlowModel::simple('purchase_order'), 'rewind')
            ->successRoute('app_purchasing_purchase_order_show', ['id' => 7]);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_purchasing_purchase_order_show', ['id' => 7])
            ->willReturn('/gen/show?id=7');

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $this->redirector(), $urls, $this->csrfExpecting('rewind7', 'good-token', true));
        $flow = new ConfirmFlow($this->createStub(Environment::class), $actionFlow);

        $response = $flow->execute($request, (object) ['id' => 7], fn (object $cmd): StubResult => StubResult::ok('Rewound'), $context);

        self::assertSame('rewind', $context->getConfirmKey());
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Rewound'], $flasher->successes);
    }

    public function testExecuteLeavesTheCallersContextUntouched(): void
    {
        $request = Request::create('/order-item/5/delete', 'POST');
        $context = FlowContext::forDelete(FlowModel::simple('order_item'));

        $actionFlow = new ActionFlow(new RecordingFlasher(), $this->redirector(), $this->urls(), $this->createStub(CsrfTokenManagerInterface::class));
        $flow = new ConfirmFlow($this->createStub(Environment::class), $actionFlow);

        $flow->execute($request, (object) ['id' => 5], fn (): null => null, $context);

        self::assertNull($context->getCsrfTokenId());
    }

    private function csrfExpecting(string $id, string $value, bool $valid): CsrfTokenManagerInterface
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())->method('isTokenValid')
            ->with(new CsrfToken($id, $value))
            ->willReturn($valid);

        return $csrf;
    }

    /** Generates "/<route>", so a redirect expectation can name the route without a generator mock. */
    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(fn (string $route): string => '/' . $route);

        return $urls;
    }

    /** For tests that only need the flow to redirect, not to check how. */
    private function redirector(): RedirectorInterface
    {
        $redirector = $this->createStub(RedirectorInterface::class);
        $redirector->method('to')->willReturn(new Response('', 303));

        return $redirector;
    }
}
