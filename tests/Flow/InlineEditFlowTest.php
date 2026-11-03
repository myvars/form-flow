<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\InlineEdit\InlineFieldForm;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Double\RecordingTwig;
use MyVars\FormFlow\Tests\Fixtures\SampleEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class InlineEditFlowTest extends TestCase
{
    private const string CLOSURE_DEPRECATION = 'Since myvars/form-flow 1.5: Passing the value of inline edit "inline-edit-sample-1-name" as a Closure is deprecated: the success template refreshes the page, so pass the value itself.';

    public function testGetWithoutEditRendersDisplayMode(): void
    {
        $request = Request::create('/sample/1/inline/name', 'GET');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('shared/form_flow/inline_edit_display.html.twig', $this->arrayHasKey('context'))
            ->willReturn('<html>display</html>');

        $flow = new InlineEditFlow($this->createStub(FormFactoryInterface::class), new RecordingFlasher(), $twig);

        $response = $flow->handleField($request, 'Old', fn ($v) => true, $this->context());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<html>display</html>', $response->getContent());
    }

    public function testGetWithEditRendersForm(): void
    {
        $request = Request::create('/sample/1/inline/name', 'GET', ['edit' => '1']);

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(false, false, 'Old'));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('shared/form_flow/inline_edit_form.html.twig', $this->anything())
            ->willReturn('<form>edit</form>');

        $flow = new InlineEditFlow($forms, new RecordingFlasher(), $twig);

        $response = $flow->handleField($request, 'Old', fn ($v) => true, $this->context());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<form>edit</form>', $response->getContent());
    }

    public function testValidPostSavesAndReturnsStreamWithFlash(): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, true, 'New Name'));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('shared/form_flow/inline_edit_success.stream.html.twig', $this->anything())
            ->willReturn('<turbo-stream>ok</turbo-stream>');

        $flasher = new RecordingFlasher();
        $flow = new InlineEditFlow($forms, $flasher, $twig);

        $saved = null;
        $response = $flow->handleField(
            $request,
            'Old Name',
            function ($value) use (&$saved): bool {
                $saved = $value;

                return true;
            },
            $this->context(),
        );

        self::assertSame('New Name', $saved);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('text/vnd.turbo-stream.html', (string) $response->headers->get('Content-Type'));
        self::assertSame(['Updated successfully'], $flasher->successes);
    }

    public function testValidPostWithNoChangeDoesNotFlash(): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, true, 'Same'));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<turbo-stream>ok</turbo-stream>');

        $flasher = new RecordingFlasher();
        $flow = new InlineEditFlow($forms, $flasher, $twig);

        $response = $flow->handleField($request, 'Same', fn ($v): bool => false, $this->context());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([], $flasher->successes);
    }

    public function testInvalidPostRendersFormWith422(): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, false, ''));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form>errors</form>');

        $flow = new InlineEditFlow($forms, new RecordingFlasher(), $twig);

        $response = $flow->handleField($request, 'Old', fn (): bool => self::fail('onSave must not run'), $this->context());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[IgnoreDeprecations]
    public function testClosureValueIsReadForTheFormAndAgainAfterSaving(): void
    {
        $this->expectUserDeprecationMessage(self::CLOSURE_DEPRECATION);

        $request = Request::create('/sample/1/inline/name', 'POST');
        $stored = 'Old Name';

        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects($this->once())->method('create')
            ->with($this->anything(), new InlineFieldForm('Old Name'))
            ->willReturn($this->formMock(true, true, '  new name  '));

        $twig = new RecordingTwig();
        $flow = new InlineEditFlow($forms, new RecordingFlasher(), $twig);

        $flow->handleField(
            $request,
            static function () use (&$stored): string {
                return $stored;
            },
            function (mixed $value) use (&$stored): bool {
                $stored = ucwords(trim((string) $value)); // onSave normalises what was typed

                return true;
            },
            $this->context(),
        );

        self::assertSame('New Name', $stored);
        self::assertSame('shared/form_flow/inline_edit_success.stream.html.twig', $twig->template);
        // The stored value, not the submitted one, reaches the template.
        self::assertSame('New Name', $twig->vars['value']);
        self::assertSame('New Name', $twig->vars['mirrorText']);
    }

    public function testPlainValueGivesTheTemplateNothingToMirror(): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, true, 'New Name'));

        $twig = new RecordingTwig();

        new InlineEditFlow($forms, new RecordingFlasher(), $twig)
            ->handleField($request, 'Old Name', fn ($v): bool => true, $this->context());

        self::assertNull($twig->vars['value']);
        self::assertNull($twig->vars['mirrorText']);
    }

    public function testStringValueNamingAFunctionIsNotCalled(): void
    {
        $request = Request::create('/sample/1/inline/name', 'GET', ['edit' => '1']);

        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects($this->once())->method('create')
            ->with($this->anything(), new InlineFieldForm('phpversion'))
            ->willReturn($this->formMock(false, false, 'phpversion'));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form>edit</form>');

        new InlineEditFlow($forms, new RecordingFlasher(), $twig)
            ->handleField($request, 'phpversion', fn ($v): bool => true, $this->context());
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function startingValueProvider(): iterable
    {
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'stringable' => [new class implements \Stringable {
            public function __toString(): string
            {
                return 'SKU-1';
            }
        }, 'SKU-1'];
        yield 'null' => [null, null];
    }

    #[DataProvider('startingValueProvider')]
    public function testScalarStartingValueSeedsTheFormAsText(mixed $value, ?string $expected): void
    {
        $request = Request::create('/sample/1/inline/stock', 'GET', ['edit' => '1']);

        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects($this->once())->method('create')
            ->with($this->anything(), new InlineFieldForm($expected))
            ->willReturn($this->formMock(false, false, $expected));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form>edit</form>');

        $response = new InlineEditFlow($forms, new RecordingFlasher(), $twig)
            ->handleField($request, $value, fn ($v): bool => true, $this->context());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function savedValueProvider(): iterable
    {
        yield 'string' => ['Widget', 'Widget'];
        yield 'empty string' => ['', ''];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'stringable' => [new class implements \Stringable {
            public function __toString(): string
            {
                return 'SKU-1';
            }
        }, 'SKU-1'];
        yield 'null' => [null, null];
        yield 'bool' => [true, null];
        yield 'array' => [['a'], null];
        yield 'date' => [new \DateTimeImmutable('2026-01-01'), null];
    }

    #[DataProvider('savedValueProvider')]
    #[IgnoreDeprecations]
    public function testOnlyTextLikeSavedValuesBecomeMirrorText(mixed $saved, ?string $expected): void
    {
        $this->expectUserDeprecationMessage(self::CLOSURE_DEPRECATION);

        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, true, 'typed'));

        $twig = new RecordingTwig();

        $calls = 0;
        $read = static function () use (&$calls, $saved): mixed {
            // First call seeds the form (which holds a string); the second is the read-back.
            return ++$calls === 1 ? 'typed' : $saved;
        };

        new InlineEditFlow($forms, new RecordingFlasher(), $twig)
            ->handleField($request, $read, fn ($v): bool => true, $this->context());

        self::assertSame(2, $calls);
        self::assertSame($saved, $twig->vars['value']);
        self::assertSame($expected, $twig->vars['mirrorText']);
    }

    #[IgnoreDeprecations]
    public function testInvalidPostDoesNotReadTheValueBack(): void
    {
        $this->expectUserDeprecationMessage(self::CLOSURE_DEPRECATION);

        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, false, ''));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form>errors</form>');

        $calls = 0;
        $read = static function () use (&$calls): string {
            ++$calls;

            return 'Old';
        };

        new InlineEditFlow($forms, new RecordingFlasher(), $twig)
            ->handleField($request, $read, fn ($v): bool => true, $this->context());

        self::assertSame(1, $calls);
    }

    private function context(): InlineEditContext
    {
        return InlineEditContext::create(
            frameId: 'inline-edit-sample-1-name',
            displayTemplate: 'sample/_inline_name.html.twig',
            entity: new SampleEntity(),
        );
    }

    /**
     * @return FormInterface<mixed>
     */
    private function formMock(bool $submitted, bool $valid, mixed $value): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('getData')->willReturn(new InlineFieldForm($value));
        $form->method('createView')->willReturn(new FormView());
        // The flow turns an exception from onSave into a form error, which would swallow a self::fail() there.
        $form->method('addError')->willReturnCallback(static fn (FormError $error) => self::fail('Unexpected form error: ' . $error->getMessage()));

        return $form;
    }
}
