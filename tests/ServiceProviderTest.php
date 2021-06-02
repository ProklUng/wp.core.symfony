<?php

namespace Prokl\ServiceProvider\Tests;

use Exception;
use Prokl\ServiceProvider\ServiceProvider;
use Prokl\WordpressCi\Base\WordpressableTestCase;
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
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testLoad() : void
    {
        $_ENV['APP_DEBUG'] = true;

        $this->obTestObject = new ServiceProvider(
            '/Fixtures/config/test_container.yaml'
        );

        $container = $this->obTestObject->container();

        $this->assertTrue($container->has('kernel'));
        $this->assertTrue($container->has('test_service'));
    }

    /**
     * Компилируется ли контейнер?
     *
     * @return void
     * @throws Exception
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
        $this->assertTrue(file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-content/cache/symfony-app/containers'));

        $this->rrmdir($_SERVER['DOCUMENT_ROOT'] . '/wp-content/cache/symfony-app');
    }

    /**
     * Грузятся ли бандлы?
     *
     * @return void
     * @throws Exception
     */
    public function testLoadBundles() : void
    {
        $_ENV['APP_DEBUG'] = false;

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
     * @return void
     * @throws Exception
     */
    public function testLoadInvalidConfigFile() : void
    {
        $this->expectException(RuntimeException::class);
        $this->obTestObject = new ServiceProvider(
            '/fake.yaml'
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
