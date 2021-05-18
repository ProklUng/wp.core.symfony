<?php

namespace Prokl\ServiceProvider\DelegateContainers;

use Illuminate\Container\Container;

/**
 * Class ExampleDelegateContainer
 * @package Prokl\ServiceProvider\DelegateContainers
 */
class ExampleDelegateContainer
{
    /**
     * @var Container
     */
    private $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
    }

    public function boot() : void
    {
    }

    public function register() : void
    {
        $this->container->singleton(SampleLaravelService::class, function ($app) {
            return new SampleLaravelService();
        });

    }
}
