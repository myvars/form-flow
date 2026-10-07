<?php

namespace MyVars\FormFlow\InlineEdit;

use MyVars\FormFlow\Contract\FlasherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Flow handler for inline edit operations (Turbo-native pattern).
 *
 * Single endpoint handles three modes:
 * - GET (no ?edit): Returns display template inside turbo-frame
 * - GET ?edit=1: Returns form inside turbo-frame
 * - POST: Processes form, returns Turbo Stream to replace frame
 *
 * The onSave callback owns persistence (apply the value and flush) and returns
 * whether anything changed, so the flow stays out of the database.
 *
 * Pass the current value as a Closure to have the flow read it again after saving
 * and send it to elements outside the frame that mirror the field (see handleField()).
 *
 * Usage:
 *   return $flow->handleField(
 *       request: $request,
 *       value: $manufacturer->getName(),
 *       onSave: function ($value) use ($manufacturer, $flusher) {
 *           $manufacturer->rename($value);
 *
 *           return $flusher->flush();
 *       },
 *       context: InlineEditContext::create(...),
 *       formOptions: ['constraints' => [new NotBlank()]],
 *   );
 */
final readonly class InlineEditFlow
{
    private const string DISPLAY_TEMPLATE = 'shared/form_flow/inline_edit_display.html.twig';

    private const string FORM_TEMPLATE = 'shared/form_flow/inline_edit_form.html.twig';

    private const string SUCCESS_TEMPLATE = 'shared/form_flow/inline_edit_success.stream.html.twig';

    public function __construct(
        private FormFactoryInterface $forms,
        private FlasherInterface $flashes,
        private Environment $twig,
    ) {
    }

    /**
     * Simple handler for single-field inline editing.
     *
     * Uses the generic InlineFieldType and a simple onSave callback. The callback
     * owns persistence (apply the value and flush); return whether anything actually
     * changed to control the success flash (null/void is treated as changed).
     *
     * The success response only replaces the field's own Turbo Frame. To keep other copies of
     * the value on the page in step (a breadcrumb, a heading), pass $value as a Closure: the flow
     * calls it for the form's starting value and again after onSave, and gives the stored result
     * to the success template, which updates every element marked
     * data-inline-edit-mirror="<frameId>". It is read back rather than taken from the submission
     * so the mirrors show what was stored, not what was typed. Only text-like values are mirrored.
     *
     * @param mixed|\Closure(): mixed      $value       Current field value, or a Closure returning it
     * @param callable(mixed): (bool|null) $onSave      Apply the new value and persist; return whether it changed
     * @param array<string, mixed>         $formOptions Options for InlineFieldType (constraints, field_type, etc.)
     */
    public function handleField(
        Request $request,
        mixed $value,
        callable $onSave,
        InlineEditContext $context,
        array $formOptions = [],
    ): Response {
        // GET without ?edit - return display mode
        if ($request->isMethod('GET') && !$request->query->has('edit')) {
            return $this->renderDisplay($context);
        }

        // Derive cancelUrl from request path if not provided
        $cancelUrl = $context->cancelUrl ?? $request->getPathInfo();
        $formOptions['action'] ??= $cancelUrl;

        // Map 'constraints' to 'value_constraints' to avoid conflict with Symfony's built-in option
        if (isset($formOptions['constraints'])) {
            $formOptions['value_constraints'] = $formOptions['constraints'];
            unset($formOptions['constraints']);
        }

        // Only a Closure is treated as a reader: a plain string value may happen to name a function.
        $read = $value instanceof \Closure ? $value : null;
        $current = $read !== null ? $read() : $value;

        $form = $this->forms->create(InlineFieldType::class, new InlineFieldForm($current), $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var InlineFieldForm $formData */
                $formData = $form->getData();
                $changed = $onSave($formData->value);

                return $this->renderSuccess($request, $context, $changed ?? true, $read !== null ? $read() : null);
            } catch (\Throwable $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->renderForm($form, $context, $cancelUrl);
    }

    /**
     * Render the display mode inside its Turbo Frame.
     */
    private function renderDisplay(InlineEditContext $context): Response
    {
        $html = $this->twig->render(self::DISPLAY_TEMPLATE, [
            'context' => $context,
        ]);

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Render the inline edit form inside its Turbo Frame.
     *
     * @param FormInterface<mixed> $form
     */
    private function renderForm(FormInterface $form, InlineEditContext $context, string $cancelUrl): Response
    {
        $html = $this->twig->render(self::FORM_TEMPLATE, [
            'form' => $form->createView(),
            'context' => $context,
            'cancelUrl' => $cancelUrl,
        ]);

        $status = $form->isSubmitted()
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;

        return new Response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Render success Turbo Stream that replaces the frame with display.
     *
     * @param mixed $savedValue The value read back after saving, or null when the caller gave no reader
     */
    private function renderSuccess(
        Request $request,
        InlineEditContext $context,
        bool $changed = true,
        mixed $savedValue = null,
    ): Response {
        // Add flash message only if something actually changed
        if ($changed && $context->successMessage !== null) {
            $this->flashes->success($request, $context->successMessage);
        }

        $html = $this->twig->render(self::SUCCESS_TEMPLATE, [
            'context' => $context,
            'value' => $savedValue,
            'mirrorText' => self::mirrorText($savedValue),
        ]);

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/vnd.turbo-stream.html; charset=UTF-8',
        ]);
    }

    /**
     * The saved value as text for mirror elements, or null when it has no plain-text form
     * (dates, enums, objects and arrays need formatting the application owns).
     */
    private static function mirrorText(mixed $savedValue): ?string
    {
        if (\is_string($savedValue) || \is_int($savedValue) || \is_float($savedValue) || $savedValue instanceof \Stringable) {
            return (string) $savedValue;
        }

        return null;
    }
}
