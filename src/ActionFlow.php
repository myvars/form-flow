<?php

namespace MyVars\FormFlow;

use MyVars\FormFlow\Concerns\RedirectsResponses;
use MyVars\FormFlow\Contract\FlasherInterface;
use MyVars\FormFlow\Contract\ResultInterface;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\View\FlowContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Executes commands directly without forms (state transitions, actions).
 * Handles user feedback and Turbo‑aware redirects.
 */
final readonly class ActionFlow
{
    use RedirectsResponses;

    public function __construct(
        private FlasherInterface $flashes,
        private RedirectorInterface $redirector,
        private UrlGeneratorInterface $urls,
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
     * Delegates to handler, maps known failures to user messages,
     * and always finishes with a redirect (Turbo stream or normal).
     */
    public function process(
        Request $request,
        object $command,
        callable $handler,
        FlowContext $context,
    ): Response {
        // Validate context preconditions up front.
        $context->validateForCommand();

        // Delegate to the application‑level handler.
        /** @var ResultInterface $result */
        $result = $handler($command);

        // Handler reported success
        if ($result->isOk()) {
            $this->flashes->success($request, $result->message());
        } else {
            $this->flashes->error($request, $result->message());
        }

        // Redirect to forced target if given.
        $target = $result->redirect();
        if ($target !== null) {
            return $this->redirectToTarget($request, $target);
        }

        return $this->successRedirect($request, $context);
    }
}
