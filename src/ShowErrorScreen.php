<?php

namespace Prokl\ServiceProvider;

use WP_Error;

/**
 * Class showErrorScreen
 * @package Prokl\ServiceProvider
 *
 * @since 30.09.2020 Рефакторинг.
 */
class ShowErrorScreen
{
    /**
     * @var string $message Сообщение об ошибке.
     */
    private $message;

    /**
     * @var boolean $isPhpUnitRunning Запущено из под PHPUnit?
     */
    private $isPhpUnitRunning;

    /**
     * showErrorScreen constructor.
     */
    public function __construct()
    {
        $this->isPhpUnitRunning = defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__');
    }

    /**
     * Показать экран.
     * The site is experiencing technical difficulties.
     * Но со своим текстом.
     *
     * @param string $errorMessage Текст сообщения.
     *
     * @return bool|null
     */
    public function die(string $errorMessage = '') : ?bool
    {
        $errorMessage = $errorMessage ?: $this->message;

        // Запущено из PHPUnit.
        if ($this->isPhpUnitRunning) {
            echo $errorMessage; // Вывод в отладочных целях.

            return false;
        }

        $error = 500;
        $message = $errorMessage;
        $args = [
            'response' => 500,
            'exit'     => false
        ];

        /** @psalm-suppress TooManyArguments */
        $message = apply_filters('wp_php_error_message', $message, $error);
        /** @psalm-suppress TooManyArguments */
        $args = apply_filters('wp_php_error_args', $args, $error);

        $wp_error = new WP_Error(
            'internal_server_error',
            $message,
            [
                'error' => $error
            ]
        );

        wp_die($wp_error, '', $args);

        echo ob_get_clean(); // Буфер захватывается в Local\Services\Buffering.

        die();
    }
}
