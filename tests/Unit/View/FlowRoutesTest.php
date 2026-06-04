<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\View;

use MyVars\FormFlow\View\FlowRoutes;
use PHPUnit\Framework\TestCase;

final class FlowRoutesTest extends TestCase
{
    public function testFromPrefixDerivesAllRoutes(): void
    {
        $routes = FlowRoutes::fromPrefix('app_catalog_product');

        self::assertSame('app_catalog_product_index', $routes->index);
        self::assertSame('app_catalog_product_new', $routes->new);
        self::assertSame('app_catalog_product_show', $routes->show);
        self::assertSame('app_catalog_product_delete', $routes->delete);
        self::assertSame('app_catalog_product_delete_confirm', $routes->deleteConfirm);
        self::assertSame('app_catalog_product_search_filter', $routes->filter);
    }

    public function testWithSelectiveOverride(): void
    {
        $routes = FlowRoutes::fromPrefix('app_catalog_product');
        $overridden = $routes->with(index: 'custom_index', delete: 'custom_delete');

        self::assertSame('custom_index', $overridden->index);
        self::assertSame('app_catalog_product_new', $overridden->new);
        self::assertSame('app_catalog_product_show', $overridden->show);
        self::assertSame('custom_delete', $overridden->delete);
        self::assertSame('app_catalog_product_delete_confirm', $overridden->deleteConfirm);
        self::assertSame('app_catalog_product_search_filter', $overridden->filter);
    }

    public function testDefaultConstructorAllNull(): void
    {
        $routes = new FlowRoutes();

        self::assertNull($routes->index);
        self::assertNull($routes->new);
        self::assertNull($routes->show);
        self::assertNull($routes->delete);
        self::assertNull($routes->deleteConfirm);
        self::assertNull($routes->filter);
    }
}
