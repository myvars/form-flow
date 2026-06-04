<?php

namespace MyVars\FormFlow;

use MyVars\FormFlow\Contract\FlasherInterface;
use MyVars\FormFlow\Contract\SearchCriteriaInterface;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\View\FlowContext;
use MyVars\FormFlow\View\FlowModel;
use MyVars\FormFlow\View\TemplateContext;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Renders an index view with pagination and handles out‑of‑range page requests.
 */
final readonly class SearchFlow
{
    public function __construct(
        private Environment $twig,
        private FlasherInterface $flashes,
        private RedirectorInterface $redirector,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param AdapterInterface<mixed> $adapter Pagerfanta adapter for the matching rows
     *                                         (e.g. $repository->findByCriteria($criteria))
     */
    public function search(
        Request $request,
        AdapterInterface $adapter,
        SearchCriteriaInterface $criteria,
        FlowContext $context,
    ): Response {
        $context->validateForSearch();
        $operation = $context->getOperation()->value;

        try {
            $pagination = Pagerfanta::createForCurrentPageWithMaxPerPage(
                $adapter,
                $criteria->getPage(),
                $criteria->getLimit(),
            );
        } catch (OutOfRangeCurrentPageException) {
            $this->flashes->warning($request, 'Page ' . $criteria->getPage() . ' not found.');
            $url = $this->urls->generate($context->getRoutes()->index, array_merge(
                $request->query->all(),
                ['page' => '1'],
            ));

            return $this->redirector->to($request, $url);
        }

        $templateContext = TemplateContext::from(
            $context->getFlowModel(),
            $operation,
            routes: $context->getRoutes(),
        );

        $html = $this->twig->render(FlowModel::BASE_TEMPLATE, array_merge(
            $templateContext->toArray(),
            ['results' => $pagination],
        ));

        return new Response($html, Response::HTTP_OK);
    }
}
