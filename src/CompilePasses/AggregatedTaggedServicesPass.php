<?php

namespace Prokl\ServiceProvider\CompilePasses;

use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class AggregatedTaggedServicesPass
 * Обработка сервисов с тэгом service.bootstrap.
 * @package Prokl\ServiceProvider\CompilePasses
 *
 * @since 19.09.2020 Добавил сохранение сервисов для автозагрузки в параметры контейнера.
 * @since 23.09.2020 Только сохраняются названия сервисов. Их автозагрузка происходит автономно.
 * @since 06.11.2020 Добавление к уже существующим параметрам, а не перезаписывание. Позволяет бандлам
 * подмешивать свои добавления.
 * @since 09.11.2020 Убрал ранний возврат при отсутствии тэгов.
 */
class AggregatedTaggedServicesPass implements CompilerPassInterface
{
    /** @const string TAG_BOOTSTRAP_SERVICES Тэг сервисов запускающихся при загрузке. */
    protected const TAG_BOOTSTRAP_SERVICES = 'service.bootstrap';

    /** @const string VARIABLE_CONTAINER Название переменной в контейнере. */
    protected const VARIABLE_CONTAINER = '_bootstrap';

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
            self::TAG_BOOTSTRAP_SERVICES
        );

        $params = $container->hasParameter(self::VARIABLE_CONTAINER) ?
            (array)$container->getParameter(self::VARIABLE_CONTAINER)
            : [];

        // Сервисы автозапуска.
        $container->setParameter(
            self::VARIABLE_CONTAINER,
            array_merge($params, $taggedServices)
        );
    }
}
