<?php

namespace Prokl\ServiceProvider\Tests;

use Exception;
use Prokl\ServiceProvider\AppKernel;
use Prokl\ServiceProvider\Bundles\BundlesLoader;
use Prokl\ServiceProvider\Tests\Fixtures\DummyService;
use Prokl\TestingTools\Base\BaseTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class ServiceProviderTest
 * @package Prokl\ServiceProvider\Tests
 *
 * @since 05.07.2021
 *
 */
class AppKernelTest extends BaseTestCase
{
    /**
     * @var AppKernel $obTestObject
     */
    protected $obTestObject;

    /**
     * Загрузка бандлов по кастомному пути.
     *
     * @return void
     */
    public function testLoadBundlesFromCustomPath() : void
    {
        $_ENV['APP_DEBUG'] = true;

        $container = new ContainerBuilder();

        $bundlesLoader = new BundlesLoader(
            $container,
            'dev',
            '/tests/Fixtures/bundles.php'
        );
        $bundlesLoader->load();

        $this->obTestObject = new AppKernel('dev', true);
        $result = $this->obTestObject->getBundlesMetaData();

        $this->assertNotEmpty($result['kernel.bundles']);
        $this->assertNotEmpty($result['kernel.bundles_metadata']);
    }

    /**
     * getContainer().
     *
     * @return void
     * @throws Exception
     */
    public function testGetContainer() : void
    {
        $container = $this->getTestContainer();

        $kernel = $container->get('kernel');
        $kernel->setContainer($container);

        $result = $kernel->getContainer()->get(DummyService::class);

        $this->assertInstanceOf(DummyService::class, $result);
    }

    /**
     * Тестовый локатор.
     *
     * @return ContainerInterface
     */
    private function getTestContainer()
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DummyService::class, new Definition(DummyService::class))->setPublic(true);
        $container->register('kernel', AppKernel::class)
                ->setAutoconfigured(true)
                ->setPublic(true)
                ->setArguments(['dev', true]);

        $container->compile();

        return $container;
    }
}
