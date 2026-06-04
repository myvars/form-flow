<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Templates;

use MyVars\FormFlow\InlineEdit\InlineEditContext;
use MyVars\FormFlow\Tests\Fixtures\SampleEntity;
use MyVars\FormFlow\View\FlowRoutes;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Smoke-renders every shipped default template with its documented variables, using
 * stub implementations of the Symfony Twig functions the app normally provides. Proves
 * the defaults are valid Twig and reference only the contract variables.
 */
final class DefaultTemplatesTest extends TestCase
{
    private function twig(): Environment
    {
        $twig = new Environment(new FilesystemLoader([\dirname(__DIR__, 2) . '/templates']), ['strict_variables' => true]);

        // Stubs for the Symfony Twig functions the host app supplies.
        foreach (['form', 'form_start', 'form_end', 'form_widget', 'form_rest', 'form_errors'] as $fn) {
            $twig->addFunction(new TwigFunction($fn, static fn (): string => '', ['is_safe' => ['html']]));
        }
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $params = []): string => '/' . $route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'token-' . $id));

        return $twig;
    }

    private function inlineContext(): InlineEditContext
    {
        return InlineEditContext::create(
            frameId: 'frame-1',
            displayTemplate: 'shared/form_flow/missing.html.twig', // any resolvable template
            entity: new SampleEntity(),
        );
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function templateProvider(): iterable
    {
        $routes = FlowRoutes::fromPrefix('app_demo_task');
        $entity = (object) ['id' => 7];
        $results = new Pagerfanta(new ArrayAdapter(['a', 'b', 'c']));

        yield 'missing' => ['shared/form_flow/missing.html.twig', ['flowOperation' => 'create', 'flowModel' => 'Task']];
        yield 'base' => ['shared/form_flow/base.html.twig', ['flowOperation' => null, 'template' => 'shared/form_flow/missing.html.twig', 'flowModel' => 'Task', 'flowModelPlural' => 'Tasks']];
        yield 'create' => ['shared/form_flow/create.html.twig', ['flowModel' => 'Task', 'form' => (object) [], 'routes' => $routes]];
        yield 'update' => ['shared/form_flow/update.html.twig', ['flowModel' => 'Task', 'form' => (object) [], 'routes' => $routes]];
        yield 'filter' => ['shared/form_flow/filter.html.twig', ['flowModel' => 'Task', 'flowModelPlural' => 'Tasks', 'form' => (object) [], 'routes' => $routes]];
        yield 'delete' => ['shared/form_flow/delete.html.twig', ['flowModel' => 'Task', 'result' => $entity, 'confirmKey' => 'delete', 'routes' => $routes]];
        yield 'index' => ['shared/form_flow/index.html.twig', ['flowModel' => 'Task', 'flowModelPlural' => 'Tasks', 'results' => $results, 'routes' => $routes]];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('templateProvider')]
    public function testRendersWithoutError(string $template, array $context): void
    {
        $html = $this->twig()->render($template, $context);

        self::assertNotSame('', trim($html));
    }

    public function testInlineEditDisplayRenders(): void
    {
        $html = $this->twig()->render('shared/form_flow/inline_edit_display.html.twig', ['context' => $this->inlineContext()]);
        self::assertNotSame('', trim($html));
    }

    public function testInlineEditFormRenders(): void
    {
        $html = $this->twig()->render('shared/form_flow/inline_edit_form.html.twig', [
            'context' => $this->inlineContext(),
            'form' => (object) [],
            'cancelUrl' => '/cancel',
        ]);
        self::assertStringContainsString('turbo-frame', $html);
    }

    public function testInlineEditSuccessStreamRenders(): void
    {
        $html = $this->twig()->render('shared/form_flow/inline_edit_success.stream.html.twig', ['context' => $this->inlineContext()]);
        self::assertStringContainsString('turbo-stream', $html);
    }
}
