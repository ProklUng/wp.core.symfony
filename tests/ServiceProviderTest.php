<?php

namespace Prokl\ServiceProvider\Tests;

use Prokl\ServiceProvider\ServiceProvider;
use Prokl\WordpressCi\Base\WordpressableTestCase;

/**
 * Class ServiceProviderTest
 * @package Prokl\ServiceProvider\Tests
 *
 * @since 02.06.2021
 */
class ServiceProviderTest extends WordpressableTestCase
{
    /**
     * @var
     */
    protected $obTestObject;

    /**
     * @var ServiceProvider
     */
    private $provider;

    /**
     * @inheritDoc
     */
    protected function setUp() : void
    {
        parent::setUp();

        $_SERVER['DOCUMENT_ROOT'] = __DIR__;

        $_ENV['APP_DEBUG'] = true;

        $this->provider = new ServiceProvider(
            '/Fixtures/config/test_container.yaml'
        );
    }

    /**
     * @return void
     */
    public function testLoad() : void
    {

    }
}
