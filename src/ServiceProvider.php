<?php

namespace Prokl\ServiceProvider;

use Exception;
use Prokl\ServiceProvider\Bundles\BundlesLoader;
use Prokl\ServiceProvider\Framework\SymfonyCompilerPassBag;
use InvalidArgumentException;
use Prokl\ServiceProvider\Utils\ContextDetector;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;

/**
 * Class ServiceProvider
 * @package Prokl\ServiceProvider
 *
 * @since 11.09.2020 Расширение функционала.
 * @since 19.09.2020 Автозагрузка сервисов в компилированном контейнере.
 * @since 23.09.2020 Набор стандартных Compile Pass
 * @since 26.09.2020 Баг: двойная автозагрузка сервисов. Вынесение загрузки в отдельный метод.
 * Механизм PostLoadingPass, исполняющихся после загрузки контейнера (автозагрузка и т.п.).
 * Compile Pass теперь массивом, чтобы удобно их инициализировать.
 * @since 29.09.2020 Доработки.
 * @since 30.09.2020 Доработки. isPHPUnit.
 * @since 02.10.2020 Расширение функционала.
 * @since 08.10.2020 Функционал ExtraFeature.
 * @since 24.10.2020 Загрузка "автономных" бандлов Symfony.
 * @since 25.10.2020 Удалил загрузку обычных бандлов Symfony.
 * @since 01.11.2020 Конфигурация сервисов Symfony Mailer.
 * @since 06.11.2020 Подгрузка конфигов из папки config.
 * @since 08.11.2020 Устранение ошибки, связанной с многократной загрузкой конфигурации бандлов.
 * @since 12.11.2020 Окружение и debug передаются снаружи. Рефакторинг.
 * @since 25.11.2020 EventDispatcher вынес в конфиг.
 * @since 28.11.2020 Конфигурация Symfony Cache.
 * @since 12.12.2020 DoctrineDbalExtension.
 * @since 21.12.2020 Нативная поддержка аннотированных роутов.
 * @since 25.12.2020 Рефакторинг по мотивам рекомендаций phpstan.
 * @since 03.03.2021 Разные компилированные контейнеры в зависмости от файла конфигурации.
 * @since 20.03.2021 Поддержка разных форматов (Yaml, php, xml) конфигурации контейнера. Удаление ExtraFeature
 * внутрь соответствующего класса.
 * @since 04.04.2021 Вынес стандартные compile pass Symfony в отдельный класс.
 * @since 14.04.2021 Метод boot бандлов вызывается теперь после компиляции контейнера.
 * @since 27.04.2021 Баг-фикс: при скомпилированном контейнере не запускался метод boot бандлов.
 *
 * @method static Container|null instance()
 * @method static mixed get()
 * @method static mixed getParameter()
 */
class ServiceProvider
{
    /**
     * @const string SERVICE_CONFIG_FILE Конфигурация сервисов.
     */
    private const SERVICE_CONFIG_FILE = 'app/symfony/services.yaml';

    /**
     * @const string COMPILED_CONTAINER_PATH Файл с сскомпилированным контейнером.
     */
    private const COMPILED_CONTAINER_FILE = '/container.php';

    /**
     * @const string COMPILED_CONTAINER_DIR Путь к скомпилированному контейнеру.
     */
    private const COMPILED_CONTAINER_DIR = '/wp-content/cache/symfony-app';

    /**
     * @const string CONFIG_EXTS Расширения конфигурационных файлов.
     */
    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    /**
     * @var ContainerBuilder $containerBuilder Контейнер.
     */
    protected static $containerBuilder;

    /**
     * @var ContainerInterface[] $delegatedContainers Делегированные контейнеры.
     */
    protected static $delegatedContainers = [];

    /**
     * @var ShowErrorScreen $errorHandler Обработчик ошибок.
     */
    private $errorHandler;

    /**
     * @var Filesystem $filesystem Файловая система.
     */
    private $filesystem;

    /**
     * @var BundlesLoader $bundlesLoader Загрузчик бандлов.
     */
    private $bundlesLoader;

    /**
     * @var string $filename Yaml файл конфигурации.
     */
    private $filename;

