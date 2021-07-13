<?php

namespace Prokl\ServiceProvider\Tests;

use Exception;
use LogicException;
use Prokl\ServiceProvider\Micro\ExampleAppKernel;
use Prokl\ServiceProvider\Tests\Fixtures\MicroServiceProvider;
use Prokl\WordpressCi\Base\WordpressableTestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MicroServiceProviderTest
 * @package Prokl\ServiceProvider\Tests
 *
 * @since 11.07.2021
 */
class MicroServiceProviderTest extends WordpressableTestCase
{
    /**
     * @var MicroServiceProvider
     */
    protected $obTestObject;

    /**
     * @var string $config
     */
    private $config = '/Fixtures/config/test_micro_container.yaml';

    /**
     * @inheritDoc
     */
    protected function setUp() : void
    {
        parent::setUp();

        $_SERVER['DOCUMENT_ROOT'] = __DIR__;
        $this->rrmdir($_SERVER['DOCUMENT_ROOT'] . '/wp-content/cache/symfony-app');
        $_ENV['APP_DEBUG'] = true;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testLoad() : void
    {
        $_ENV['APP_DEBUG'] = true;

        $this->obTestObject = new MicroServiceProvider($this->config);

        $container = $this->obTestObject->container();

        $this->assertTrue($container->has('kernel'));
        $this->assertTrue($container->has('test_service'));
    }

    /**
     * Компилируется ли контейнер?
     *
     * @return void
     * @throws Exception
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoadProd() : void
    {
        $_ENV['APP_DEBUG'] = false;

        $this->obTestObject = new MicroServiceProvider(
            $this->config,
            'prod',
            false
        );

        $container = $this->obTestObject->container();

        $this->assertTrue($container->has('kernel'));
        $this->assertTrue($container->has('test_service'));

        $this->assertTrue(file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-content/cache/symfony-app/containers'));

        $this->rrmdir($_SERVER['DOCUMENT_ROOT'] . '/wp-content/cache/symfony-app');
    }

    /**
     * Грузятся ли бандлы?
     *
     * @return void
     * @throws Exception
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoadBundles() : void
    {
        $_ENV['APP_DEBUG'] = true;

        $this->obTestObject = new MicroServiceProvider(
            $this->config,
            'dev',
            true,
            '/Fixtures/bundles.php'
        );

        $container = $this->obTestObject->container();

        $this->assertTrue($container->has('kernel'));
        $this->assertTrue($container->has('test_service'));

        $bundles = $container->getParameter('kernel.bundles');

        $this->assertSame(
            ['TestingBundle' => 'Prokl\ServiceProvider\Tests\Fixtures\TestingBundle'],
            $bundles,
            'Бандл не загрузился.'
        );

        $bundlesMeta = $container->getParameter('kernel.bundles_metadata');
        $this->assertNotEmpty($bundlesMeta);
    }

    /**
     *
     * shutdown().
     *
     * @return void
     * @throws Exception
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testShutdown(): void
    {
        $this->obTestObject = new MicroServiceProvider($this->config);
        $container = $this->obTestObject->container();

        /** @var ExampleAppKernel $kernel */
        $kernel = $container->get('kernel');

        $this->obTestObject->shutdown();

        $reflection = new ReflectionProperty(MicroServiceProvider::class, 'containerBuilder');
        $reflection->setAccessible(true);
        $value = $reflection->getValue(null);

        $this->assertNull($value, 'Контейнер не обнулился');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot retrieve the container from a non-booted kernel.');

        $kernel->getContainer();
    }

    /**
     *
     * reboot().
     *
     * @return void
     * @throws Exception
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testReboot(): void
    {
        $this->obTestObject = new MicroServiceProvider($this->config);

        $this->obTestObject->reboot();

        $reflection = new ReflectionProperty(MicroServiceProvider::class, 'containerBuilder');
        $reflection->setAccessible(true);
        $container = $reflection->getValue(null);

        /** @var ExampleAppKernel $kernel */
        $kernel = $container->get('kernel');

        $this->assertNotNull($container, 'Контейнер обнулился');
        $this->assertNotNull($kernel->getContainer(), 'Контейнер в kernel обнулился');
    }

    /**
     * @return void
     * @throws Exception
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoadInvalidConfigFile() : void
    {
        $this->expectException(RuntimeException::class);
        $this->obTestObject = new MicroServiceProvider('/fake.yaml');
    }


    /**
     * Правильный ли класс установился в качестве сервиса kernel.
     *
     * @return void
     * @throws Exception
     */
    public function testSetAppKernelProperly() : void
    {
        $_ENV['APP_DEBUG'] = true;

        $this->obTestObject = new MicroServiceProvider($this->config);

        $container = $this->obTestObject->container();
        $kernel = $container->get('kernel');

        $this->assertInstanceOf(ExampleAppKernel::class, $kernel);
    }

    /**
     * Правильный ли контейнер в сервисе kernel.
     *
     * @return void
     * @throws Exception
     */
    public function testSetAppKernelContainerProperly() : void
    {
        $_ENV['APP_DEBUG'] = true;

        $this->obTestObject = new MicroServiceProvider($this->config);

        $container = $this->obTestObject->container();

        /** @var ContainerInterface $containerKernel */
        $containerKernel = $container->get('kernel')->getContainer();

        $this->assertSame(
            $containerKernel->getParameter('dummy'),
            'OK'
        );
    }

    /**
     * Рекурсивно удалить папку со всем файлами и папками.
     *
     * @param string $dir Директория.
     *
     * @return void
     */
    private function rrmdir(string $dir) : void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (filetype($dir. '/' .$object) === 'dir') {
                        $this->rrmdir($dir . '/' . $object);
                    } else {
                        unlink($dir. '/' . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
