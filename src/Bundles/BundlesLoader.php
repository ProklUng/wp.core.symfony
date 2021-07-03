<?php

namespace Prokl\ServiceProvider\Bundles;

use Prokl\ServiceProvider\CompilePasses\MakePrivateCommandsPublic;
use Prokl\ServiceProvider\CompilePasses\MakePrivateEventsPublic;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

/**
 * Class BundlesLoader
 * Загрузчик бандлов.
 * @package Prokl\ServiceProvider\Bundles
 *
 * @since 24.10.2020
 * @since 25.10.2020 Доработка.
 * @since 08.11.2020 Устранение ошибки, связанной с многократной загрузкой конфигурации бандлов.
 * @since 11.11.2020 - регистрация extensions перед build.
 * @since 19.11.2020 Сделать все приватные подписчики событий публичными.
 * @since 20.12.2020 Сделать все приватные консольные команды публичными.
 * @since 04.03.2021 Возможность загрузки бандлов несколькими провайдерами.
 * @since 27.04.2021 Баг-фикс: при скомпилированном контейнере не запускался метод boot бандлов.
 * @since 03.07.2021 Поддержка ключей окружения при загрузке бандлов.
 */
class BundlesLoader
{
    /**
     * @const string PATH_BUNDLES_CONFIG Путь к конфигурационному файлу.
     */
    private const PATH_BUNDLES_CONFIG = '/config/standalone_bundles.php';

    /**
     * @var ContainerBuilder $container Контейнер.
     */
    private $container;

    /**
     * @var array Конфигурация бандлов.
     */
    private $bundles = [];

    /**
     * @var array $bundlesMap Инициализированные классы бандлов.
     */
    private static $bundlesMap = [];

    /**
     * @var string $environment Окружение.
     */
    private $environment;

    /**
     * BundlesLoader constructor.
     *
     * @param ContainerBuilder $container   Контейнер в стадии формирования.
     * @param string           $environment Окружение.
     * @param string           $configPath  Путь к bundles.php (конфигурация бандлов).
     */
    public function __construct(
        ContainerBuilder $container,
        string $environment,
        string $configPath = ''
    ) {
        $configPath = $configPath ?: self::PATH_BUNDLES_CONFIG;

        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $configPath)) {
            $this->bundles = require $_SERVER['DOCUMENT_ROOT'] . $configPath;
        }

        $this->container = $container;
        $this->environment = $environment;

        static::$bundlesMap[static::class] = [];
    }

    /**
     * Инициализация бандлов.
     *
     * @return void
     *
     * @throws InvalidArgumentException Не найден класс бандла.
     */
    public function load() : void
    {
        foreach ($this->bundles as $bundleClass => $envs) {
            if (!class_exists($bundleClass)) {
                throw new InvalidArgumentException(
                    sprintf('Bundle class %s not exist.', $bundleClass)
                );
            }

            if (!array_key_exists($this->environment, (array)$envs)
                &&
                !array_key_exists('all', (array)$envs)
            ) {
                continue;
            }

            if (!method_exists($bundleClass, 'getContainerExtension')) {
                throw new InvalidArgumentException(
                    sprintf('Bundle %s dont have implemented getContainerExtension method.', $bundleClass)
                );
            }

            /**
             * @var Bundle $bundle Бандл.
             */
            $bundle = new $bundleClass;

            if ((bool)$_ENV['APP_DEBUG'] === true) {
                $this->container->addObjectResource($bundle);
            }

            $extension = $bundle->getContainerExtension();
            if ($extension !== null) {
                $this->container->registerExtension($extension);
                $bundle->build($this->container);

                // Сделать все приватные подписчики событий публичными.
                // Без этого они почему-то не подхватываются при загрузке бандлов.
                $this->container->addCompilerPass(new MakePrivateEventsPublic());

                // Сделать все приватные команды публичными.
                // Без этого они почему-то не подхватываются при загрузке бандлов.
                $this->container->addCompilerPass(
                    new MakePrivateCommandsPublic()
                );
            }

            // Сохраняю инстанцированный бандл в статику.
            static::$bundlesMap[static::class][$bundle->getName()] = $bundle;
        }
    }

    /**
     * Бандлы.
     *
     * @return array
     */
    public function bundles() : array
    {
        return static::$bundlesMap[static::class] ?? [];
    }

    /**
     * Регистрация extensions.
     *
     * @param ContainerBuilder $container Контейнер.
     *
     * @return void
     */
    public function registerExtensions(ContainerBuilder $container) : void
    {
        // Extensions in container.
        $extensions = [];
        foreach ($container->getExtensions() as $extension) {
            $extensions[] = $extension->getAlias();
        }

        // ensure these extensions are implicitly loaded
        $container->getCompilerPassConfig()
                        ->setMergePass(
                            new MergeExtensionConfigurationPass($extensions)
                        );
    }

    /**
     * Инстанцы бандлов.
     *
     * @return array
     */
    public static function getBundlesMap() : array
    {
        return static::$bundlesMap[static::class] ?? [];
    }

    /**
     * Boot bundles.
     *
     * @param ContainerInterface $container Контейнер.
     *
     * @return void
     *
     * @since 11.11.2020
     */
    public function boot(ContainerInterface $container) : void
    {
        /**
         * @var Bundle $bundle
         */
        foreach (static::$bundlesMap[static::class] as $bundle) {
            $bundle->setContainer($container);
            $bundle->boot();
        }
    }

    /**
     * Запуск метода boot у бандлов, когда контейнер скомпилирован.
     *
     * @param ContainerInterface $container Контейнер.
     *
     * @return void
     *
     * @since 27.04.2021 Баг-фикс: при скомпилированном контейнере не запускался метод boot бандлов.
     */
    public static function bootAfterCompilingContainer(ContainerInterface $container) : void
    {
        if (!$container->hasParameter('kernel.bundles')) {
            return;
        }

        /**
         * @var Bundle $bundle
         */
        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $bundleObject = new $bundle;
            $bundleObject->setContainer($container);
            $bundleObject->boot();
        }
    }
}
