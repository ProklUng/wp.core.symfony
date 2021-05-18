<?php

namespace Prokl\ServiceProvider\Tests\PostLoadingPasses;

use Prokl\ServiceProvider\PostLoadingPass\RegisterCustomPostType;
use Prokl\WordpressCi\Base\WordpressableTestCase;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class RegisterCustomPostTypeTest
 * @package Tests\ServiceProvider
 * @coversDefaultClass RegisterCustomPostType
 *
 * @since 27.09.2020
 * @since 28.09.2020 Доработки.
 */
class RegisterCustomPostTypeTest extends WordpressableTestCase
{
    /**
     * @var RegisterCustomPostType $obTestObject Тестируемый объект.
     */
    protected $obTestObject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obTestObject = new RegisterCustomPostType();
    }

    /**
    * action(). Нормальный ход событий.
    *
    * @return void
    */
    public function testAction() : void
    {
        $testContainerBuilder = $this->getTestContainer('custom.post.type.registrator');

        $result = $this->obTestObject->action(
            $testContainerBuilder
        );

        $this->assertTrue(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * action(). Несуществующий сервис.
     *
     * @return void
     */
    public function testActionNonExistService() : void
    {
        $result = $this->obTestObject->action(
            $this->getTestContainer('fake.service')
        );

        $this->assertFalse(
            $result,
            'Что-то пошло не так. Несуществующий сервис проскочил.'
        );
    }

    /**
     * Мок PostTypeRegistrator.
     *
     * @return mixed
     */
    private function getStubService()
    {
        return new class {
            public function registerPostType(): void
            {

            }
        };
    }

    /**
     * Тестовый контейнер.
     *
     * @param string $serviceId ID сервиса.
     *
     * @return ContainerBuilder
     */
    private function getTestContainer(string $serviceId) : ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container
            ->register($serviceId, get_class($this->getStubService()))
            ->setPublic(true);

        $this->process($container);

        return $container;
    }

    /**
     * @param ContainerBuilder $container Контейнер.
     */
    private function process(ContainerBuilder $container): void
    {
        (new RemoveUnusedDefinitionsPass())->process($container);
    }
}
