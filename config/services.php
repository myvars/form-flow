<?php

declare(strict_types=1);

use MyVars\FormFlow\Redirect\RedirectorInterface;
use MyVars\FormFlow\Redirect\TurboAwareRedirector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Register the flow coordinators + their internal services. Value objects,
    // interfaces, the trait and the (app-instantiated) ObjectMapper are not services.
    $services->load('MyVars\\FormFlow\\', \dirname(__DIR__) . '/src/')
        ->exclude([
            \dirname(__DIR__) . '/src/FormFlowBundle.php',
            \dirname(__DIR__) . '/src/Contract/',
            \dirname(__DIR__) . '/src/Concerns/',
            \dirname(__DIR__) . '/src/View/',
            \dirname(__DIR__) . '/src/Mapper/',
            \dirname(__DIR__) . '/src/InlineEdit/InlineEditContext.php',
            \dirname(__DIR__) . '/src/InlineEdit/InlineFieldForm.php',
        ]);

    // The Turbo-aware redirector is the only implementation of the port.
    $services->alias(RedirectorInterface::class, TurboAwareRedirector::class);
};
