<?php

namespace Charcoal\Migrator\ServiceProvider;

// from 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;
use Charcoal\Factory\GenericFactory as Factory;

// local dependencies
use Charcoal\Migrator\AbstractPatch;

// from 'pimple'
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class MigratorServiceProvider
 */
class MigratorServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance.
     * @return void
     */
    public function register(Container $container)
    {
        /**
         * @param Container $container A Pimple DI container.
         * @return FactoryInterface
         */
        $container['patch/factory'] = function (Container $container) {
            return new Factory([
                'base_class'       => AbstractPatch::class,
                'resolver_options' => [
                    'prefix' => '\\Charcoal\\Patch\\',
                ],
                'arguments'        => [
                    [
                        'container'       => $container,
                        'database'        => $container['database'],
                        'database/config' => $container['database/config'],
                    ],
                ],
            ]);
        };
    }
}