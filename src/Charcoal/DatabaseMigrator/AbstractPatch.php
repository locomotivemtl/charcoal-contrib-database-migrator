<?php

namespace Charcoal\DatabaseMigrator;

// from 'charcoal-app'
use Charcoal\App\Config\DatabaseConfig;

// from 'charcoal-core'
use Charcoal\Model\ModelInterface;
use Charcoal\Source\DatabaseSource;

use Exception;

// from PDO
use PDO;

// From pimple
use Pimple\Container;

/**
 * Abstract Patch
 *
 * A patch is a file namespaced under 'Charcoal\\Patch'
 * which defines directives for a Database migration for scenarios :
 *  - up : update to specified version;
 *  - down : revert from specified version;
 */
abstract class AbstractPatch
{
    /**
     * @var PDO $pdo
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $feedback = [];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var DatabaseConfig
     */
    protected $databaseConfig;

    /**
     * AbstractPatch constructor.
     *
     * @param array $data Dependencies.
     * @throws Exception When the DB_VERSION const is missing.
     */
    public function __construct(array $data)
    {
        if (!defined('static::DB_VERSION')) {
            throw new Exception(sprintf('The patch [%s] is missing the "DB_VERSION" const', static::class));
        }

        $this->setPdo($data['database']);
        $this->setDatabaseConfig($data['database/config']);

        // Optional dependencies injection via Pimple Container
        if (isset($data['container'])) {
            $this->setDependencies($data['container']);
        }
    }

    /**
     * @return PDO
     */
    protected function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param PDO $pdo The PDO instance.
     * @return self
     */
    protected function setPdo(PDO $pdo)
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * @return DatabaseConfig
     */
    protected function databaseConfig(): DatabaseConfig
    {
        return $this->databaseConfig;
    }

    /**
     * @param DatabaseConfig $databaseConfig Database configset.
     * @return self
     */
    protected function setDatabaseConfig(DatabaseConfig $databaseConfig)
    {
        $this->databaseConfig = $databaseConfig;

        return $this;
    }

    /**
     * @param ModelInterface $proto A targeted model for the alteration.
     * @return void
     */
    protected function createOrAlter(ModelInterface $proto)
    {
        /** @var DatabaseSource $source */
        $source = $proto->source();

        if (!$source->tableExists()) {
            $source->createTable();
        } else {
            $source->alterTable();
        }
    }

    /**
     * @param string $feedback Feedback messages.
     * @return $this
     */
    protected function addFeedback($feedback)
    {
        $this->feedback[] = $feedback;

        return $this;
    }

    /**
     * @param string $error Error messages.
     * @return $this
     */
    protected function addError($error)
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * @return array
     */
    public function getFeedback()
    {
        return $this->feedback;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A Pimple DI service container.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        // This method is a stub.
        // Reimplement in children method to inject dependencies in your class from a Pimple container.
    }

    /**
     * Apply migration
     *
     * @return mixed
     */
    abstract public function up();

    /**
     * Revert migration
     *
     * @return mixed
     */
    abstract public function down();
}