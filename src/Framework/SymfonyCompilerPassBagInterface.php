<?php

namespace Prokl\ServiceProvider\Framework;

/**
 * Interface SymfonyCompilerPassBagInterface
 * @package Prokl\ServiceProvider\Framework
 *
 * @since 05.04.2021
 */
interface SymfonyCompilerPassBagInterface
{
    /**
     * @param array $standartCompilerPasses Стандартные compiler passes.
     *
     * @return void
     */
    public function setStandartCompilerPasses(array $standartCompilerPasses): void;

    /**
     * @return array
     */
    public function getStandartCompilerPasses(): array;
}
