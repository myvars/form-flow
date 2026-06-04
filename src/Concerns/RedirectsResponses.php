<?php

namespace MyVars\FormFlow\Concerns;

use MyVars\FormFlow\Contract\RedirectTargetInterface;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\View\FlowContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Shared redirect logic for flow classes.
 *
 * Classes using this trait must have:
 * - private RedirectorInterface $redirector
 * - private UrlGeneratorInterface $urls
 */
trait RedirectsResponses
{
    abstract private function getRedirector(): RedirectorInterface;

    abstract private function getUrlGenerator(): UrlGeneratorInterface;

    /**
     * Redirect to the configured success URL, using Turbo stream when applicable.
     */
    public function successRedirect(Request $request, FlowContext $context): Response
    {
        return $this->getRedirector()->to(
            $request,
            $context->resolveSuccessUrl($request, $this->getUrlGenerator()),
            $context->isRedirectRefresh(),
            $context->getRedirectStatus()
        );
    }

    /**
     * Redirect to a target URL, using Turbo stream when applicable.
     * Always navigates to the specified URL (handler explicitly wants this destination).
     */
    public function redirectToTarget(Request $request, RedirectTargetInterface $redirect): Response
    {
        return $this->getRedirector()->to(
            $request,
            $this->getUrlGenerator()->generate($redirect->route(), $redirect->params()),
            refresh: false,
            status: $redirect->status(),
            forceNavigate: true,
        );
    }
}
