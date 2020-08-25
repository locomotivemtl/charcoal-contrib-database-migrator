<?php

namespace Charcoal\DatabaseMigrator\ServiceProvider;

// from 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;
use Charcoal\Factory\GenericFactory as Factory;
// local dependencies
use Charcoal\DatabaseMigrator\AbstractMigration;
use Charcoal\DatabaseMigrator\MigratorConfig;
use Charcoal\DatabaseMigrator\Service\Migrator;
use Charcoal\DatabaseMigrator\Service\MigrationFinder;
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

        $container['charcoal/database-migrator/migration/finder'] = function (Container $container) {
            return new MigrationFinder(
                $container['config']->get('base_path'),
                $container['charcoal/database-migrator/migration/factory']
            );
        };

        /**
         * @param Container $container A Pimple DI container.
         * @return FactoryInterface
         */
        $container['charcoal/database-migrator/migration/factory'] = function (Container $container) {
            return new Factory([
                'base_class'       => AbstractMigration::class,
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
