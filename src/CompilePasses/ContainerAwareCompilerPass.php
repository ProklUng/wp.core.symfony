<?php

namespace Prokl\ServiceProvider\CompilePasses;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Class ContainerAwareCompilerPass
 * @package Prokl\ServiceProvider\CompilePasses
 */
class ContainerAwareCompilerPass implements CompilerPassInterface
{
    /**
     * automatically injects the Service Container into all your services that
     * implement Symfony\Component\DependencyInjection\ContainerAwareInterface.
     *
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) : void
    {
        foreach ($container->getServiceIds() as $serviceId) {
            $definition = $container->findDefinition($serviceId);
            $class = $definition->getClass();
            if ($class
                &&
                is_a($class, ContainerAwareInterface::class, true)
                &&
                !$definition->hasMethodCall('setContainer')) {
                $definition->addMethodCall('setContainer', [new Reference('service_container')]);
            }
        }
    }
}
