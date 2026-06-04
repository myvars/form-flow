<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\FormFlow;
use MyVars\FormFlow\Guard\AutoUpdateGuard;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Double\StubRedirectTarget;
use MyVars\FormFlow\Tests\Double\StubResult;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\FlowRoutes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class FormFlowTest extends TestCase
{
    /**
     * @return FormInterface<mixed>
     */
    private function formMock(bool $submitted, bool $valid, mixed $data, bool $autoUpdate = false): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('handleRequest')->with($this->isInstanceOf(Request::class));
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('getData')->willReturn($data);
        $form->method('createView')->willReturn(new FormView());

        if ($autoUpdate) {
            $button = $this->createStubForIntersectionOfInterfaces([FormInterface::class, ClickableInterface::class]);
            $button->method('isClicked')->willReturn(true);
            $form->method('has')->willReturn(true);
            $form->method('get')->willReturn($button);
        } else {
            $form->method('has')->willReturn(false);
        }

        return $form;
    }

    private function mapper(): callable
    {
        return fn (mixed $d) => (object) ['mapped' => $d];
    }

    private function handlerOk(?string $msg = 'Saved', ?StubRedirectTarget $rt = null): callable
    {
        return fn (object $cmd): StubResult => StubResult::ok($msg, $rt);
    }

    private function handlerFail(?string $msg = 'Failed'): callable
    {
        return fn (object $cmd): StubResult => StubResult::fail($msg);
    }

    public function testGetRequestRendersTemplateOk(): void
    {
        $request = Request::create('/order/new', 'GET');
        $form = $this->formMock(false, true, null);

        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects($this->once())->method('create')
            ->with('FormType', $this->equalTo([]), $this->callback(fn ($opts): bool => $opts['action'] === $request->getUri()))
            ->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with(
                $this->equalTo('shared/form_flow/base.html.twig'),
                $this->callback(fn (array $vars): bool => isset($vars['form'])
                    && $vars['routes'] instanceof FlowRoutes
                    && $vars['routes']->index === 'app_order_item_index')
            )
            ->willReturn('<html>GET</html>');

        $flasher = new RecordingFlasher();
        $flow = new FormFlow($forms, $flasher, $twig, $this->createStub(UrlGeneratorInterface::class), $this->createStub(RedirectorInterface::class), new AutoUpdateGuard());
        $ctx = FlowContext::forCreate(FlowModel::simple('order_item'));

        // @phpstan-ignore argument.type (test uses a mock form type string)
        $response = $flow->form($request, 'FormType', [], $this->mapper(), $this->handlerOk(), $ctx);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<html>GET</html>', $response->getContent());
        self::assertSame([], $flasher->successes);
    }

    public function testSuccessfulPostRedirectsAndFlashes(): void
    {
        $request = Request::create('/order/new', 'POST');
        $form = $this->formMock(true, true, ['x' => 1]);

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_item_index', [])
            ->willReturn('/gen/app_order_item_index');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_order_item_index', false, 303)
            ->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new FormFlow($forms, $flasher, $this->createStub(Environment::class), $urls, $redirector, new AutoUpdateGuard());
        $ctx = FlowContext::forCreate(FlowModel::simple('order_item'));

        // @phpstan-ignore argument.type (test uses a mock form type string)
        $response = $flow->form($request, 'FormType', [], $this->mapper(), $this->handlerOk('Saved'), $ctx);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Saved'], $flasher->successes);
    }

    public function testSuccessfulPostWithRedirectTargetOverridesSuccessRoute(): void
    {
        $request = Request::create('/order/edit', 'POST');
        $form = $this->formMock(true, true, ['id' => 5]);

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        $rt = new StubRedirectTarget('app_order_item_show', ['id' => 5], 307);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_item_show', ['id' => 5])
            ->willReturn('/gen/app_order_item_show?id=5');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_order_item_show?id=5', false, 307, true)
            ->willReturn(new Response('', 307));

        $flasher = new RecordingFlasher();
        $flow = new FormFlow($forms, $flasher, $this->createStub(Environment::class), $urls, $redirector, new AutoUpdateGuard());
        $ctx = FlowContext::forUpdate(FlowModel::simple('order_item'));

        // @phpstan-ignore argument.type (test uses a mock form type string)
        $response = $flow->form($request, 'FormType', [], $this->mapper(), $this->handlerOk('Updated', $rt), $ctx);

        self::assertSame(307, $response->getStatusCode());
        self::assertSame(['Updated'], $flasher->successes);
    }

    public function testInvalidPostRenders422WithoutErrorFlash(): void
    {
        $request = Request::create('/order/new', 'POST');
        $form = $this->formMock(true, false, []);

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->willReturn('<html>422</html>');

        $flasher = new RecordingFlasher();
        $flow = new FormFlow($forms, $flasher, $twig, $this->createStub(UrlGeneratorInterface::class), $this->createStub(RedirectorInterface::class), new AutoUpdateGuard());
        $ctx = FlowContext::forCreate(FlowModel::simple('order_item'));

        // @phpstan-ignore argument.type (test uses a mock form type string)
        $response = $flow->form($request, 'FormType', [], $this->mapper(), $this->handlerFail(), $ctx);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('<html>422</html>', $response->getContent());
        self::assertSame([], $flasher->errors);
    }

    public function testValidPostHandlerFailureRenders422AndErrorFlash(): void
    {
        $request = Request::create('/order/new', 'POST');
        $form = $this->formMock(true, true, ['x' => 1]);

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->willReturn('<html>422</html>');

        $flasher = new RecordingFlasher();
        $flow = new FormFlow($forms, $flasher, $twig, $this->createStub(UrlGeneratorInterface::class), $this->createStub(RedirectorInterface::class), new AutoUpdateGuard());
        $ctx = FlowContext::forCreate(FlowModel::simple('order_item'));

        // @phpstan-ignore argument.type (test uses a mock form type string)
        $response = $flow->form($request, 'FormType', [], $this->mapper(), $this->handlerFail('Failed'), $ctx);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('<html>422</html>', $response->getContent());
        self::assertSame(['Failed'], $flasher->errors);
    }

    public function testAutoUpdateInvalidSkips422Flashing(): void
    {
        $request = Request::create('/order/new', 'POST');
        $form = $this->formMock(true, false, [], autoUpdate: true);

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->willReturn('<html>AUTO</html>');

        $flasher = new RecordingFlasher();
        $flow = new FormFlow($forms, $flasher, $twig, $this->createStub(UrlGeneratorInterface::class), $this->createStub(RedirectorInterface::class), new AutoUpdateGuard());
        $ctx = FlowContext::forCreate(FlowModel::simple('order_item'));

        // @phpstan-ignore argument.type (test uses a mock form type string)
        $response = $flow->form($request, 'FormType', [], $this->mapper(), $this->handlerFail('Ignored'), $ctx);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('<html>AUTO</html>', $response->getContent());
        self::assertSame([], $flasher->errors);
        self::assertSame([], $flasher->successes);
    }
}
