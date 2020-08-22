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
     * @return void
     */
    public function up(): void
    {
    }

    /**
     * Revert migration
     *
     * @return void
     */
    public function down(): void
    {
    }

    /**
     * Short description of what the patch will do.
     *
     * @return string
     */
    public function description(): string
    {
        return 'This is an example patch that does nothing at all';
    }

    /**
     * The author of the patch.
     *
     * @return string
     */
    public function author(): string
    {
        return 'Joel Alphonso';
    }
}
