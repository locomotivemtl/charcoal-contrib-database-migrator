<?php

namespace Charcoal\Migrator;

// from 'charcoal-app'
use Charcoal\App\Module\AbstractModule;

// local dependencies
use Charcoal\Migrator\ServiceProvider\MigratorServiceProvider;

/**
 * Migrator Module
 */
class MigratorModule extends AbstractModule
{
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
