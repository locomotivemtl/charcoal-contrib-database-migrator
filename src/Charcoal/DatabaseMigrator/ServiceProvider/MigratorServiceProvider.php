<?php

namespace Charcoal\DatabaseMigrator\ServiceProvider;

// from 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;
use Charcoal\Factory\GenericFactory as Factory;

// local dependencies
use Charcoal\DatabaseMigrator\AbstractPatch;
use Charcoal\DatabaseMigrator\MigratorConfig;
use Charcoal\DatabaseMigrator\Service\Migrator;
use Charcoal\DatabaseMigrator\Service\PatchFinder;

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
         * @param Container $container
         * @return MigratorConfig
         */
        $container['charcoal/database-migrator/config'] = function (Container $container) {
            return new MigratorConfig($container['config']['database.migrator']);
        };

        /**
         * @param Container $container
         * @return Migrator
         */
        $container['charcoal/database-migrator/migrator'] = function (Container $container) {
            return new Migrator($container['database']);
        };

        $container['charcoal/database-migrator/patch/finder'] = function (Container $container) {
            return new PatchFinder([
                'config'          => $container['config'],
                'patch/factory'   => $container['charcoal/database-migrator/patch/factory'],
                'migrator/config' => $container['charcoal/database-migrator/config'],
            ]);
        };

        /**
         * @param Container $container A Pimple DI container.
         * @return FactoryInterface
         */
        $container['charcoal/database-migrator/patch/factory'] = function (Container $container) {
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
