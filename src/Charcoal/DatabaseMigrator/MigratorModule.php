<?php

namespace Charcoal\DatabaseMigrator;

// from 'charcoal-app'
use Charcoal\App\Module\AbstractModule;
// local dependencies
use Charcoal\DatabaseMigrator\ServiceProvider\MigratorServiceProvider;

/**
 * Migrator Module
 */
class MigratorModule extends AbstractModule
{
    public const ADMIN_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-database-migrator/config/admin.json';

    /**
     * Setup the module's dependencies.
     *
     * @return AbstractModule
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        $migratorServiceProvider = new MigratorServiceProvider();
        $container->register($migratorServiceProvider);

        return $this;
    }
}
