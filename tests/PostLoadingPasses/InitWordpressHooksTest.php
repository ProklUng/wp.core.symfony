<?php

namespace Prokl\ServiceProvider\Tests\PostLoadingPasses;

use Prokl\ServiceProvider\PostLoadingPass\Exceptions\RuntimePostLoadingPassException;
use Prokl\ServiceProvider\PostLoadingPass\InitWordpressHooks;
use Prokl\WordpressCi\Base\WordpressableTestCase;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class InitWordpressHooksTest
 * @package Tests\ServiceProvider\PostLoadingPasses
 * @coversDefaultClass InitWordpressHooks
 *
 * @since 27.09.2020
 */
class InitWordpressHooksTest extends WordpressableTestCase
{
    /**
     * @var InitWordpressHooks $obTestObject Тестируемый объект.
     */
    protected $obTestObject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obTestObject = new InitWordpressHooks();
    }

    /**
     * action(). Нормальный ход событий.
     *
     * @return void
     * @throws RuntimePostLoadingPassException
     */
    public function testAction(): void
    {
        $testContainerBuilder = $this->getTestContainer('init.wordpress.hooks');

        $result = $this->obTestObject->action($testContainerBuilder);

        $this->assertTrue(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * action(). Нет обработчиков. Пустой parameterBag.
     *
     * @return void
     * @throws RuntimePostLoadingPassException
     */
    public function testActionNoListener(): void
    {
        $container = $this->getTestContainer('fake.service', []);
        $container->setParameter('_events', null);

        $result = $this->obTestObject->action(
            $container
        );

        $this->assertFalse(
            $result,
            'Что-то пошло не так.'
        );
    }

    /**
     * action(). Пустые параметры.
     *
     * @return void
     * @throws RuntimePostLoadingPassException
     */
    public function testActionEmptyParams(): void
    {
        $result = $this->obTestObject->action(
            $this->getTestContainer('fake.service', [])
        );

        $this->assertFalse(
            $result,
            'Что-то пошло не так.'
        );

        $this->willSeeException(
            RuntimePostLoadingPassException::class,
            'InitEvents PostLoadingPass: params void.'
        );

        $this->obTestObject->action(
            $this->getTestContainer('fake.service', [[]])
        );

    }

    /**
     * action(). Неполные параметры.
     *
     * @return void
     * @throws RuntimePostLoadingPassException
     */
    public function testActionNonExistService(): void
    {
        $this->willSeeException(
            RuntimePostLoadingPassException::class,
            'InitEvents PostLoadingPass: name event apsent.'
        );

        $this->obTestObject->action(
            $this->getTestContainer('fake.service', [['method' => 'test']])
        );
    }

    /**
     * action(). Неполные параметры.
     *
     * @return void
     * @throws RuntimePostLoadingPassException
     */
    public function testActionExecuteMethod(): void
    {
        $class = $this->getStubService();

        $this->willSeeException(
            RuntimePostLoadingPassException::class,
            sprintf(
                'InitEvents PostLoadingPass: method %s of class listener %s not exist.',
                'test',
                get_class($class)
            )
        );

        $this->obTestObject->action(
            $this->getTestContainer('fake.service', [['event' => 'fake', 'method' => 'test']])
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
            public function addEvent(): void
            {

            }
        };
    }

    /**
     * Тестовый контейнер.
     *
     * @param string $serviceId ID сервиса.
     * @param array $params
     *
     * @return ContainerBuilder
     */
    private function getTestContainer(
        string $serviceId,
        array $params = [
            ['event' => 'test'],
        ]
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container
            ->register($serviceId, get_class($this->getStubService()))
            ->setPublic(true);

        $container->setParameter('_events', [
            $serviceId => $params,
        ]);

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
