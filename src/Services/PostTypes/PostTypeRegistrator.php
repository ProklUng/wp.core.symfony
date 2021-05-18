<?php

namespace Prokl\ServiceProvider\Services\PostTypes;

use Prokl\ServiceProvider\Services\PostTypes\Interfaces\PostTypeDataInterface;

/**
 * Class PostTypeRegistrator
 * @package Prokl\ServiceProvider\Services\PostTypes
 *
 * @since 27.09.2020
 * @since 25.01.2021 Регистрация кастомных ACF полей.
 */
class PostTypeRegistrator
{
    /**
     * @var PostTypeDataInterface[] $postTypesRegistratorBag Данные на кастомные типы постов.
     */
    private $postTypesRegistratorBag;

    /**
     * PostTypeRegistrator constructor.
     *
     * @param PostTypeDataInterface ...$postTypeData
     */
    public function __construct(
        PostTypeDataInterface ...$postTypeData
    ) {
        $this->postTypesRegistratorBag = $postTypeData;
    }

    /**
     * Регистрация кастомных типов постов.
     *
     * @return void
     */
    public function registerPostType() : void
    {
        foreach ($this->postTypesRegistratorBag as $postTypeData) {
            register_post_type($postTypeData->getNameTypePost(), $postTypeData->getRegistrationData());
            $postTypeData->registerAcfFields();
        }
    }
}
