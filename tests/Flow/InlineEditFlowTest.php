<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\InlineEdit\InlineFieldForm;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Fixtures\SampleEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class InlineEditFlowTest extends TestCase
{
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

        return $form;
    }

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

        $called = false;
        $flow = new InlineEditFlow($forms, new RecordingFlasher(), $twig);

        $response = $flow->handleField(
            $request,
            'Old',
            function ($v) use (&$called): bool {
                $called = true;

                return true;
            },
            $this->context(),
        );

        self::assertFalse($called, 'onSave must not run for an invalid submission');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testClosureValueIsReadForTheFormAndAgainAfterSaving(): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');
        $stored = 'Old Name';

        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects($this->once())->method('create')
            ->with($this->anything(), $this->callback(static fn (InlineFieldForm $data): bool => $data->value === 'Old Name'))
            ->willReturn($this->formMock(true, true, '  new name  '));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with(
                'shared/form_flow/inline_edit_success.stream.html.twig',
                // The stored value, not the submitted one, reaches the template.
                $this->callback(static fn (array $vars): bool => $vars['value'] === 'New Name' && $vars['mirrorText'] === 'New Name'),
            )
            ->willReturn('<turbo-stream>ok</turbo-stream>');

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
    }

    public function testPlainValueGivesTheTemplateNothingToMirror(): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, true, 'New Name'));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with($this->anything(), $this->callback(static fn (array $vars): bool => $vars['value'] === null && $vars['mirrorText'] === null))
            ->willReturn('<turbo-stream>ok</turbo-stream>');

        (new InlineEditFlow($forms, new RecordingFlasher(), $twig))
            ->handleField($request, 'Old Name', fn ($v): bool => true, $this->context());
    }

    public function testStringValueNamingAFunctionIsNotCalled(): void
    {
        $request = Request::create('/sample/1/inline/name', 'GET', ['edit' => '1']);

        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects($this->once())->method('create')
            ->with($this->anything(), $this->callback(static fn (InlineFieldForm $data): bool => $data->value === 'phpversion'))
            ->willReturn($this->formMock(false, false, 'phpversion'));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form>edit</form>');

        (new InlineEditFlow($forms, new RecordingFlasher(), $twig))
            ->handleField($request, 'phpversion', fn ($v): bool => true, $this->context());
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
    public function testOnlyTextLikeSavedValuesBecomeMirrorText(mixed $saved, ?string $expected): void
    {
        $request = Request::create('/sample/1/inline/name', 'POST');

        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($this->formMock(true, true, 'typed'));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with($this->anything(), $this->callback(static fn (array $vars): bool => $vars['value'] === $saved && $vars['mirrorText'] === $expected))
            ->willReturn('<turbo-stream>ok</turbo-stream>');

        $calls = 0;
        $read = static function () use (&$calls, $saved): mixed {
            // First call seeds the form (which holds a string); the second is the read-back.
            return ++$calls === 1 ? 'typed' : $saved;
        };

        (new InlineEditFlow($forms, new RecordingFlasher(), $twig))
            ->handleField($request, $read, fn ($v): bool => true, $this->context());

        self::assertSame(2, $calls);
    }

    public function testInvalidPostDoesNotReadTheValueBack(): void
    {
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

        (new InlineEditFlow($forms, new RecordingFlasher(), $twig))
            ->handleField($request, $read, fn ($v): bool => true, $this->context());

        self::assertSame(1, $calls);
    }
}
