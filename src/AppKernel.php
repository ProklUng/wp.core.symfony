<?php

namespace Prokl\ServiceProvider;

use InvalidArgumentException;
use LogicException;
use Prokl\ServiceProvider\Bundles\BundlesLoader;
use ReflectionObject;
use RuntimeException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class AppKernel
 * @package Prokl\ServiceProvider
 *
 * @since 22.09.2020 Обнаружена ошибка - передача env переменных из контейнера.
 * @since 02.10.2020 Доточка под Symfony.
 * @since 04.10.2020 Доработка.
 * @since 23.10.2020 Причесывание.
 * @since 25.10.2020 Наследование от HttpKernel. Бридж к отдельностоящим бандлам.
 * @since 03.11.2020 Чистка.
 * @since 12.11.2020 Рефакторинг.
 * @since 13.11.2020 Рефакторинг.
 * @since 18.11.2020 Сеттер контейнера.
 * @since 20.11.2020 kernel.default_locale.
 * @since 13.12.2020 Создание директории кэша, если она не существует.
 * @since 24.12.2020 phpstan. Прогон.
 * @since 20.03.2021 Переменная kernel.logs_dir.
 */
class AppKernel extends Kernel
{
    /**
     * @const string DEV_ENVIRONMENT Как называется dev среда.
     */
    private const DEV_ENVIRONMENT = 'dev';

    /**
     * @var BundleInterface[] $bundles Бандлы.
     */
    protected $bundles;

    /**
     * @var string $environment Окружение.
     */
    protected $environment;

    /**
     * @var boolean $debug Отладка?
     */
    protected $debug;

    /**
     * @var string $projectDir DOCUMENT_ROOT.
     */
    private $projectDir = '';

    /**
     * AppKernel constructor.
     *
     * @param string  $environment Окружение.
     * @param boolean $debug       Отладка?
     */
    public function __construct(string $environment, bool $debug)
    {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->projectDir = $_SERVER['DOCUMENT_ROOT'];

        $this->initializeBundles(); // Бандлы Symfony
        $this->registerStandaloneBundles(); // "Standalone" бандлы.

        parent::__construct($this->environment, $this->debug);
    }

    /**
     * Регистрация бандлов.
     *
     * @return iterable|BundleInterface[]
     *
     * @since 02.06.2021 Если файл не существует - игнорим.
     */
    public function registerBundles(): iterable
    {
        $bundleConfigPath = $this->getProjectDir() . '/config/bundles.php';

        if (!@file_exists($bundleConfigPath)) {
            return [];
        }

        $contents = require $bundleConfigPath;

        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    /**
     * Регистрация одного бандла.
     *
     * @param Bundle $bundle Бандл.
     *
     * @return void
     */
    public function registerBundle(Bundle $bundle) : void
    {
        $name = $bundle->getName();
        if (array_key_exists($name, $this->bundles)) {
            throw new LogicException(sprintf('Trying to register two bundles with the same name "%s"', $name));
        }

        $this->bundles[$name] = $bundle;
    }

    /**
     * Returns a bundle.
     *
     * @param string $name Bundle name
     *
     * @return BundleInterface A BundleInterface instance
     *
     * @throws InvalidArgumentException when the bundle is not enabled
     */
    public function getBundle(string $name): BundleInterface
    {
        if (!array_key_exists($name, $this->bundles)) {
            $class = get_class($this);
            $class = 'c' === $class[0] && 0 === strpos(
                $class,
                "class@anonymous\0"
            ) ? get_parent_class($class).'@anonymous' : $class;

            throw new InvalidArgumentException(
                sprintf(
                    'Bundle "%s" does not exist or it is not enabled. 
                    Maybe you forgot to add it in the registerBundles() method of your %s.php file?',
                    $name,
                    $class
                )
            );
        }

        return $this->bundles[$name];
    }

    /**
     * Директория кэша.
     *
     * @return string
     * @throws RuntimeException Когда не удалось создать директорию с кэшом.
     *
     * @since 13.12.2020 Доработка.
     */
    public function getCacheDir(): string
    {
        $cachePath = $this->getProjectDir() . $this->getRelativeCacheDir();

        if (!file_exists($cachePath) && !mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $cachePath));
        }

