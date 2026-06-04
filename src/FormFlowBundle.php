<?php

declare(strict_types=1);

namespace MyVars\FormFlow;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the FormFlow coordinators and their internal services.
 *
 * The flows depend on FormFlow-owned ports (Contract\*); the consuming app
 * supplies the adapters (its Result/RedirectTarget/FlashMessenger/SearchCriteria),
 * which Symfony autowires by interface. Templates are app-owned (a documented
 * contract), so this bundle ships no Twig.
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
}
