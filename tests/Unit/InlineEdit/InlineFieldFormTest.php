<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\InlineEdit;

use MyVars\FormFlow\InlineEdit\InlineFieldForm;
use PHPUnit\Framework\TestCase;

final class InlineFieldFormTest extends TestCase
{
    public function testHoldsValue(): void
    {
        self::assertSame('hello', new InlineFieldForm('hello')->value);
    }

    public function testDefaultsToNull(): void
    {
        self::assertNull(new InlineFieldForm()->value);
    }
}
