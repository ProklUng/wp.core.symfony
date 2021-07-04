<?php

namespace Prokl\ServiceProvider\Tests;

use Prokl\ServiceProvider\LoadEnvironment;
use Prokl\TestingTools\Base\BaseTestCase;

/**
 * Class LoadEnvironmentTest
 * @package Prokl\ServiceProvider\Tests
 *
 * @since 02.06.2021
 */
class LoadEnvironmentTest extends BaseTestCase
{
    /**
     * @var LoadEnvironment $obTestObject
     */
    protected $obTestObject;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env.prod');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env.local');

        parent::setUp();

        $_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/Fixtures/env';
        $this->obTestObject = new LoadEnvironment($_SERVER['DOCUMENT_ROOT']);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_ENV);
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);
    }

    /**
     * @return void
     */
    public function testProcess() : void
    {
        $_ENV['APP_DEBUG'] = true;
        $_ENV['APP_ENV'] = 'test';

        $this->obTestObject->process();

        $this->assertTrue((bool)$_SERVER['APP_DEBUG']);
        $this->assertSame('test', $_SERVER['APP_ENV']);
    }

    /**
     * load(). Production.
     *
     * @return void
     */
    public function testLoadProd() : void
    {
        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/.env',
            'TEST=dev'
        );

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/.env.prod',
            'TEST=prod'
        );

        $this->obTestObject->load();

        $this->assertSame($_ENV['TEST'], 'prod');

        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env.prod');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env');
    }

    /**
     * load(). Local.
     *
     * @return void
     */
    public function testLoadLocal() : void
    {
        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/.env',
            'TEST=dev'
        );

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/.env.prod',
            'TEST=prod'
        );

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/.env.local',
            'TEST=local'
        );

        $this->obTestObject->load();

        $this->assertSame($_ENV['TEST'], 'local');

        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env.prod');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env.local');
    }

    /**
     * load(). .env.
     *
     * @return void
     */
    public function testLoadEnv() : void
    {
        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/.env',
            'TEST=dev'
        );

        $this->obTestObject->load();

        $this->assertSame($_ENV['TEST'], 'dev');

        @unlink($_SERVER['DOCUMENT_ROOT'] . '/.env');
    }
}
