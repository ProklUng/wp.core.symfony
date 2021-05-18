<?php

namespace Prokl\ServiceProvider\Tests\PostLoadingPasses;

use Prokl\ServiceProvider\PostLoadingPass\InitWordpressHooksViaTrait;
use Prokl\ServiceProvider\Traits\Eventable;
use Prokl\WordpressCi\Base\WordpressableTestCase;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class InitWordpressHooksViaTraitTest
 * @package Tests\ServiceProvider\PostLoadingPasses
 * @coversDefaultClass InitWordpressHooksViaTrait
 *
 * @since 27.09.2020
 * @since 30.09.2020 Исправление ошибок.
 */
class InitWordpressHooksViaTraitTest extends WordpressableTestCase
{
    /**
     * @var InitWordpressHooksViaTrait $obTestObject Тестируемый объект.
     */
    protected $obTestObject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obTestObject = new InitWordpressHooksViaTrait();
    }

    /**
     * action(). Нормальный ход событий.
     *
     * @return void
     */
    public function testAction(): void
    {
        $testContainerBuilder = $this->getTestContainer('custom.events.init.trait');

        $result = $this->obTestObject->action(
            $testContainerBuilder
        );

        $this->assertTrue(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * action(). Пустые параметры.
     *
     * @return void
     */
    public function testActionEmptyParams(): void
    {
        $result = $this->obTestObject->action(
            $this->getTestContainer('fake.service', $this->getStubService(), [])
        );

        $this->assertFalse(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * action(). Без трэйта.
     *
     * @return void
     */
    public function testActionServiceWithoutTrait(): void
    {
        $result = $this->obTestObject->action(
            $this->getTestContainer('fake.service',
                $this->getStubServiceWithoutTrait(),
                [['method' => 'test']]
            )
        );

        $this->assertFalse(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * action(). С трэйтом, но без метода.
     *
     * @return void
     */
    public function testActionServiceTraitWithoutMethod(): void
    {
        $result = $this->obTestObject->action(
            $this->getTestContainer('fake.service',
                $this->getStubServiceTraitNoMethod(),
                [['method' => 'test']]
            )
        );

        // Вызывается пустой метод трэйта.
        $this->assertTrue(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * Мок обработчика.
     *
     * @return mixed
     */
    private function getStubService()
    {
        return new class {
            use Eventable;

            public function addEvent(): void
            {

            }
        };
    }

    /**
     * Мок обработчика. Без трэйта.
     *
     * @return mixed
     */
    private function getStubServiceWithoutTrait()
    {
        return new class {
            public function addEvent(): void
            {

            }
        };
    }

    /**
     * Мок обработчика. С трэйтом, но без метода.
     *
     * @return mixed
     */
    private function getStubServiceTraitNoMethod()
    {
        return new class {
            use Eventable;
        };
    }

    /**
     * Тестовый контейнер.
     *
     * @param string $serviceId ID сервиса.
     * @param null $object Объект-обработчик.
     * @param array $params Параметры.
     *
     * @return ContainerBuilder
     */
    private function getTestContainer(
        string $serviceId,
        $object = null,
        array $params = [
            ['event' => 'test'],
        ]
    ): ContainerBuilder {
        if ($object === null) {
            $object = $this->getStubService();
        }

        $container = new ContainerBuilder();
        $container
            ->register($serviceId, get_class($object))
            ->setPublic(true);

        if (!empty($params)) {
            $container->setParameter('_events_trait', [
                $serviceId => $params,
            ]);
        }

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
