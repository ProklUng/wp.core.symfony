<?php

namespace Prokl\ServiceProvider\Services\PostTypes\Interfaces;

/**
 * Interface PostTypeDataInterface
 * @package Prokl\ServiceProvider\Services\PostTypes\Interfaces
 *
 * @since 27.09.2020
 * @since 25.01.2021 Регистрация кастомных ACF полей.
 */
interface PostTypeDataInterface
{
    /**
     * Название нового типа поста.
     *
     * @return string
     */
    public function getNameTypePost() : string;

    /**
     * Массив для регистрации нового типа поста.
     *
     * @return array
     */
    public function getRegistrationData() : array;

    /**
     * Регистрация кастомных ACF полей.
     *
     * @return void
     */
    public function registerAcfFields() : void;
}
