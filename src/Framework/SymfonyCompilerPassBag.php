<?php

namespace Prokl\ServiceProvider\Framework;

use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;
use Symfony\Component\Routing\DependencyInjection\RoutingResolverPass;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;

/**
 * Class SymfonyCompilerPassBag
 * @package Prokl\ServiceProvider\Framework
 *
 * @since 04.04.2021
 */
final class SymfonyCompilerPassBag extends AbstractSymfonyCompilerPassBag
{
    /**
     * @var array $standartCompilerPasses Пассы Symfony.
     */
    protected $standartCompilerPasses = [
        [
            'pass' => RoutingResolverPass::class,
        ],
        [
            'pass' => SerializerPass::class,
        ],
        [
            'pass' => PropertyInfoPass::class,
        ],
        [
            'pass' => AddConstraintValidatorsPass::class,
        ],
    ];
}
