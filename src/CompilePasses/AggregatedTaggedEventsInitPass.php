<?php

namespace Prokl\ServiceProvider\CompilePasses;

use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class AggregatedTaggedEventsInitPass
 * Инициализатор событий. Тэг custom.events
 * @package Prokl\ServiceProvider\CompilePasses
 *
 * @since 26.09.2020
 * @since 06.11.2020 Добавление к уже существующим параметрам, а не перезаписывание. Позволяет бандлам
 * подмешивать свои добавления.
 * @since 09.11.2020 Убрал ранний возврат при отсутствии тэгов.
 */
final class AggregatedTaggedEventsInitPass implements CompilerPassInterface
{
    /**
     * @const string TAG_EVENTS_INIT_SERVICES Тэг сервисов запускающихся для инициализации событий.
     */
    private const TAG_EVENTS_INIT_SERVICES = 'custom.events.init';

    /**
     * @const string VARIABLE_CONTAINER Название переменной в контейнере.
     */
    private const VARIABLE_CONTAINER = '_events';

    /**
     * Движуха.
     *
     * @param ContainerBuilder $container Контейнер.
     *
     * @return void
     * @throws Exception Когда проблемы с получением параметров из контейнера.
     */
    public function process(ContainerBuilder $container) : void
    {
        $taggedServices = $container->findTaggedServiceIds(self::TAG_EVENTS_INIT_SERVICES);

        $params = $container->hasParameter(self::VARIABLE_CONTAINER) ?
            (array)$container->getParameter(self::VARIABLE_CONTAINER)
            : [];

        $container->setParameter(
            self::VARIABLE_CONTAINER,
            array_merge($params, $taggedServices)
        );
    }
}
