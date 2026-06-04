<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Integration;

use MyVars\FormFlow\ActionFlow;
use MyVars\FormFlow\ConfirmFlow;
use MyVars\FormFlow\Contract\FlasherInterface;
use MyVars\FormFlow\FormFlow;
use MyVars\FormFlow\InlineEdit\InlineEditFlow;
use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Redirect\TurboAwareRedirector;
use MyVars\FormFlow\SearchFlow;
use MyVars\FormFlow\Tests\Double\RecordingFlasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Verifies the bundle's service definitions (the config/services.php that
 * FormFlowBundle imports) register every flow, autowire their dependencies, and
 * resolve the ports to their adapters — using a real compiled container.
 */
final class FormFlowBundleTest extends TestCase
{
    private function compileContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

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

        // Load the exact definitions the bundle imports.
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');

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

        return $container;
    }

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

    public function testRedirectorPortResolvesToTurboAwareRedirector(): void
    {
        self::assertInstanceOf(TurboAwareRedirector::class, $this->compileContainer()->get(RedirectorInterface::class));
    }

    public function testFlasherPortResolvesToTheAppAdapter(): void
    {
        self::assertInstanceOf(RecordingFlasher::class, $this->compileContainer()->get(FlasherInterface::class));
    }
}
