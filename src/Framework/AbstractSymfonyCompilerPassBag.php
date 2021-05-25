<?php

namespace Prokl\ServiceProvider\Framework;

/**
 * Class AbstractSymfonyCompilerPassBag
 * @package Prokl\ServiceProvider\Framework
 *
 * @since 05.04.2021
 */
class AbstractSymfonyCompilerPassBag implements SymfonyCompilerPassBagInterface
{
    /**
     * @var array $standartCompilerPasses Пассы Symfony.
     */
    protected $standartCompilerPasses = [];

    /**
     * @inheritDoc
     */
    public function setStandartCompilerPasses(array $standartCompilerPasses) : void
    {
        $this->standartCompilerPasses = $standartCompilerPasses;
    }

    /**
     * @inheritDoc
     */
    public function getStandartCompilerPasses(): array
    {
        return $this->standartCompilerPasses;
    }
}
