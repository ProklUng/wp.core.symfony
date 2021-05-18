<?php

namespace Prokl\ServiceProvider\Tests\PostLoadingPasses;

use Prokl\ServiceProvider\PostLoadingPass\TwigExtensionApply;
use Prokl\WordpressCi\Base\WordpressableTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class TwigExtensionApplyTest
 * @package Tests\ServiceProvider\PostLoadingPasses
 * @coversDefaultClass TwigExtensionApply
 *
 * @since 12.10.2020
 */
class TwigExtensionApplyTest extends WordpressableTestCase
{
    /**
     * @var TwigExtensionApply $obTestObject Тестируемый объект.
     */
    protected $obTestObject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obTestObject = new TwigExtensionApply();
    }

    /**
     * action(). Нормальный ход событий.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testActionNormal(): void
    {
        $testContainer = $this->getTestContainer('test.service', new class () {
        }, true);

        $result = $this->obTestObject->action($testContainer);

        $this->assertTrue(
            $result,
            'Процесс не прошел.'
        );
    }

    /**
     * action(). Нет сервиса.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testActionNoService(): void
    {
        $testContainer = $this->getTestContainer('test.service', new class () {
        }, false);

        $result = $this->obTestObject->action($testContainer);

        $this->assertFalse(
            $result,
            'Процесс не прошел.'
        );
    }

    /**
     * Тестовый контейнер.
     *
     * @param string $serviceId ID сервиса.
     * @param null $object
     * @param boolean $tagged
     *
     * @return ContainerBuilder
     */
    private function getTestContainer(
        string $serviceId,
        $object = null,
        bool $tagged = true
    ): ContainerBuilder {

        $container = new ContainerBuilder();

        if ($tagged) {
            $container
                ->register($serviceId, get_class($object))
                ->addTag('twig.extension')
                ->setPublic(true);

            $container->setParameter(
                '_twig_extension',
                [$serviceId => []]
            );
        } else {
            $container
                ->register($serviceId, get_class($object))
                ->setPublic(true);
        }

        return $container;
    }

}
