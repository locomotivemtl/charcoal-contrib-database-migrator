<?php

namespace Charcoal\Patch\DatabaseMigrator;

use Charcoal\DatabaseMigrator\AbstractPatch;

/**
 * Generic Patch
 */
final class GenericPatch extends AbstractPatch
{
    const DB_VERSION = '20200101';

    /**
     * Apply migration
     *
     * @return mixed
     */
    public function up()
    {

    }

    /**
     * Revert migration
     *
     * @return mixed
     */
    public function down()
    {

    }
}