    /**
     * @var array $compilerPassesBag Набор Compiler Pass.
     */
    private $compilerPassesBag = [];

    /**
     * @var array $postLoadingPassesBag Пост-обработчики (PostLoadingPass) контейнера.
     */
    private $postLoadingPassesBag = [];

    /**
     * @var array $bundles
     */
    private $bundles = [];

    /**
     * @var string $pathBundlesConfig Путь к конфигурации бандлов.
     */
    protected $pathBundlesConfig = '/config/standalone_bundles.php';

    /**
     * @var string $configDir Папка, где лежат конфиги.
     */
    protected $configDir = '/config';

    /**
     * @var string $environment Среда.
     */
    private $environment;

    /**
     * @var boolean $debug Режим отладки.
     */
    private $debug;

    /**
     * @var array $standartCompilerPasses Пассы Symfony.
     */
    protected $standartCompilerPasses = [];

    /**
     * @var string $symfonyCompilerClass Класс с симфоническими compiler passes.
     */
    protected $symfonyCompilerClass = SymfonyCompilerPassBag::class;

    /**
     * ServiceProvider constructor.
     *
     * @param string      $filename          Конфигурационный Yaml файл.
     * @param string      $environment       Среда.
     * @param boolean     $debug             Режим отладки.
     * @param string|null $pathBundlesConfig Путь к конфигурации бандлов.
     *
     * @since 12.11.2020 Окружение и debug передаются снаружи.
     * @since 01.06.2021 Путь к конфигурации бандлов можно задать снаружи.
     */
    public function __construct(
        string $filename = self::SERVICE_CONFIG_FILE,
        string $environment = 'dev',
        bool $debug = true,
        ?string $pathBundlesConfig = null
    ) {
        $this->filename = $filename;
        $this->errorHandler = new ShowErrorScreen();
        $this->filesystem = new Filesystem();

        // Изменить обработчик ошибок, если запускаемся в CLI.
        if (ContextDetector::isCli()) {
            add_filter('wp_die_handler', [$this->errorHandler, 'wpDieCliHandler']);
        }

        if ($pathBundlesConfig !== null) {
            $this->pathBundlesConfig = $pathBundlesConfig;
        }

        $this->environment = $environment;
        $this->debug = $debug;

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (static::$containerBuilder !== null) {
            return;
        }

        $frameworkCompilePasses = new $this->symfonyCompilerClass;
        $this->standartCompilerPasses = $frameworkCompilePasses->getStandartCompilerPasses();

        // Кастомные Compile pass & PostLoadingPass.
        $customCompilePassesBag = new CustomCompilePassBag();

        $this->compilerPassesBag = $customCompilePassesBag->getCompilerPassesBag();
        $this->postLoadingPassesBag = $customCompilePassesBag->getPostLoadingPassesBag();

        try {
            $this->initContainer($filename);
        } catch (Exception $e) {
            $this->errorHandler->die('Ошибка сервис-контейнера: '.$e->getMessage());

            return;
        }
    }

