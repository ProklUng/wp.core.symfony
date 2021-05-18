<?php

namespace Prokl\ServiceProvider\PostLoadingPass;

use Exception;
use Prokl\ServiceProvider\Interfaces\PostLoadingPassInterface;
use Prokl\ServiceProvider\PostLoadingPass\Exceptions\RuntimePostLoadingPassException;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class InitWordpressHooks
 *
 * Инициализация событий Wordpress.
 *
 * @package Prokl\ServiceProvider\PostLoadingPass
 *
 * @since 26.09.2020
 * @since 27.09.2020 Доработки.
 */
class InitWordpressHooks implements PostLoadingPassInterface
{
    /** @const string METHOD_INIT_EVENT Метод, инициализирующий события. */
    private const METHOD_INIT_EVENT = 'addEvent';

    /** @const string VARIABLE_PARAM_BAG Переменная в ParameterBag. */
    private const VARIABLE_PARAM_BAG = '_events';

    /**
     * @inheritDoc
     *
     * @throws RuntimePostLoadingPassException
     * @throws Exception
     */
    public function action(Container $containerBuilder): bool
    {
        $result = false;

        try {
            $eventsServices = $containerBuilder->getParameter(self::VARIABLE_PARAM_BAG);
        } catch (InvalidArgumentException $e) {
            return $result;
        }

        if (!$eventsServices || !is_array($eventsServices)) {
            return $result;
        }

        foreach ($eventsServices as $service => $value) {
            $serviceInstance = $containerBuilder->get($service);
            if (is_array($value) && $value && $serviceInstance) {
                $this->processEventItem($serviceInstance, $value);
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Обработать параметры события и запустить обработчик.
     *
     * @param object $service Экземпляр сервиса.
     * @param array  $arData  Данные.
     *
     * @return boolean
     * @throws RuntimePostLoadingPassException
     */
    private function processEventItem(object $service, array $arData): bool
    {
        $result = false;

        foreach ($arData as $item) {
            if (!$item) {
                throw new RuntimePostLoadingPassException(
                    'InitEvents PostLoadingPass: params void.'
                );
            }

            if (!$item['event']) {
                throw new RuntimePostLoadingPassException(
                    'InitEvents PostLoadingPass: name event apsent.'
                );
            }

            /**
             * Функция подвязки события. Аналогична соответствующим функциям Wordpress:
             * add_action, add_filter & etc.
             */
            $type = $item['type'] ?? 'add_action';
            $priority = $item['priority'] ?? 10; // Приоритет.
            $method = $item['method'] ?? self::METHOD_INIT_EVENT; // Метод.

            if (!method_exists($service, $method)) {
                throw new RuntimePostLoadingPassException(
                    sprintf(
                        'InitEvents PostLoadingPass: method %s of class listener %s not exist.',
                        $method,
                        get_class($service)
                    )
                );
            }

            // Инициализация события.
            $type($item['event'], [$service, $method], $priority);
            $result = true;
        }

        return $result;
    }
}
