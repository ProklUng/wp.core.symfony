<?php

namespace Prokl\ServiceProvider;

use Prokl\ServiceProvider\CompilePasses\BaseAggregatedTaggedServicesPass;
use Prokl\ServiceProvider\CompilePasses\CustomEventsPass;
use Prokl\ServiceProvider\CompilePasses\TwigExtensionTaggedServicesPass;
use Prokl\ServiceProvider\CompilePasses\ValidateServiceDefinitions;
use Prokl\ServiceProvider\CompilePasses\ContainerAwareCompilerPass;
use Prokl\ServiceProvider\CompilePasses\Wordpress\CustomPostTypesPass;
use Prokl\ServiceProvider\PostLoadingPass\BootstrapServices;
use Prokl\ServiceProvider\PostLoadingPass\TwigExtensionApply;
use Prokl\ServiceProvider\PostLoadingPass\RegisterCustomPostType;
use Prokl\ServiceProvider\PostLoadingPass\InitWordpressHooks;
use Prokl\ServiceProvider\PostLoadingPass\InitWordpressHooksViaTrait;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * Class CustomCompilePassBag
 * @package Prokl\ServiceProvider
 *
 * @since 26.09.2020
 * @since 27.09.2020 Новые compiler pass & post loading pass.
 * @since 11.10.2020 Новый compiler pass & post loading pass Twig extensions.
 * @since 25.11.2020 Новый compiler pass CustomEventsInit (события Symfony).
 * @since 20.12.2020 Новый compiler pass AddConsoleCommands.
 */
class CustomCompilePassBag
{
    /**
     * @var array $compilePassesBag Набор Compiler Passes.
     */
    private $compilePassesBag = [
        // Автозагрузка сервисов.
        [
            'pass' => BaseAggregatedTaggedServicesPass::class,
            'params' => [
                'service.bootstrap',
                '_bootstrap'
            ]
        ],
        // Инициализация событий через сервисные тэги.
        [
            'pass' => BaseAggregatedTaggedServicesPass::class,
            'params' =>
                ['custom.events.init', '_events'],
        ],
        // Инициализация событий через трэйты.
        [
            'pass' => BaseAggregatedTaggedServicesPass::class,
            'params' =>
                ['custom.events.init.trait', '_events_trait'],
        ],

        // Проверка классов сервисов на существование.
        [
            'pass' => ValidateServiceDefinitions::class,
            'phase' => PassConfig::TYPE_BEFORE_REMOVING
        ],

        // Автоматическая инжекция контейнера в сервисы, имплементирующие ContainerAwareInterface.
        [
            'pass' => ContainerAwareCompilerPass::class
        ],

        // Регистрация новых типов постов.
        [
            'pass' => CustomPostTypesPass::class
        ],

        // Регистрация Twig extensions.
        [
            'pass' => TwigExtensionTaggedServicesPass::class
        ],
        // Кастомные события Symfony.
        [
            'pass' => CustomEventsPass::class
        ],
        // Подключение консольных команд.
        [
            'pass' => AddConsoleCommandPass::class,
            'phase' => PassConfig::TYPE_BEFORE_REMOVING
        ],
    ];

    /**
     * @var array $postLoadingPassesBag Пост-обработчики (PostLoadingPass) контейнера.
     */
    private $postLoadingPassesBag = [
        ['pass' => InitWordpressHooks::class, 'priority' => 10],
        ['pass' => InitWordpressHooksViaTrait::class, 'priority' => 10],
        ['pass' => BootstrapServices::class, 'priority' => 20],
        ['pass' => RegisterCustomPostType::class, 'priority' => 20],
        ['pass' => TwigExtensionApply::class, 'priority' => 20],
    ];

    /**
     * Compiler Passes.
     *
     * @return array|array[]
     */
    public function getCompilerPassesBag() : array
    {
        return $this->compilePassesBag;
    }

    /**
     * PostLoadingPasses.
     *
     * @return array[]|string[]
     */
    public function getPostLoadingPassesBag() : array
    {
        return $this->postLoadingPassesBag;
    }
}
