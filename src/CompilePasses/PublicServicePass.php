<?php

namespace Prokl\ServiceProvider\CompilePasses;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class PublicServicePass
 * Сделать приватные сервисы публичными.
 * @package Prokl\ServiceProvider\CompilePasses
 *
 * @since 01.12.2020
 */
final class PublicServicePass implements CompilerPassInterface
{
    /**
     * A regex to match the services that should be public.
     *
     * @var string
     */
    private $regex;

    /**
     * @param string $regex A regex to match the services that should be public.
     *
     * @internal Example:
     *
     * Make services public that have an idea that matches a regex
     * $this->addCompilerPass(new PublicServicePass('|my_bundle.*|'));
     */
    public function __construct(string $regex = '|.*|')
    {
        $this->regex = $regex;
    }

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) : void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (preg_match($this->regex, $id)) {
                $definition->setPublic(true);
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            if (preg_match($this->regex, $id)) {
                $alias->setPublic(true);
            }
        }
    }
}
