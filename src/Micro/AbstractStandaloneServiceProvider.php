<?php

namespace Prokl\ServiceProvider\Micro;

use Prokl\ServiceProvider\Framework\SymfonyCompilerPassBagLight;
use Prokl\ServiceProvider\ServiceProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class AbstractStandaloneServiceProvider
 *
 * Абстракция для наследования отдельных микро-сервиспровайдеров.
 *
 * @package Prokl\ServiceProvider\Micro
 *
 * @since 04.03.2021
 * @since 04.04.2021 Вынес стандартные compile pass Symfony в отдельный класс.
 */
class AbstractStandaloneServiceProvider extends ServiceProvider
{
    /**
     * @var ContainerBuilder $containerBuilder Контейнер.
     */
    protected static $containerBuilder;

    /**
     * @var array $standartCompilerPasses Пассы Symfony.
     */
    protected $standartCompilerPasses;

    /**
     * AbstractStandaloneServiceProvider constructor.
     *
     * @param string $filename Конфиг.
     *
     * @throws \Exception Ошибка инициализации контейнера.
     */
    public function __construct(
        string $filename
    ) {
        $this->symfonyCompilerClass = SymfonyCompilerPassBagLight::class;

        parent::__construct($filename);
    }
}
