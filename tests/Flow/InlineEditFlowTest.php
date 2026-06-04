<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Flow;

use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\InlineEdit\InlineFieldForm;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use MyVars\FormFlow\Tests\Fixtures\SampleEntity;
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
}
