<?php

namespace Prokl\ServiceProvider;

use Exception;
use RuntimeException;
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
     * @return boolean|null
     * @throws RuntimeException Ошибки под PHPUnit.
     */
    public function die(string $errorMessage = '') : ?bool
    {
        $errorMessage = $errorMessage ?: $this->message;

        // Запущено из PHPUnit.
        if ($this->isPhpUnitRunning) {
            throw new RuntimeException($errorMessage);
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

    /**
     * Обработчик wp_die для CLI режима.
     *
     * @param string | WP_Error $message The `wp_die()` message.
     *
     * @return void
     *
     * @throws Exception Exception containing the message.
     */
    public function wpDieCliHandler($message) : void
    {
        if (is_wp_error($message)) {
            $message = $message->get_error_message();
        }

        if (!is_scalar($message)) {
            $message = '0';
        }

        throw new Exception($message);
    }
}
