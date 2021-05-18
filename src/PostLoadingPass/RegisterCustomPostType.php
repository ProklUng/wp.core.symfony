<?php

namespace Prokl\ServiceProvider\PostLoadingPass;

use Exception;
use Prokl\ServiceProvider\Interfaces\PostLoadingPassInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class RegisterCustomPostType
 *
 * Регистрация кастомных типов постов.
 *
 * @package Prokl\ServiceProvider\PostLoadingPass
 *
 * @since 27.09.2020
 */
class RegisterCustomPostType implements PostLoadingPassInterface
{
    /**
     * @const string SERVICE_REGISTRATOR Сервис-регистратор кастомных типов постов.
     */
    private const SERVICE_REGISTRATOR = 'custom.post.type.registrator';

    /**
     * @inheritDoc
     *
     * @return boolean
     */
    public function action(Container $containerBuilder) : bool
    {
        $result = false;

        try {
            $registrator = $containerBuilder->get(self::SERVICE_REGISTRATOR);
        } catch (ServiceNotFoundException | Exception $e) {
            return false;
        }

        // Регистрация.
        if ($registrator) {
            $registrator->registerPostType();
            $result = true;
        }

        return $result;
    }
}
