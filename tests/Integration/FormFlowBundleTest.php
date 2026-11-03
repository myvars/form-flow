<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Integration;

use MyVars\FormFlow\ActionFlow;
use MyVars\FormFlow\ConfirmFlow;
use MyVars\FormFlow\Contract\FlasherInterface;
use MyVars\FormFlow\FormFlow;
use MyVars\FormFlow\FormFlowBundle;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Redirect\TurboAwareRedirector;
use MyVars\FormFlow\SearchFlow;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Loads and builds FormFlowBundle into a real compiled container, then checks that it
 * registers every flow, autowires their dependencies, resolves the ports to their
 * adapters, and puts its default templates behind the app's.
 */
final class FormFlowBundleTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function flowProvider(): iterable
    {
        yield 'FormFlow' => [FormFlow::class];
        yield 'ActionFlow' => [ActionFlow::class];
        yield 'ConfirmFlow' => [ConfirmFlow::class];
        yield 'SearchFlow' => [SearchFlow::class];
        yield 'InlineEditFlow' => [InlineEditFlow::class];
    }

    /**
     * @param class-string $flowClass
     */
    #[DataProvider('flowProvider')]
    public function testFlowServiceIsRegisteredAndAutowired(string $flowClass): void
    {
        self::assertInstanceOf($flowClass, $this->compileContainer()->get($flowClass));
    }

    public function testInlineEditFlowGetsTheValidatorWhenTheAppHasOne(): void
    {
        $container = $this->compileContainer(withValidator: true);

        self::assertSame(
            $container->get(ValidatorInterface::class),
            $this->validatorOf($container->get(InlineEditFlow::class)),
        );
    }

    public function testInlineEditFlowWorksWithoutAValidator(): void
    {
        self::assertNull($this->validatorOf($this->compileContainer()->get(InlineEditFlow::class)));
    }

    public function testRedirectorPortResolvesToTurboAwareRedirector(): void
    {
        self::assertInstanceOf(TurboAwareRedirector::class, $this->compileContainer()->get(RedirectorInterface::class));
    }

    public function testFlasherPortResolvesToTheAppAdapter(): void
    {
        self::assertInstanceOf(RecordingFlasher::class, $this->compileContainer()->get(FlasherInterface::class));
    }

    public function testAppTemplateWinsOverTheBundleDefault(): void
    {
        $container = new ContainerBuilder();
        // What TwigBundle registers for the app's own templates/ before any compiler pass runs.
        $container->register('twig.loader.native_filesystem', FilesystemLoader::class)
            ->addMethodCall('addPath', [\dirname(__DIR__) . '/Fixtures/templates'])
            ->setPublic(true);

        new FormFlowBundle()->build($container);
        $container->compile();

        $loader = $container->get('twig.loader.native_filesystem');
        self::assertInstanceOf(FilesystemLoader::class, $loader);

        self::assertSame(
            "<p>The app's own missing template.</p>\n",
            new Environment($loader)->render('shared/form_flow/missing.html.twig'),
        );
        self::assertSame(
            \dirname(__DIR__, 2) . '/templates/shared/form_flow/base.html.twig',
            $loader->getSourceContext('shared/form_flow/base.html.twig')->getPath(),
            'A template the app does not override comes from the bundle',
        );
    }

    /**
     * Without a Twig loader (no TwigBundle) the bundle's compiler pass does nothing,
     * which every container test above relies on.
     */
    private function compileContainer(bool $withValidator = false): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if ($withValidator) {
            $container->register(ValidatorInterface::class)->setSynthetic(true)->setPublic(true);
        }

        // External services each flow autowires (the host app/framework provides these).
        foreach ([
            FormFactoryInterface::class,
            Environment::class,
            UrlGeneratorInterface::class,
            CsrfTokenManagerInterface::class,
        ] as $external) {
            $container->register($external)->setSynthetic(true)->setPublic(true);
        }

        // The app-supplied adapter for the FlasherInterface port.
        $container->register(RecordingFlasher::class)->setAutowired(true)->setPublic(true);
        $container->setAlias(FlasherInterface::class, RecordingFlasher::class)->setPublic(true);

        $bundle = new FormFlowBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([], $container);
        $bundle->build($container);

        // Expose the flows + resolved redirector so the assertions can fetch them.
        foreach ([FormFlow::class, ActionFlow::class, ConfirmFlow::class, SearchFlow::class, InlineEditFlow::class, RedirectorInterface::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            } elseif ($container->hasAlias($id)) {
                $container->getAlias($id)->setPublic(true);
            }
        }

        $container->compile();

        // Satisfy the synthetic dependencies so the flow services can be instantiated.
        $container->set(FormFactoryInterface::class, $this->createStub(FormFactoryInterface::class));
        $container->set(Environment::class, $this->createStub(Environment::class));
        $container->set(UrlGeneratorInterface::class, $this->createStub(UrlGeneratorInterface::class));
        $container->set(CsrfTokenManagerInterface::class, $this->createStub(CsrfTokenManagerInterface::class));
        if ($withValidator) {
            $container->set(ValidatorInterface::class, $this->createStub(ValidatorInterface::class));
        }

        return $container;
    }

    private function validatorOf(?object $flow): mixed
    {
        self::assertInstanceOf(InlineEditFlow::class, $flow);

        return new \ReflectionProperty(InlineEditFlow::class, 'validator')->getValue($flow);
    }
}