    /**
     * Контейнер.
     *
     * @return ContainerInterface
     * @throws Exception Ошибки контейнера.
     */
    public function container(): ContainerInterface
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        return static::$containerBuilder ?? $this->initContainer($this->filename);
    }

    /**
     * Жестко установить контейнер.
     *
     * @param ContainerInterface $container Контейнер.
     *
     * @return void
     */
    public function setContainer(ContainerInterface $container): void
    {
        /** @psalm-suppress PropertyTypeCoercion */
        static::$containerBuilder = $container; // @phpstan-ignore-line
    }

    /**
     * Задать делегированный контейнер.
     *
     * @param ContainerInterface $container Делегированный контейнер.
     *
     * @return void
     *
     * @since 21.03.2021
     */
    public static function addDelegatedContainer(ContainerInterface $container) : void
    {
        static::$delegatedContainers[get_class($container)] = $container; // @phpstan-ignore-line
    }

    /**
     * Все делегированные контейнеры.
     *
     * @return ContainerInterface[]
     *
     * @since 21.03.2021
     */
    public function getDelegatedContainers() : array
    {
        return static::$delegatedContainers;
    }

    /**
     * Сбросить контейнер.
     *
     * @return void
     *
     * @since 30.09.2020
     */
    public static function resetContainer() : void
    {
        // @phpstan-ignore-next-line
        static::$containerBuilder = null;
    }

    /**
     * Инициализировать контейнер.
     *
     * @param string $fileName Имя конфигурационного файла.
     *
     * @return mixed
     * @throws Exception Ошибки контейнера.
     *
     * @since 26.09.2020 Рефакторинг.
     */
    private function initContainer(string $fileName)
    {
        // Если в dev режиме, то не компилировать контейнер.
        if ($this->environment === 'dev') {
            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (static::$containerBuilder !== null) {
                return static::$containerBuilder;
            }

            // Загрузить, инициализировать и скомпилировать контейнер.
            static::$containerBuilder = $this->initialize($fileName);

            // Исполнить PostLoadingPasses.
            $this->runPostLoadingPasses();

            return static::$containerBuilder;
        }

        // Создать директорию для компилированного контейнера.
        $this->createCacheDirectory();

        /** Путь к скомпилированному контейнеру. */
        $compiledContainerFile = $this->getPathCacheDirectory($this->filename) . self::COMPILED_CONTAINER_FILE;

        $containerConfigCache = new ConfigCache($compiledContainerFile, true);
        // Класс скомпилированного контейнера.
        $classCompiledContainerName = $this->getContainerClass() . md5($this->filename);

        if (!$containerConfigCache->isFresh()) {
            // Загрузить, инициализировать и скомпилировать контейнер.
            static::$containerBuilder = $this->initialize($fileName);

            // Блокировка на предмет конкурентных запросов.
            $lockFile = $this->getPathCacheDirectory($this->filename) . '/container.lock';

            // Silence E_WARNING to ignore "include" failures - don't use "@" to prevent silencing fatal errors
            $errorLevel = error_reporting(\E_ALL ^ \E_WARNING);

            $lock = false;
            try {
                if ($lock = fopen($lockFile, 'w')) {
                    flock($lock, \LOCK_EX | \LOCK_NB, $wouldBlock);
                    if (!flock($lock, $wouldBlock ? \LOCK_SH : \LOCK_EX)) {
                        fclose($lock);
                        @unlink($lockFile);
                        $lock = null;
                    }
                } else {
                    // Если в файл контейнера уже что-то пишется, то вернем свежую копию контейнера.
                    flock($lock, \LOCK_UN);
                    fclose($lock);
                    @unlink($lockFile);

                    // Исполнить PostLoadingPasses.
                    $this->runPostLoadingPasses();

                    return static::$containerBuilder;
                }
            } catch (\Throwable $e) {
            } finally {
                error_reporting($errorLevel);
            }

            $this->dumpContainer($containerConfigCache, static::$containerBuilder, $classCompiledContainerName);

            if ($lock) {
                flock($lock, \LOCK_UN);
                fclose($lock);
                @unlink($lockFile);
            }
        }

        // Подключение скомпилированного контейнера.
        require_once $compiledContainerFile;

        $classCompiledContainerName = '\\'.$classCompiledContainerName;
        static::$containerBuilder = new $classCompiledContainerName(); // @phpstan-ignore-line

        // Boot bundles.
        BundlesLoader::bootAfterCompilingContainer(static::$containerBuilder);

        // Исполнить PostLoadingPasses.
        $this->runPostLoadingPasses();

        return static::$containerBuilder;
    }

    /**
     * Dumps the service container to PHP code in the cache.
     *
     * @param ConfigCache      $cache     Кэш.
     * @param ContainerBuilder $container Контейнер.
     * @param string           $class     The name of the class to generate.
     *
     * @return void
     *
     * @since 20.03.2021 Форк оригинального метода с приближением к реальности.
     */
    private function dumpContainer(ConfigCache $cache, ContainerBuilder $container, string $class) : void
    {
        // Опция в конфиге - компилировать ли контейнер.
        if ($container->hasParameter('compile.container')
            &&
            !$container->getParameter('compile.container')) {
            return;
        }

        // Опция - дампить как файлы. По умолчанию - нет.
        $asFiles = false;
        if ($container->hasParameter('container.dumper.inline_factories')) {
            $asFiles = $container->getParameter('container.dumper.inline_factories');
        }

        $dumper = new PhpDumper(static::$containerBuilder);

        if (class_exists(\ProxyManager\Configuration::class) && class_exists(ProxyDumper::class)) {
            $dumper->setProxyDumper(new ProxyDumper());
        }

        $content = $dumper->dump(
            [
                'class' => $class,
                'file' => $cache->getPath(),
                'as_files' => $asFiles,
                'debug' => $this->debug,
                'build_time' => static::$containerBuilder->hasParameter('kernel.container_build_time')
                    ? static::$containerBuilder->getParameter('kernel.container_build_time') : time(),
                'preload_classes' => array_map('get_class', $this->bundles),
            ]
        );

        // Если as_files = true.
        if (is_array($content)) {
            $rootCode = array_pop($content);
            $dir = \dirname($cache->getPath()).'/';

            foreach ($content as $file => $code) {
                $this->filesystem->dumpFile($dir.$file, $code);
                @chmod($dir.$file, 0666 & ~umask());
            }

            $legacyFile = \dirname($dir.key($content)).'.legacy';
            if (is_file($legacyFile)) {
                @unlink($legacyFile);
            }

            $content = $rootCode;
        }

        $cache->write(
            $content, // @phpstan-ignore-line
            static::$containerBuilder->getResources()
        );
    }

    /**
     * Gets the container class.
     *
     * @return string The container class.
     * @throws InvalidArgumentException If the generated classname is invalid
     */
    private function getContainerClass() : string
    {
        $class = static::class;
        $class = false !== strpos($class, "@anonymous\0") ? get_parent_class($class).str_replace('.', '_', ContainerBuilder::hash($class))
                                                                  : $class;
        $class = str_replace('\\', '_', $class).ucfirst($this->environment).($this->debug ? 'Debug' : '').'Container';

        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new InvalidArgumentException(sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
        }

        return $class;
    }

    /**
     * Загрузить, инициализировать и скомпилировать контейнер.
     *
     * @param string $fileName Конфигурационный файл.
     *
     * @return ContainerBuilder
     *
     * @throws RuntimeException|LogicException|Exception Не инициализирован сервис kernel или пустой ParameterBag.
     *
     * @since 26.09.2020
     * @since 11.11.2020 Boot бандлов после загрузки контейнера.
     * @since 13.11.2020 Мета-данные бандлов. Обработка ошибки отсутствия сервиса kernel.
     */
    private function initialize(string $fileName): ContainerBuilder
    {
        try {
            $this->loadContainer($fileName);

            // Prepare bundles
            $this->updateBundlesMetaData();
            $this->bundlesLoader->registerExtensions(static::$containerBuilder);

            static::$containerBuilder->compile(true);

            // Boot bundles.
            $this->bundlesLoader->boot(static::$containerBuilder);
        } catch (Exception $e) {
            $this->errorHandler->die(
                $e->getMessage().'<br><br><pre>'.$e->getTraceAsString().'</pre>'
            );
            /**
             * Это исключение никогда не будет выброшено. Экран смерти выше его перебьет.
             * Нужно, чтобы не возвращать null или что-то подобное.
             */
            throw new RuntimeException(
                'Error initialize container.'
            );
        }

        // Контейнер в AppKernel, чтобы соответствовать Symfony.
        if (static::$containerBuilder->has('kernel')) {
            /**
             * @var AppKernel $kernelService Сервис kernel.
             */
            $kernelService = static::$containerBuilder->get('kernel');
            $kernelService->setContainer(static::$containerBuilder);
        }

        return static::$containerBuilder;
    }

    /**
     * Дополнить переменные приложения сведениями о зарегистрированных бандлах.
     *
     * @return void
     *
     * @throws LogicException  Не инициализирован сервис kernel или пустой ParameterBag.
     * @throws Exception
     *
     * @since 13.11.2020
     */
    private function updateBundlesMetaData() : void
    {
        if (!static::$containerBuilder->has('kernel')) {
            throw new LogicException(
                'Service kernel not initialized.'
            );
        }

        /**
         * @var AppKernel $kernelService
         */
        $kernelService = static::$containerBuilder->get('kernel');
        $parameterBag = static::$containerBuilder->getParameterBag();
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if ($parameterBag !== null) {
            // Дополнить переменные приложения сведениями о загруженных бандлах.
            $kernelService->registerStandaloneBundles();

            $parameterBag->add(
                $kernelService->getBundlesMetaData()
            );
        } else {
            throw new LogicException(
                'ParameterBag not initialized.'
            );
        }
    }

    /**
     * Параметры контейнера и регистрация сервиса kernel.
     *
     * @return void
     * @throws Exception Ошибки контейнера.
     *
     * @since 12.11.2020 Полная переработка. Регистрация сервиса.
     */
    private function setDefaultParamsContainer(): void
    {
        if (!static::$containerBuilder->hasDefinition('kernel')) {
            static::$containerBuilder->register('kernel', AppKernel::class)
                ->addTag('service.bootstrap')
                ->setAutoconfigured(true)
                ->setPublic(true)
                ->setArguments([$this->environment, $this->debug])
            ;
        }

        /** @var AppKernel $kernelService */
        $kernelService = static::$containerBuilder->get('kernel');

        static::$containerBuilder->getParameterBag()->add(
            /**
             * @psalm-suppress PossiblyNullReference
             */
            $kernelService->getKernelParameters() // @phpstan-ignore-line
        );
    }

    /**
     * Путь к директории с компилированным контейнером.
     *
     * @param string $filename Конфигурация.
     *
     * @return string
     *
     * @since 03.03.2021
     */
    private function getPathCacheDirectory(string $filename) : string
    {
        return $_SERVER['DOCUMENT_ROOT'] . self::COMPILED_CONTAINER_DIR .'/containers/'. md5($filename);
    }

    /**
     * Если надо создать директорию для компилированного контейнера.
     *
     * @return void
     */
    private function createCacheDirectory(): void
    {
        $dir = $this->getPathCacheDirectory($this->filename);

        if (!$this->filesystem->exists($dir)) {
            try {
                $this->filesystem->mkdir($dir);
            } catch (IOExceptionInterface $exception) {
                $this->errorHandler->die(
                    'An error occurred while creating your directory at ' . (string)$exception->getPath()
                );
            }
        }
    }

    /**
     * Загрузить контейнер.
     *
     * @param string $fileName Имя конфигурационного Yaml файла.
     *
     * @return boolean|ContainerBuilder
     * @throws Exception Ошибки контейнера.
     *
     * @since 11.09.2020 Подключение возможности обработки событий HtppKernel через Yaml конфиг.
     * @since 23.09.2020 Набор стандартных Compile Pass
     * @since 25.10.2020 Загрузка "автономных" бандлов.
     * @since 08.11.2020 Устранение ошибки, связанной с многократной загрузкой конфигурации бандлов.
     */
    private function loadContainer(string $fileName)
    {
        static::$containerBuilder = new ContainerBuilder();
        // Если изменился этот файл, то перестроить контейнер.
        static::$containerBuilder->addObjectResource($this);

        $this->setDefaultParamsContainer();

        static::$containerBuilder->setProxyInstantiator(new RuntimeInstantiator());

        // Инициализация автономных бандлов.
        $this->loadSymfonyBundles();

        // Набор стандартных Compile Pass
        $passes = new PassConfig();
        $allPasses = $passes->getPasses();
        foreach ($allPasses as $pass) {
            // Тонкость: MergeExtensionConfigurationPass добавляется в BundlesLoader.
            // Если не проигнорировать здесь, то он вызовется еще раз.
            if (get_class($pass) === MergeExtensionConfigurationPass::class) {
                continue;
            }
            static::$containerBuilder->addCompilerPass($pass);
        }

        $this->standartSymfonyPasses();

        // Локальные compile pass.
        foreach ($this->compilerPassesBag as $compilerPass) {
            /** @var CompilerPassInterface $passInitiated */
            $passInitiated = !empty($compilerPass['params']) ? new $compilerPass['pass'](...$compilerPass['params'])
                :
                new $compilerPass['pass'];

            // Фаза. По умолчанию PassConfig::TYPE_BEFORE_OPTIMIZATION
            // @phpstan-ignore-next-line
            $phase = !empty($compilerPass['phase']) ? $compilerPass['phase'] : PassConfig::TYPE_BEFORE_OPTIMIZATION;

            static::$containerBuilder->addCompilerPass($passInitiated, $phase);
        }

        // Подключение возможности обработки событий HtppKernel через Yaml конфиг.
        // tags:
        //      - { name: kernel.event_listener, event: kernel.request, method: handle }

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->setHotPathEvents([
            KernelEvents::REQUEST,
            KernelEvents::CONTROLLER,
            KernelEvents::CONTROLLER_ARGUMENTS,
            KernelEvents::RESPONSE,
            KernelEvents::FINISH_REQUEST,
        ]);

        static::$containerBuilder->addCompilerPass($registerListenersPass);

        // Загрузка основного конфига контейнера.
        if (!$this->loadContainerConfig($fileName, static::$containerBuilder)) {
            return false;
        }

        // Подгрузить конфигурации из папки config.
        $this->configureContainer(
            static::$containerBuilder,
            $this->getContainerLoader(static::$containerBuilder)
        );

        return static::$containerBuilder;
    }

    /**
     * Загрузка конфигурационного файла контейнера.
     *
     * @param string           $fileName         Конфигурационный файл.
     * @param ContainerBuilder $containerBuilder Контейнер.
     *
     * @return boolean
     * @throws Exception Когда не удалось прочитать конфиг.
     *
     * @since 20.03.2021
     */
    private function loadContainerConfig(string $fileName, ContainerBuilder $containerBuilder) : bool
    {
        $loader = $this->getContainerLoader($containerBuilder);

        try {
            $loader->load($_SERVER['DOCUMENT_ROOT'] . '/' . $fileName);
            $loader->load(__DIR__ . '/../config/base.yaml');

            return true;
        } catch (Exception $e) {
            $this->errorHandler->die('Сервис-контейнер: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Загрузка конфигураций в различных форматах из папки config.
     *
     * @param ContainerBuilder $container Контейнер.
     * @param LoaderInterface  $loader    Загрузчик.
     *
     * @return void
     * @throws Exception
     * @throws RuntimeException Когда директория с конфигами не существует.
     *
     * @since 06.11.2020
     */
    private function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $confDir = $_SERVER['DOCUMENT_ROOT'] . $this->configDir;

        if (!@file_exists($confDir)) {
            throw new RuntimeException(
                'Config directory ' . $confDir . ' not exist.'
            );
        }

        $container->setParameter('container.dumper.inline_class_loader', true);

        try {
            $loader->load($confDir.'/packages/*'.self::CONFIG_EXTS, 'glob');
        } catch (Exception $e) {
        }

        if (is_dir($confDir . '/packages/' . $this->environment)) {
            $loader->load($confDir . '/packages/' . $this->environment . '/**/*' .self::CONFIG_EXTS, 'glob');
        }

        $loader->load($confDir . '/services' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/services_'. $this->environment. self::CONFIG_EXTS, 'glob');
    }

    /**
     * Returns a loader for the container.
     *
     * @param ContainerBuilder $container
     *
     * @return DelegatingLoader The loader
     * @throws Exception
     *
     * @since 06.11.2020
     */
    private function getContainerLoader(ContainerBuilder $container): DelegatingLoader
    {
        /**
         * @var KernelInterface $kernelService Сервис kernel.
         */
        $kernelService = static::$containerBuilder->get('kernel');
        $locator = new \Symfony\Component\HttpKernel\Config\FileLocator(
            $kernelService
        );

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        return new DelegatingLoader($resolver);
    }

    /**
     * Стандартные Symfony манипуляции над контейнером.
     *
     * @return void
     *
     * @since 28.09.2020
     *
     * @see FrameworkBundle
     */
    private function standartSymfonyPasses(): void
    {
        /** @var array $autoConfigure Автоконфигурация тэгов. */
        $autoConfigure = [
            'controller.service_arguments' => AbstractController::class,
            'controller.argument_value_resolver' => ArgumentValueResolverInterface::class,
            'container.service_locator' => ServiceLocator::class,
            'kernel.event_subscriber' => EventSubscriberInterface::class,
            'validator.constraint_validator' => ConstraintValidatorInterface::class,
            'validator.initializer' => ObjectInitializerInterface::class,
        ];

        foreach ($autoConfigure as $tag => $class) {
            static::$containerBuilder->registerForAutoconfiguration($class)
                                     ->addTag($tag);
        }

        // Применяем compiler passes.
        foreach ($this->standartCompilerPasses as $pass) {
            if (!array_key_exists('pass', $pass) || !class_exists($pass['pass'])) {
                continue;
            }
            static::$containerBuilder->addCompilerPass(
                new $pass['pass'],
                $pass['phase'] ?? PassConfig::TYPE_BEFORE_OPTIMIZATION
            );
        }
    }

    /**
     * Загрузка "автономных" бандлов Symfony.
     *
     * @return void
     *
     * @throws InvalidArgumentException  Не найден класс бандла.
     *
     * @since 24.10.2020
     */
    private function loadSymfonyBundles() : void
    {
        $this->bundlesLoader = new BundlesLoader(
            static::$containerBuilder,
            $this->pathBundlesConfig
        );

        $this->bundlesLoader->load(); // Загрузить бандлы.
        $this->bundles = $this->bundlesLoader->bundles();
    }

    /**
     * Запустить PostLoadingPasses.
     *
     * @return void
     *
     * @since 26.09.2020
     * @since 21.03.2021 Маркер, что пасс уже запускался.
     */
    private function runPostLoadingPasses(): void
    {
        /**
         * Отсортировать по приоритету.
         *
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress InvalidScalarArgument
         */
        usort($this->postLoadingPassesBag, static function ($a, $b) : bool {
            // @phpstan-ignore-line
            return $a['priority'] > $b['priority'];
        });

        // Запуск.
        foreach ($this->postLoadingPassesBag as $key => $postLoadingPass) {
            if (class_exists($postLoadingPass['pass']) && !array_key_exists('runned', $postLoadingPass)) {
                $class = new $postLoadingPass['pass'];
                $class->action(static::$containerBuilder);

                // Отметить, что пасс уже запускался.
                $this->postLoadingPassesBag[$key]['runned'] = true;
            }
        }
    }

    /**
     * Статический фасад получение контейнера.
     *
     * @param string $method Метод. В данном случае instance().
     * @param mixed  $args   Аргументы (конфигурационный файл).
     *
     *
     * @return mixed | void
     * @throws Exception
     */
    public static function __callStatic(string $method, $args = null)
    {
        if ($method === 'instance') {
            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (static::$containerBuilder !== null) {
                return static::$containerBuilder;
            }

            $self = new self(...$args);

            try {
                return $self->container();
            } catch (Exception $e) {
                $self->errorHandler->die('Ошибка сервис-контейнера: '.$e->getMessage());
            }
        }

        if ($method === 'get') {
            /** @psalm-suppress PossiblyNullReference */
            if (static::$containerBuilder->has(...$args)) {
                return static::$containerBuilder->get(...$args);
            }

            // Попытка достать сервис из делегированных контейнеров.
            foreach (static::$delegatedContainers as $delegateContainer) {
                /** @psalm-suppress RedundantConditionGivenDocblockType */
                if ($delegateContainer && $delegateContainer->has(...$args)) {
                    return $delegateContainer->get(...$args);
                }
            }

            throw new Exception(
                'Service not exists.'
            );
        }

        if ($method === 'getParameter') {
            /** @psalm-suppress PossiblyNullReference */
            if (static::$containerBuilder->hasParameter(...$args)) {
                return static::$containerBuilder->getParameter(...$args);
            }

            throw new Exception(
                'Parameter not exists.'
            );
        }
    }
}
