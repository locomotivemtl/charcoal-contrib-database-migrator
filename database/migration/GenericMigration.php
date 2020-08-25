<?php

use Charcoal\DatabaseMigrator\AbstractMigration;

/**
 * Generic Migration
 */
final class GenericMigration extends AbstractMigration
{
    public const DB_VERSION = '20200101';

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
        return 'This is an example migration that does nothing at all';
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