        return $cachePath;
    }

    /**
     * Относительная директория кэша.
     *
     * @return string
     *
     * @since 13.12.2020
     */
    public function getRelativeCacheDir(): string
    {
        return '/wp-content/cache/';
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return $this->getProjectDir() . '/logs';
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     *
     * @return string The project root dir
     */
    public function getProjectDir(): string
    {
        if (!$this->projectDir) {
            $r = new ReflectionObject($this);

            if (!file_exists($dir = (string)$r->getFileName())) {
                throw new LogicException(
                    sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name)
                );
            }

            $dir = $rootDir = dirname($dir);
            while (!file_exists($dir . '/composer.json')) {
                if ($dir === dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = dirname($dir);
            }

            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * Параметры ядра. Пути, debug & etc.
     *
     * @return array
     *
     * @since 13.11.2020 Мета-данные бандлов вынесены в отдельный метод.
     */
    public function getKernelParameters(): array
    {
        $bundlesMetaData = $this->getBundlesMetaData();

        return [
            'kernel.project_dir' => realpath($this->getProjectDir()) ?: $this->getProjectDir(),
            'kernel.environment' => $this->environment,
            'kernel.debug' => $this->debug,
            'kernel.cache_dir' => realpath($this->getCacheDir()),
            'kernel.cache_dir.relative' => $this->getRelativeCacheDir(),
            'kernel.logs_dir' => $this->getLogDir(),
            'kernel.http.host' => $_SERVER['HTTP_HOST'],
            'kernel.site.host' => $this->getSiteHost(),
            'kernel.bundles' => $bundlesMetaData['kernel.bundles'],
            'kernel.bundles_metadata' => $bundlesMetaData['kernel.bundles_metadata'],
            'kernel.charset' => $this->getCharset(),
            'kernel.container_class' => $this->getContainerClass(),
            'kernel.schema' => $this->getSchema(),
            'kernel.default_locale' => 'ru',
            'debug.container.dump' => $this->debug ? '%kernel.cache_dir%/%kernel.container_class%.xml' : null,
            'kernel.ajax.url' => admin_url('admin-ajax.php')
        ];
    }

    /**
     * Мета-данные бандлов.
     *
     * @return array[]
     *
     * @since 13.11.2020
     */
    public function getBundlesMetaData() : array
    {
        $bundles = [];
        $bundlesMetadata = [];

        foreach ($this->bundles as $name => $bundle) {
            $bundles[$name] = get_class($bundle);
            $bundlesMetadata[$name] = [
                'path' => $bundle->getPath(),
                'namespace' => $bundle->getNamespace(),
            ];
        }

        return [
            'kernel.bundles' => $bundles,
            'kernel.bundles_metadata' => $bundlesMetadata
        ];
    }

    /**
     * REQUEST_URI.
     *
     * @return string
     *
     * @since 23.10.2020
     */
    public function getRequestUri() : string
    {
        return array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : '';
    }

    /**
     * @inheritDoc
     */
    public function registerContainerConfiguration(LoaderInterface $loader) : void
    {
    }

    /**
     * Регистрация "отдельностоящих" бандлов.
     *
     * @return void
     *
     * @since 25.10.2020
     */
    public function registerStandaloneBundles(): void
    {
        foreach (BundlesLoader::getBundlesMap() as $bundle) {
            $this->registerBundle($bundle);
        }
    }

    /**
     * Задать контейнер.
     *
     * @param ContainerInterface|null $container Контейнер.
     *
     * @since 18.11.2020
     */
    public function setContainer(?ContainerInterface $container = null) : void
    {
        $this->container = $container;
    }

    /**
     * Initializes bundles.
     *
     * @throws LogicException if two bundles share a common name.
     */
    protected function initializeBundles(): void
    {
        // init bundles
        $this->bundles = [];
        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            if (array_key_exists($name, $this->bundles)) {
                throw new LogicException(sprintf('Trying to register two bundles with the same name "%s"', $name));
            }

            $this->bundles[$name] = $bundle;
        }
    }

    /**
     * Gets the container class.
     *
     * @return string The container class
     *
     * @since 12.11.2020 Убрал $_ENV.
     */
    protected function getContainerClass(): string
    {
        return $this->environment === self::DEV_ENVIRONMENT ? ContainerBuilder::class : 'MyCachedContainer';
    }

    /**
     * Хост сайта.
     *
     * @return string
     *
     * @since 08.10.2020
     */
    private function getSiteHost() : string
    {
        return $this->getSchema() . (array_key_exists('HTTP_HOST', $_SERVER) ? $_SERVER['HTTP_HOST'] : '');
    }

    /**
     * Schema http or https.
     *
     * @return string
     *
     * @since 22.10.2020
     */
    private function getSchema() : string
    {
        return (array_key_exists('HTTPS', $_SERVER)
            && ($_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] === 443)
        ) ? 'https://' : 'http://';
    }
}
