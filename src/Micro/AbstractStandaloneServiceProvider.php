<?php

namespace Prokl\ServiceProvider\Micro;

use Exception;
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
     * @inheritDoc
     */
    public function __construct(
        string $filename,
        string $environment = 'dev',
        bool $debug = true,
        ?string $pathBundlesConfig = null
    ) {
        $this->symfonyCompilerClass = SymfonyCompilerPassBagLight::class;

        parent::__construct($filename, $environment, $debug, $pathBundlesConfig);
    }
}
