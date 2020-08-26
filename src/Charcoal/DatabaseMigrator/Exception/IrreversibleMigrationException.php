<?php

namespace Charcoal\DatabaseMigrator\Exception;

use RuntimeException;

/**
 * Irreversible Migration Exception
 *
 * Thrown when a Migration cannot be reverted.
 */
class IrreversibleMigrationException extends RuntimeException
{
}
