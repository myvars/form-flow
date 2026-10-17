<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Fixtures\ValidatedEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * Runs the flow with a real form factory and validator, so the entity property's own
 * attributes decide whether onSave runs.
 */
final class InlineEditEntityRulesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $rendered = [];

    private ValidatorInterface $validator;

    private FormFactoryInterface $forms;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $this->forms = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension($this->validator))
            ->getFormFactory();
    }

    public function testOverLengthValueIsAFieldErrorAndNotSaved(): void
    {
        $entity = new ValidatedEntity();
        $saved = false;

        $response = $this->flow($this->validator)->handleField(
            $this->post('toolong'),
            $entity->name,
            function () use (&$saved): bool {
                $saved = true;

                return true;
            },
            $this->context($entity, 'name'),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertFalse($saved);
        self::assertSame(['Max 5 characters'], $this->fieldErrors());
    }

    public function testBlankValueReportsTheEntityMessage(): void
    {
        $entity = new ValidatedEntity();

        $response = $this->flow($this->validator)->handleField(
            $this->post(''),
            $entity->name,
            fn (): bool => self::fail('onSave must not run'),
            $this->context($entity, 'name'),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(['Please enter a name'], $this->fieldErrors());
    }

    public function testValidValueIsSaved(): void
    {
        $entity = new ValidatedEntity();
        $savedValue = null;

        $response = $this->flow($this->validator)->handleField(
            $this->post('new'),
            $entity->name,
            function (mixed $value) use (&$savedValue): bool {
                $savedValue = $value;

                return true;
            },
            $this->context($entity, 'name'),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('new', $savedValue);
    }

    public function testWithoutAPropertyTheEntityRulesAreNotChecked(): void
    {
        $entity = new ValidatedEntity();
        $savedValue = null;

        $this->flow($this->validator)->handleField(
            $this->post('toolong'),
            $entity->name,
            function (mixed $value) use (&$savedValue): bool {
                $savedValue = $value;

                return true;
            },
            $this->context($entity, null),
        );

        self::assertSame('toolong', $savedValue);
    }

    public function testAPropertyWithoutAValidatorIsAClearError(): void
    {
        $entity = new ValidatedEntity();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('composer require symfony/validator');

        $this->flow(null)->handleField(
            $this->post('new'),
            $entity->name,
            fn (): bool => true,
            $this->context($entity, 'name'),
        );
    }

    private function flow(?ValidatorInterface $validator): InlineEditFlow
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $params): string {
            $this->rendered = $params;

            return '<html></html>';
        });

        return new InlineEditFlow($this->forms, new RecordingFlasher(), $twig, $validator);
    }

    private function context(object $entity, ?string $property): InlineEditContext
    {
        return InlineEditContext::create(
            frameId: 'inline-edit-validated-1-name',
            displayTemplate: 'validated/_inline_name.html.twig',
            entity: $entity,
            property: $property,
        );
    }

    private function post(string $value): Request
    {
        return Request::create('/validated/1/inline/name', 'POST', ['inline_field' => ['value' => $value]]);
    }

    /**
     * @return list<string>
     */
    private function fieldErrors(): array
    {
        $form = $this->rendered['form'] ?? null;
        self::assertInstanceOf(FormView::class, $form);

        $messages = [];
        foreach ($form->children['value']->vars['errors'] as $error) {
            $messages[] = $error->getMessage();
        }

        return $messages;
    }
}
