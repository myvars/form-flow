<?php

namespace MyVars\FormFlow;

use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\TemplateContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Coordinates a confirmable action: a confirmation page (GET) and a
 * CSRF‑validated execution (POST). Delete is the canonical case; other
 * confirmable actions (cancel, rewind, archive, …) use a distinct CSRF key
 * via FlowContext::forConfirm().
 *
 * Handles CSRF validation, user feedback, and Turbo‑aware redirects.
 */
final readonly class ConfirmFlow
{
    public function __construct(
        private Environment $twig,
        private ActionFlow $flow,
    ) {
    }

    /**
     * Render the confirmation page.
     *
     * The CSRF token key (from the context) is exposed to the template as
     * `confirmKey` so the confirm dialog scopes its token to this action.
     */
    public function confirm(object $entity, FlowContext $context): Response
    {
        $context->validate();

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
                'result' => $entity,
                'confirmKey' => $context->getConfirmKey(),
            ]
        ));

        return new Response($html, Response::HTTP_OK);
    }

    /**
     * Process the confirmed POST.
     *
     * Scopes the CSRF token to the context's confirm key + the command id and
     * delegates to ActionFlow, which validates it before calling the handler.
     *
     * @param object&object{id: int|string} $command
     */
    public function execute(
        Request $request,
        object $command,
        callable $handler,
        FlowContext $context,
    ): Response {
        return $this->flow->process(
            request: $request,
            command: $command,
            handler: $handler,
            context: (clone $context)->csrf($context->getConfirmKey() . $command->id),
        );
    }
}
