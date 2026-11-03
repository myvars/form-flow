<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Redirect\TurboAwareRedirector;
use MyVars\FormFlow\SearchFlow;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Double\RecordingTwig;
use MyVars\FormFlow\Tests\Double\StubSearchCriteria;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\FlowRoutes;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class SearchFlowTest extends TestCase
{
    public function testSearchRendersTemplateWithResults(): void
    {
        $request = Request::create('/order-item');
        $adapter = new ArrayAdapter(['A', 'B', 'C', 'D', 'E']);
        $criteria = new StubSearchCriteria(page: 1, limit: 2);

        $twig = new RecordingTwig('<html>OK</html>');
        $flasher = new RecordingFlasher();
        $flow = new SearchFlow($twig, $flasher, $this->createStub(RedirectorInterface::class), $this->createStub(UrlGeneratorInterface::class));

        $response = $flow->search($request, $adapter, $criteria, FlowContext::forSearch(FlowModel::simple('order_item')));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<html>OK</html>', $response->getContent());
        self::assertSame([], $flasher->warnings);
        self::assertSame('shared/form_flow/base.html.twig', $twig->template);
        self::assertSame('Order Item', $twig->vars['flowModel']);
        self::assertSame('index', $twig->vars['flowOperation']);
        self::assertSame('order_item/index.html.twig', $twig->vars['template']);
        self::assertInstanceOf(FlowRoutes::class, $twig->vars['routes']);
        self::assertSame('app_order_item_index', $twig->vars['routes']->index);
        self::assertInstanceOf(Pagerfanta::class, $twig->vars['results']);
    }

    public function testSearchOutOfRangeRedirectsWithWarningFlash(): void
    {
        $request = Request::create('/order-item', 'GET', ['foo' => 'bar']);
        $adapter = new ArrayAdapter(['A', 'B', 'C', 'D', 'E']);
        $criteria = new StubSearchCriteria(page: 99, limit: 2);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('app_order_item_index', ['foo' => 'bar', 'page' => '1'])
            ->willReturn('/gen/app_order_item_index?foo=bar&page=1');

        $redirector = $this->createMock(RedirectorInterface::class);
        $redirector->expects($this->once())->method('to')
            ->with($request, '/gen/app_order_item_index?foo=bar&page=1')
            ->willReturn(new Response('', 303));

        $flasher = new RecordingFlasher();
        $flow = new SearchFlow($this->createStub(Environment::class), $flasher, $redirector, $urls);

        $response = $flow->search($request, $adapter, $criteria, FlowContext::forSearch(FlowModel::simple('order_item')));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['Page 99 not found.'], $flasher->warnings);
    }

    public function testSearchOutOfRangeInsideTurboFrameRedirectsToPageOne(): void
    {
        // A pagination link or GET search form inside a frame. Turbo follows the redirect within the frame;
        // a refresh stream would reload the browser's current URL instead of page 1.
        $request = Request::create('/order-item', 'GET', ['foo' => 'bar', 'page' => '99']);
        $request->headers->set('Turbo-Frame', 'results');
        $request->headers->set('Accept', 'text/html, application/xhtml+xml');
        $criteria = new StubSearchCriteria(page: 99, limit: 2);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/order-item?foo=bar&page=1');

        $flasher = new RecordingFlasher();
        $flow = new SearchFlow($this->createStub(Environment::class), $flasher, new TurboAwareRedirector(), $urls);

        $response = $flow->search($request, new ArrayAdapter(['A', 'B', 'C']), $criteria, FlowContext::forSearch(FlowModel::simple('order_item')));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/order-item?foo=bar&page=1', $response->getTargetUrl());
        self::assertSame(['Page 99 not found.'], $flasher->warnings);
    }
}
