<?php

namespace Prokl\ServiceProvider\Tests;

use Exception;
use LogicException;
use Prokl\ServiceProvider\AppKernel;
use Prokl\ServiceProvider\ServiceProvider;
use Prokl\TestingTools\Tools\PHPUnitUtils;
use Prokl\WordpressCi\Base\WordpressableTestCase;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

/**
 * Class ServiceProviderTest
 * @package Prokl\ServiceProvider\Tests
 *
 * @since 02.06.2021
 */
class ServiceProviderTest extends WordpressableTestCase
{
    /**
     * @var ServiceProvider
     */
    protected $obTestObject;

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

        $this->obTestObject = new ServiceProvider('/Fixtures/config/test_container.yaml');

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

        $this->obTestObject = new ServiceProvider(
            '/Fixtures/config/test_container.yaml',
            'prod',
            false
        );

        $container = $this->obTestObject->container();

        $this->assertTrue($container->has('kernel'));
        $this->assertTrue($container->has('test_service'));

        // Передан ли в kernel скомпилированного контейнера экземпляр контейнера.
        $container = $container->get('kernel')->getContainer();
        $this->assertNotNull($container);

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

        $this->obTestObject = new ServiceProvider(
            '/Fixtures/config/test_container.yaml',
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
        $this->obTestObject = new ServiceProvider('/Fixtures/config/test_container.yaml');
        $container = $this->obTestObject->container();

        /** @var AppKernel $kernel */
        $kernel = $container->get('kernel');

        $this->obTestObject->shutdown();

        $reflection = new ReflectionProperty(ServiceProvider::class, 'containerBuilder');
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
        $this->obTestObject = new ServiceProvider('/Fixtures/config/test_container.yaml');

        $this->obTestObject->reboot();

        $reflection = new ReflectionProperty(ServiceProvider::class, 'containerBuilder');
        $reflection->setAccessible(true);
        $container = $reflection->getValue(null);

        /** @var AppKernel $kernel */
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
        $this->obTestObject = new ServiceProvider('/fake.yaml');
    }

    /**
     * getPathCacheDirectory().
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    public function testGetPathCacheDirectory() : void
    {
        $this->obTestObject = new ServiceProvider('/Fixtures/config/test_container.yaml');

        $filename = 'test';
        $result = PHPUnitUtils::callMethod(
            $this->obTestObject,
            'getPathCacheDirectory',
            [$filename]
        );

        $this->assertStringContainsString(
            'wp-content/cache/symfony-app/containers/',
            $result
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
