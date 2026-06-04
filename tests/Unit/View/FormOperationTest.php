<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\View;

use MyVars\FormFlow\View\FormOperation;
use PHPUnit\Framework\TestCase;

final class FormOperationTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('create', FormOperation::Create->value);
        self::assertSame('update', FormOperation::Update->value);
        self::assertSame('delete', FormOperation::Delete->value);
        self::assertSame('filter', FormOperation::Filter->value);
        self::assertSame('action', FormOperation::Action->value);
        self::assertSame('index', FormOperation::Index->value);
    }

    public function testPastTense(): void
    {
        self::assertSame('created', FormOperation::Create->past());
        self::assertSame('updated', FormOperation::Update->past());
        self::assertSame('deleted', FormOperation::Delete->past());
        self::assertSame('filtered', FormOperation::Filter->past());
        self::assertSame('actioned', FormOperation::Action->past());
        self::assertSame('indexed', FormOperation::Index->past());
    }
}
