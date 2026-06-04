<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\InlineEdit;

use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\Tests\Fixtures\SampleEntity;
use PHPUnit\Framework\TestCase;

final class InlineEditContextTest extends TestCase
{
    public function testCreateStoresProvidedValues(): void
    {
        $entity = new SampleEntity();

        $context = InlineEditContext::create(
            frameId: 'inline-edit-sample-1-name',
            displayTemplate: 'sample/_inline_name.html.twig',
            entity: $entity,
            cancelUrl: '/sample/1/inline/name',
            entityVarName: 'thing',
            displayTemplateVars: ['extra' => true],
            successMessage: 'Saved',
        );

        self::assertSame('inline-edit-sample-1-name', $context->frameId);
        self::assertSame('sample/_inline_name.html.twig', $context->displayTemplate);
        self::assertSame($entity, $context->entity);
        self::assertSame('/sample/1/inline/name', $context->cancelUrl);
        self::assertSame('thing', $context->entityVarName);
        self::assertSame(['extra' => true], $context->displayTemplateVars);
        self::assertSame('Saved', $context->successMessage);
    }

    public function testEntityVarNameIsDerivedFromEntityClass(): void
    {
        $context = InlineEditContext::create(
            frameId: 'frame',
            displayTemplate: 'tpl.html.twig',
            entity: new SampleEntity(),
        );

        self::assertSame('sampleEntity', $context->entityVarName);
    }

    public function testDefaults(): void
    {
        $context = InlineEditContext::create(
            frameId: 'frame',
            displayTemplate: 'tpl.html.twig',
            entity: new SampleEntity(),
        );

        self::assertNull($context->cancelUrl);
        self::assertSame([], $context->displayTemplateVars);
        self::assertSame('Updated successfully', $context->successMessage);
    }

    public function testSuccessMessageCanBeDisabled(): void
    {
        $context = InlineEditContext::create(
            frameId: 'frame',
            displayTemplate: 'tpl.html.twig',
            entity: new SampleEntity(),
            successMessage: null,
        );

        self::assertNull($context->successMessage);
    }
}
