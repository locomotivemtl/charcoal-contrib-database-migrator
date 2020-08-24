<?php

namespace Charcoal\DatabaseMigrator;

use Charcoal\Config\AbstractConfig;

/**
 * Migrator Config
 */
class MigratorConfig extends AbstractConfig
{
    /**
     * The default data is defined in a JSON file.
     *
     * @return array
     */
    public function defaults()
    {
        $baseDir = realpath(__DIR__.'/../../../');
        $confDir = $baseDir.'/config';

        return $this->loadFile($confDir.'/migrator.json');
    }
}
