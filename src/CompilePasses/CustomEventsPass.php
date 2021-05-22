<?php

namespace Prokl\ServiceProvider\CompilePasses;

use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class CustomEventsPass
 * Инициализатор событий. Тэг custom.symfony.events
 * @package Prokl\ServiceProvider\CompilePasses
 *
 * @since 24.11.2020
 */
class CustomEventsPass implements CompilerPassInterface
{
    /**
     * @const string TAG_EVENTS_INIT_SERVICES Тэг сервисов запускающихся для инициализации событий.
     */
    private const TAG_EVENTS_INIT_SERVICES = 'custom.symfony.event.listener';

    /**
     * @const string VARIABLE_CONTAINER Название переменной в контейнере.
     */
    private const VARIABLE_CONTAINER = '_symfony_events';

    /**
     * Движуха.
     *
     * @param ContainerBuilder $container Контейнер.
     *
     * @return void
     * @throws Exception
     */
    public function process(ContainerBuilder $container) : void
    {
        $taggedServices = $container->findTaggedServiceIds(
            self::TAG_EVENTS_INIT_SERVICES
        );

        $params = $container->hasParameter(self::VARIABLE_CONTAINER) ?
            (array)$container->getParameter(self::VARIABLE_CONTAINER)
            : [];

        $container->setParameter(
            self::VARIABLE_CONTAINER,
            array_merge($params, $taggedServices)
        );
    }
}
