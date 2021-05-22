<?php

namespace Prokl\ServiceProvider\CompilePasses;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class MakePrivateEventsPublic
 * Сделать все приватные команды событий публичными.
 * @package Prokl\ServiceProvider\CompilePasses
 *
 * @since 20.12.2020
 */
final class MakePrivateCommandsPublic implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) : void
    {
        $taggedServices = $container->findTaggedServiceIds('console.command');

        foreach ($taggedServices as $id => $service) {
            $def = $container->getDefinition($id);
            $def->setPublic(true);
        }
    }
}
