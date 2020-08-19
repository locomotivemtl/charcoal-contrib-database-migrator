<?php

namespace Charcoal\DatabaseMigrator\ServiceProvider;

// from 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;
use Charcoal\Factory\GenericFactory as Factory;

// local dependencies
use Charcoal\DatabaseMigrator\AbstractPatch;
use Charcoal\DatabaseMigrator\Service\Migrator;

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
         * @return Migrator
         */
        $container['charcoal/database-migrator'] = function (Container $container) {
            return new Migrator($container['database'], $container['charcoal/database-migrator/patches']);
        };

        /**
         * Extend the migrator to add this package patches.
         */
        $container['charcoal/database-migrator'] = $container->extend(
            'charcoal/database-migrator',
            function (Migrator $migrator, Container $container) {
                $migrator->addPatches($container['charcoal/database-migrator/patches']);

                return $migrator;
            }
        );

        /**
         * @param Container $container
         * @return array
         */
        $container['charcoal/database-migrator/patches'] = function (Container $container) {
            return [
                $container['patch/factory']->create('database-migrator/generic-patch')
            ];
        };

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
