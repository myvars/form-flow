<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\View;

use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\FlowRoutes;
use MyVars\FormFlow\View\FormOperation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class FlowContextTest extends TestCase
{
    private function urlGenerator(): UrlGeneratorInterface
    {
        $stub = $this->createStub(UrlGeneratorInterface::class);

        $stub->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                ksort($parameters);
                $query = $parameters !== [] ? '?' . http_build_query($parameters) : '';

                return '/gen/' . $name . $query;
            }
        );

        $context = new RequestContext();
        $stub->method('getContext')->willReturn($context);
        $stub->method('setContext')->willReturnCallback(static function ($new) use (&$context): void { $context = $new; });

        return $stub;
    }

    public function testFactoryForCreateSetsDefaults(): void
    {
        $model = FlowModel::create('catalog', 'manufacturer');
        $ctx = FlowContext::forCreate($model);

        self::assertSame($model, $ctx->getFlowModel());
        self::assertSame(FormOperation::Create, $ctx->getOperation());
        self::assertSame('catalog/manufacturer/create.html.twig', $ctx->getTemplate());
        self::assertSame('app_catalog_manufacturer_index', $ctx->getSuccessRoute());

        $routes = $ctx->getRoutes();
        self::assertInstanceOf(FlowRoutes::class, $routes);
        self::assertSame('app_catalog_manufacturer_index', $routes->index);
        self::assertSame('app_catalog_manufacturer_new', $routes->new);
        self::assertSame('app_catalog_manufacturer_delete', $routes->delete);
    }

    public function testForSearchSetsRoutes(): void
    {
        $model = FlowModel::create('catalog', 'product');
        $ctx = FlowContext::forSearch($model);

        $routes = $ctx->getRoutes();
        self::assertInstanceOf(FlowRoutes::class, $routes);
        self::assertSame('app_catalog_product_index', $routes->index);
        self::assertSame('app_catalog_product_search_filter', $routes->filter);
    }

    public function testResolveSuccessUrlUsesConfiguredRoute(): void
    {
        $model = FlowModel::simple('order_item');
        $ctx = FlowContext::forCreate($model)->successRoute('app_order_item_index', ['page' => 2]);
        $req = Request::create('/current');
        $url = $ctx->resolveSuccessUrl($req, $this->urlGenerator());
        self::assertSame('/gen/app_order_item_index?page=2', $url);
    }

    public function testResolveSuccessUrlFallsBackToReferer(): void
    {
        $req = Request::create('/current');
        $req->headers->set('referer', '/prev');
        $refCtx = FlowContext::forSearch(FlowModel::simple('order'));
        $ref = $refCtx->resolveSuccessUrl($req, $this->urlGenerator());
        self::assertSame('/prev', $ref);
    }

    public function testResolveSuccessUrlFallsBackToCurrentWhenNoReferer(): void
    {
        $ctx = FlowContext::forSearch(FlowModel::simple('order'));
        $req = Request::create('/current');
        $url = $ctx->resolveSuccessUrl($req, $this->urlGenerator());
        self::assertSame('/current', $url);
    }

    public function testResolveBackUrl(): void
    {
        $ctx = FlowContext::forSearch(FlowModel::simple('order'));
        $req = Request::create('/x');
        self::assertNull($ctx->resolveBackUrl($req));
        $req->headers->set('referer', '/prev');
        self::assertSame('/prev', $ctx->resolveBackUrl($req));
    }

    public function testMutators(): void
    {
        $model = FlowModel::simple('order');
        $ctx = FlowContext::forCreate($model)
            ->template('custom.html.twig')
            ->successRoute('app_custom', ['id' => 10])
            ->allowDelete(true);

        self::assertSame($model, $ctx->getFlowModel());
        self::assertSame('custom.html.twig', $ctx->getTemplate());
        self::assertSame('app_custom', $ctx->getSuccessRoute());
        self::assertSame(['id' => 10], $ctx->getSuccessParams());
        self::assertTrue($ctx->isAllowDelete());
    }

    public function testForUpdateWithFlowModel(): void
    {
        $model = FlowModel::create('purchasing', 'supplier_product');
        $ctx = FlowContext::forUpdate($model);

        self::assertSame('Supplier Product', $ctx->getFlowModel()->displayName);
        self::assertSame(FormOperation::Update, $ctx->getOperation());
        self::assertSame('purchasing/supplier_product/update.html.twig', $ctx->getTemplate());
        self::assertSame('app_purchasing_supplier_product_index', $ctx->getSuccessRoute());
    }

    public function testForDeleteWithFlowModel(): void
    {
        $model = FlowModel::simple('order');
        $ctx = FlowContext::forDelete($model);

        self::assertSame('Order', $ctx->getFlowModel()->displayName);
        self::assertSame(FormOperation::Delete, $ctx->getOperation());
        self::assertTrue($ctx->isRedirectRefresh());
        self::assertSame('app_order_index', $ctx->getSuccessRoute());
        self::assertSame('delete', $ctx->getConfirmKey());
    }

    public function testForConfirmScopesConfirmKey(): void
    {
        $model = FlowModel::simple('purchase_order');
        $ctx = FlowContext::forConfirm($model, 'rewind');

        self::assertSame(FormOperation::Action, $ctx->getOperation());
        self::assertTrue($ctx->isRedirectRefresh());
        self::assertSame('rewind', $ctx->getConfirmKey());
    }

    public function testForFilterWithFlowModel(): void
    {
        $model = FlowModel::create('catalog', 'category');
        $ctx = FlowContext::forFilter($model);

        self::assertSame('Category', $ctx->getFlowModel()->displayName);
        self::assertSame(FormOperation::Filter, $ctx->getOperation());
        self::assertSame('catalog/category/filter.html.twig', $ctx->getTemplate());
    }

    public function testWithDisplayNameOverride(): void
    {
        $model = FlowModel::simple('pricing')->withDisplayName('Product Cost');
        $ctx = FlowContext::forUpdate($model);

        self::assertSame('Product Cost', $ctx->getFlowModel()->displayName);
        self::assertSame('pricing/update.html.twig', $ctx->getTemplate());
        self::assertSame('app_pricing_index', $ctx->getSuccessRoute());
    }

    public function testValidateThrowsWhenModelMissing(): void
    {
        $ctx = FlowContext::forAction('app_order_show');

        $this->expectException(\LogicException::class);
        $ctx->validate();
    }
}
