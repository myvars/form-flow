<?php

declare(strict_types=1);

namespace MyVars\FormFlow;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the FormFlow coordinators and their internal services.
 *
 * The flows depend on FormFlow-owned ports (Contract\*); the consuming app
 * supplies the adapters (its Result/RedirectTarget/FlashMessenger/SearchCriteria),
 * which Symfony autowires by interface.
 *
 * The bundle ships design-neutral default templates (templates/shared/form_flow/*)
 * registered as a low-priority path in Twig's main namespace, so the flows render
 * out of the box. An app overrides any of them simply by providing its own file at
 * the same path (templates/shared/form_flow/...), which Twig resolves first.
 */
final class FormFlowBundle extends AbstractBundle
{
    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(\dirname(__DIR__) . '/config/services.php');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Append the bundle's default templates to Twig's main namespace *after* the
        // app's own templates/ (a compiler pass appends, so it is searched last). This
        // makes any app-provided shared/form_flow/* template win, with the bundle
        // default used only as a fallback.
        $templatesDir = \dirname(__DIR__) . '/templates';

        $container->addCompilerPass(new class($templatesDir) implements CompilerPassInterface {
            public function __construct(private readonly string $templatesDir)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition('twig.loader.native_filesystem')) {
                    $container->getDefinition('twig.loader.native_filesystem')
                        ->addMethodCall('addPath', [$this->templatesDir]);
                }
            }
        });
    }
}
