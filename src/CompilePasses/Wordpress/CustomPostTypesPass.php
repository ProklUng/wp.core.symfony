<?php

namespace Prokl\ServiceProvider\CompilePasses\Wordpress;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CustomPostTypesPass
 * @package Prokl\ServiceProvider\CompilePasses\Wordpress
 *
 * @since 27.09.2020
 */
class CustomPostTypesPass implements CompilerPassInterface
{
    /** @const string TAG_POST_REGISTRATOR_DATA Сервисы - данные на новые виды постов. */
    private const TAG_POST_REGISTRATOR_DATA = 'post.type';
    /** @const string TAG_POST_REGISTRATOR Сервис - регистратор новых типов постов. */
    private const TAG_POST_REGISTRATOR = 'post.type.registrator';

    /**
     * @param ContainerBuilder $container Контейнер.
     *
     * @return void
     */
    public function process(ContainerBuilder $container): void
    {
        // Сервисы-данные новых типов постов.
        $taggedServices = $container->findTaggedServiceIds(
            self::TAG_POST_REGISTRATOR_DATA
        );

        // Регистратор новых типов постов.
        $registrator = $container->findTaggedServiceIds(
            self::TAG_POST_REGISTRATOR
        );

        if (!$taggedServices || !$registrator) {
            return;
        }

        $registratorService = array_key_first($registrator);
        $definition = $container->getDefinition($registratorService);

        // Загоняю аргументы в сервис регистратора.
        foreach ($taggedServices as $id => $values) {
            $definition->addArgument(new Reference($id));
        }
    }
}
