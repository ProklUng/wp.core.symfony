<?php

namespace Prokl\ServiceProvider;

use Symfony\Component\Dotenv\Dotenv;

/**
 * Class LoadEnvironment
 * Хэлпер: загрузка окружения (dev или prod).
 * @package Prokl\ServiceProvider
 */
class LoadEnvironment
{
    /**
     * Загрузка конфигурации окружения.
     */
    public function load() : void
    {
        /** Путь к конфигурации окружения. .env.prod - продакшен. */
        $pathEnvFile = @file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env.prod')
            ? $_SERVER['DOCUMENT_ROOT'] . '/.env.prod'
            :
            $_SERVER['DOCUMENT_ROOT'] . '/.env';

        if (@file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env.local')) {
            $pathEnvFile = $_SERVER['DOCUMENT_ROOT'] . '/.env.local';
        }

        $dotenv = new Dotenv();

        $dotenv->loadEnv($pathEnvFile);
    }
}
