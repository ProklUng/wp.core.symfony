<?php

namespace Prokl\ServiceProvider\PostLoadingPass;

use Exception;
use Prokl\ServiceProvider\Interfaces\PostLoadingPassInterface;
use Prokl\ServiceProvider\Traits\Eventable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class InitWordpressHooks
 *
 * Инициализация событий Wordpress через трэйт.
 *
 * @package Prokl\ServiceProvider\PostLoadingPass
 *
 * @since 26.09.2020
 * @since 27.09.2020 Доработки.
 * @since 28.09.2020 Доработки.
 */
class InitWordpressHooksViaTrait implements PostLoadingPassInterface
{
    /** @const string METHOD_INIT_EVENT Метод, инициализирующий события. */
    private const METHOD_INIT_EVENT = 'addEvent';

    /** @const string VARIABLE_PARAM_BAG Переменная в ParameterBag. */
    private const VARIABLE_PARAM_BAG = '_events_trait';

    /**
     * @inheritDoc
     */
    public function action(Container $containerBuilder): bool
    {
        $result = false;

        try {
            $eventsServices = (array)$containerBuilder->getParameter(self::VARIABLE_PARAM_BAG);
        } catch (InvalidArgumentException $e) {
            return $result;
        }

        foreach ($eventsServices as $service => $value) {
            try {
                $serviceInstance = $containerBuilder->get($service);

                $result = $this->executeTraitMethod(
                    $serviceInstance,
                    Eventable::class,
                    self::METHOD_INIT_EVENT
                );
            } catch (Exception $e) {
                continue;
            }
        }

        return $result;
    }

    /**
     * Проверить - использует ли объект трэйт. Если да, то исполнить заданный метод.
     *
     * @param mixed $object  Объект.
     * @param string $trait  Трэйт.
     * @param string $method Метод.
     *
     * @return boolean Выполнялся метод или нет.
     */
    private function executeTraitMethod($object, string $trait, string $method): bool
    {
        $result = false;

        if (!is_object($object)) {
            return false;
        }

        foreach (class_uses_recursive($object) as $traitUse) {
            if ($traitUse !== $trait) {
                continue;
            }

            // Вызов сервиса. Проверка на метод не нужна - при любом раскладе будет вызван
            // пустой метод трэйта.
            $object->{$method}();

            $result = true;
        }

        return $result;
    }
}
