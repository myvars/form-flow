<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\View;

use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\FlowRoutes;
use MyVars\FormFlow\View\TemplateContext;
use PHPUnit\Framework\TestCase;

final class TemplateContextTest extends TestCase
{
    public function testFromBuildsExpectedDefaults(): void
    {
        $model = FlowModel::create('catalog', 'manufacturer');
        $context = TemplateContext::from($model, 'edit');

        self::assertSame('Manufacturer', $context->flowModel);
        self::assertSame('edit', $context->flowOperation);
        self::assertSame('catalog/manufacturer/edit.html.twig', $context->template);
        self::assertSame($model->routes, $context->routes);
        self::assertSame('Manufacturers', $context->flowModelPlural);
        self::assertSame([
            'flowModel' => 'Manufacturer',
            'flowOperation' => 'edit',
            'template' => 'catalog/manufacturer/edit.html.twig',
            'routes' => $model->routes,
            'flowModelPlural' => 'Manufacturers',
        ], $context->toArray());
    }

    public function testFromWithTemplateOverride(): void
    {
        $model = FlowModel::simple('review');
        $context = TemplateContext::from($model, 'update', 'review/reject.html.twig');

        self::assertSame('Review', $context->flowModel);
        self::assertSame('update', $context->flowOperation);
        self::assertSame('review/reject.html.twig', $context->template);
    }

    public function testFromWithRouteOverride(): void
    {
        $model = FlowModel::create('catalog', 'product');
        $customRoutes = FlowRoutes::fromPrefix('app_custom');
        $context = TemplateContext::from($model, 'index', routes: $customRoutes);

        self::assertSame($customRoutes, $context->routes);
        self::assertSame($customRoutes, $context->toArray()['routes']);
    }

    public function testFromWithDisplayNameOverride(): void
    {
        $model = FlowModel::simple('pricing')->withDisplayName('Product Cost');
        $context = TemplateContext::from($model, 'update');

        self::assertSame('Product Cost', $context->flowModel);
        self::assertSame('pricing/update.html.twig', $context->template);
    }
}
