<?php

namespace Prokl\ServiceProvider\PostLoadingPass;

use Exception;
use Prokl\ServiceProvider\Interfaces\PostLoadingPassInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;

/**
 * Class TwigExtensionApply
 *
 * Автозагрузка Twig Extensions.
 *
 * @package Prokl\ServiceProvider\TwigExtensionApply
 *
 * @since 11.10.2020
 * @since 27.10.2020 Доработка.
 */
class TwigExtensionApply implements PostLoadingPassInterface
{
    /** @const string VARIABLE_PARAM_BAG Переменная в ParameterBag. */
    private const VARIABLE_PARAM_BAG = '_twig_extension';

    /**
     * @inheritDoc
     */
    public function action(Container $containerBuilder) : bool
    {
        $result = false;

        try {
            $twigExtensionsServices = (array)$containerBuilder->getParameter(self::VARIABLE_PARAM_BAG);
        } catch (InvalidArgumentException $e) {
            return $result;
        }

        if (!$twigExtensionsServices) {
            return $result;
        }

        foreach ($twigExtensionsServices as $service => $value) {
            try {
                $extension = $containerBuilder->get($service);

                // Подвязывание Twig Extension.
                add_filter('timber/twig',
                    static function (Environment $twig) use ($extension) : Environment {
                        /** @var ExtensionInterface $extension */
                        try {
                            $twig->addExtension($extension);
                        } catch (LogicException $e) {
                        }

                        return $twig;
                });

                $result = true;
            } catch (Exception $e) {
                continue;
            }
        }

        return $result;
    }
}
