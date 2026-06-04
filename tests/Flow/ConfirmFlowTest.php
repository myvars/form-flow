<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\ActionFlow;
use MyVars\FormFlow\ConfirmFlow;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
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

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with(
                FlowModel::BASE_TEMPLATE,
                $this->callback(fn (array $vars): bool => $vars['result'] === $entity
                    && $vars['flowOperation'] === $context->getOperation()->value
                    && $vars['flowModel'] === 'Order Item'
                    && $vars['routes'] instanceof FlowRoutes
                    && $vars['routes']->delete === 'app_order_item_delete'
                    && $vars['confirmKey'] === 'delete')
            )
            ->willReturn('<html>confirm</html>');

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $this->createStub(RedirectorInterface::class), $this->createStub(UrlGeneratorInterface::class));
        $flow = new ConfirmFlow($twig, $flasher, $this->createStub(CsrfTokenManagerInterface::class), $actionFlow);

        $response = $flow->confirm($entity, $context);

        self::assertSame('delete', $context->getConfirmKey());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<html>confirm</html>', $response->getContent());
    }

    public function testExecuteInvalidCsrfAddsErrorFlashAndRedirects(): void
    {
        $request = Request::create('/order-item/5/delete', 'POST');
        $request->request->set('_token', 'bad-token');

        $context = FlowContext::forDelete(FlowModel::simple('order_item'));

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t): bool => $t->getId() === 'delete5' && $t->getValue() === 'bad-token'))
            ->willReturn(false);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_item_index', [])
            ->willReturn('/gen/app_order_item_index');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_order_item_index', true, 303)
            ->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $redirector, $urls);
        $flow = new ConfirmFlow($this->createStub(Environment::class), $flasher, $csrf, $actionFlow);

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

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t): bool => $t->getId() === 'delete9' && $t->getValue() === 'good-token'))
            ->willReturn(true);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')->willReturn('/gen/app_order_item_index');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $redirector, $urls);
        $flow = new ConfirmFlow($this->createStub(Environment::class), $flasher, $csrf, $actionFlow);

        $response = $flow->execute($request, (object) ['id' => 9], fn (object $cmd): StubResult => StubResult::ok('Deleted'), $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Deleted'], $flasher->successes);
        self::assertSame([], $flasher->errors);
    }

    public function testForConfirmScopesCsrfTokenToCustomKey(): void
    {
        $request = Request::create('/purchase-order/7/rewind', 'POST');
        $request->request->set('_token', 'good-token');

        $context = FlowContext::forConfirm(FlowModel::simple('purchase_order'), 'rewind')
            ->successRoute('app_purchasing_purchase_order_show', ['id' => 7]);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $t): bool => $t->getId() === 'rewind7' && $t->getValue() === 'good-token'))
            ->willReturn(true);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_purchasing_purchase_order_show', ['id' => 7])
            ->willReturn('/gen/show?id=7');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $actionFlow = new ActionFlow($flasher, $redirector, $urls);
        $flow = new ConfirmFlow($this->createStub(Environment::class), $flasher, $csrf, $actionFlow);

        $response = $flow->execute($request, (object) ['id' => 7], fn (object $cmd): StubResult => StubResult::ok('Rewound'), $context);

        self::assertSame('rewind', $context->getConfirmKey());
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Rewound'], $flasher->successes);
    }
}
