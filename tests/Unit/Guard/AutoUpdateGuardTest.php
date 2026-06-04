<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\Guard;

use MyVars\FormFlow\Guard\AutoUpdateGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;

final class AutoUpdateGuardTest extends TestCase
{
    public function testIsFalseWhenButtonAbsent(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn(false);

        self::assertFalse(new AutoUpdateGuard()->is($form));
    }

    public function testIsFalseWhenButtonPresentButNotClicked(): void
    {
        $button = $this->createStubForIntersectionOfInterfaces([FormInterface::class, ClickableInterface::class]);
        $button->method('isClicked')->willReturn(false);

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn(true);
        $form->method('get')->willReturn($button);

        self::assertFalse(new AutoUpdateGuard()->is($form));
    }

    public function testIsTrueWhenButtonClicked(): void
    {
        $button = $this->createStubForIntersectionOfInterfaces([FormInterface::class, ClickableInterface::class]);
        $button->method('isClicked')->willReturn(true);

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn(true);
        $form->method('get')->willReturn($button);

        self::assertTrue(new AutoUpdateGuard()->is($form));
    }

    public function testClearIsNoOpWhenNotAutoUpdate(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn(false);

        $guard = new AutoUpdateGuard();
        $guard->clear($form); // must not throw when there is no auto-update button

        self::assertFalse($guard->is($form));
    }
}
