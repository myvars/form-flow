<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\View;

use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\FlowRoutes;
use PHPUnit\Framework\TestCase;

final class FlowModelTest extends TestCase
{
    public function testCreateManufacturer(): void
    {
        $m = FlowModel::create('catalog', 'manufacturer');

        self::assertSame('Manufacturer', $m->displayName);
        self::assertSame('catalog/manufacturer', $m->templateDir);
        self::assertSame('app_catalog_manufacturer_index', $m->defaultSuccessRoute);
        self::assertSame('app_catalog_manufacturer_index', $m->routes->index);
        self::assertSame('app_catalog_manufacturer_new', $m->routes->new);
        self::assertSame('app_catalog_manufacturer_show', $m->routes->show);
        self::assertSame('app_catalog_manufacturer_delete', $m->routes->delete);
        self::assertSame('app_catalog_manufacturer_search_filter', $m->routes->filter);
    }

    public function testCreateWithMultiWordEntity(): void
    {
        $m = FlowModel::create('purchasing', 'supplier_product');

        self::assertSame('Supplier Product', $m->displayName);
        self::assertSame('purchasing/supplier_product', $m->templateDir);
        self::assertSame('app_purchasing_supplier_product_index', $m->defaultSuccessRoute);
        self::assertSame('app_purchasing_supplier_product_search_filter', $m->routes->filter);
    }

    public function testCreateWithExplicitDisplayName(): void
    {
        $m = FlowModel::create('pricing', 'vat_rate', displayName: 'VAT Rate');

        self::assertSame('VAT Rate', $m->displayName);
        self::assertSame('pricing/vat_rate', $m->templateDir);
        self::assertSame('app_pricing_vat_rate_index', $m->defaultSuccessRoute);
    }

    public function testSimpleEntity(): void
    {
        $m = FlowModel::simple('customer');

        self::assertSame('Customer', $m->displayName);
        self::assertSame('customer', $m->templateDir);
        self::assertSame('app_customer_index', $m->defaultSuccessRoute);
        self::assertSame('app_customer_index', $m->routes->index);
    }

    public function testSimpleMultiWordEntity(): void
    {
        $m = FlowModel::simple('order_item');

        self::assertSame('Order Item', $m->displayName);
        self::assertSame('order_item', $m->templateDir);
        self::assertSame('app_order_item_index', $m->defaultSuccessRoute);
    }

    public function testTemplateDerivesBoundedContextPath(): void
    {
        $m = FlowModel::create('catalog', 'manufacturer');
        self::assertSame('catalog/manufacturer/create.html.twig', $m->template('create'));
        self::assertSame('catalog/manufacturer/update.html.twig', $m->template('update'));
        self::assertSame('catalog/manufacturer/index.html.twig', $m->template('index'));
        self::assertSame('catalog/manufacturer/delete.html.twig', $m->template('delete'));
    }

    public function testTemplateDerivesSimplePath(): void
    {
        $m = FlowModel::simple('customer');
        self::assertSame('customer/create.html.twig', $m->template('create'));
        self::assertSame('customer/update.html.twig', $m->template('update'));
    }

    public function testPluralDefaultsToDisplayNameWithS(): void
    {
        self::assertSame('Manufacturers', FlowModel::create('catalog', 'manufacturer')->plural());
    }

    public function testPluralUsesExplicitOverride(): void
    {
        $m = FlowModel::create('catalog', 'category', displayNamePlural: 'Categories');
        self::assertSame('Categories', $m->plural());
    }

    public function testWithDisplayNameReturnsNewInstance(): void
    {
        $original = FlowModel::simple('pricing');
        $override = $original->withDisplayName('Product Cost');

        self::assertSame('Product Cost', $override->displayName);
        self::assertSame('Pricing', $original->displayName);

        self::assertSame($original->templateDir, $override->templateDir);
        self::assertSame($original->defaultSuccessRoute, $override->defaultSuccessRoute);
        self::assertSame($original->routes->index, $override->routes->index);
    }

    public function testBaseTemplateMatchesExpectedPath(): void
    {
        self::assertSame('shared/form_flow/base.html.twig', FlowModel::BASE_TEMPLATE);
    }

    public function testRoutesAreFlowRoutesInstances(): void
    {
        $m = FlowModel::create('catalog', 'product');
        self::assertInstanceOf(FlowRoutes::class, $m->routes);
    }
}
