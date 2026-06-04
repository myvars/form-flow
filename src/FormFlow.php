<?php

namespace MyVars\FormFlow;

use MyVars\FormFlow\Concerns\RedirectsResponses;
use MyVars\FormFlow\Contract\FlasherInterface;
use MyVars\FormFlow\Contract\ResultInterface;
use MyVars\FormFlow\Guard\AutoUpdateGuard;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\TemplateContext;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Generic form coordinator for create/update operations.
 * Centralizes form creation, validation, mapping, handler invocation, feedback, and redirect.
 */
final readonly class FormFlow
{
    use RedirectsResponses;

    public function __construct(
        private FormFactoryInterface $forms,
        private FlasherInterface $flashes,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private RedirectorInterface $redirector,
        private AutoUpdateGuard $autoUpdateForm,
    ) {
    }

    private function getRedirector(): RedirectorInterface
    {
        return $this->redirector;
    }

    private function getUrlGenerator(): UrlGeneratorInterface
    {
        return $this->urls;
    }

    /**
     * Handle GET/POST for a Symfony form and redirect on success.
     *
     * On invalid submission, re‑renders the form with validation errors.
     * On success, flashes feedback and redirects (Turbo‑aware).
     *
     * @param class-string<FormTypeInterface<mixed>> $formType
     * @param array<string, mixed>                   $formOptions
     */
    public function form(
        Request $request,
        string $formType,
        mixed $data,
        callable $mapper,
        callable $handler,
        FlowContext $context,
        array $formOptions = [],
    ): Response {
        // Validate preset preconditions up front.
        $context->validate();

        // Default the form action to the current URL (Turbo‑friendly).
        $formOptions['action'] ??= $request->getUri();

        $form = $this->forms->create($formType, $data, $formOptions);
        $form->handleRequest($request);

        // Compute HTTP status for rendering branch.
        $status = $this->getResponseStatus($form);

        // Success path: submitted, valid, and not an auto‑update submit.
        if ($form->isSubmitted() && $form->isValid() && !$this->autoUpdateForm->is($form)) {
            $command = $this->map($mapper, $form->getData());
            /** @var ResultInterface $result */
            $result = $handler($command);

            // Handler reported success
            if ($result->isOk()) {
                $this->flashes->success($request, $result->message());

                // Redirect to forced target if given.
                $target = $result->redirect();
                if ($target !== null) {
                    return $this->redirectToTarget($request, $target);
                }

                return $this->successRedirect($request, $context);
            }

            // Handler reported failure -> 422 and error flash.
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            $this->flashes->error($request, $result->message());
        }

        // In auto‑update scenarios clear blocking errors to keep UX responsive.
        $this->autoUpdateForm->clear($form);

        // GET or invalid POST fall through to render.
        return $this->render($request, $context, $form, $status, $data);
    }

    /**
     * Determine HTTP status for rendering the form page.
     *
     * @param FormInterface<mixed> $form
     */
    private function getResponseStatus(FormInterface $form): int
    {
        if ($form->isSubmitted() && (!$form->isValid() || $this->autoUpdateForm->is($form))) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_OK;
    }

    /**
     * Map form data to a command object via callable.
     *
     * @throws \LogicException When mapper does not return an object
     */
    private function map(callable $mapper, mixed $data): object
    {
        $command = $mapper($data);
        if (!\is_object($command)) {
            throw new \LogicException('Mapper must return an object.');
        }

        return $command;
    }

    /**
     * Render the base template.
     *
     * @param FormInterface<mixed> $form
     */
    private function render(
        Request $request,
        FlowContext $context,
        FormInterface $form,
        int $status,
        mixed $data,
    ): Response {
        // Build a consistent set of Twig variables for template.
        $templateContext = TemplateContext::from(
            $context->getFlowModel(),
            $context->getOperation()->value,
            $context->getTemplate(),
            $context->getRoutes(),
        );

        $html = $this->twig->render(FlowModel::BASE_TEMPLATE, array_merge(
            $templateContext->toArray(),
            [
                'flowBackLink' => $context->resolveBackUrl($request),
                'flowAllowDelete' => $context->isAllowDelete(),
                'form' => $form->createView(),
                'result' => $data,
            ]
        ));

        return new Response($html, $status);
    }
}
