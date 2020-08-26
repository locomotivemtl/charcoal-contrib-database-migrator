<?php

namespace Charcoal\DatabaseMigrator;

// from 'charcoal-app'
use Charcoal\App\Config\DatabaseConfig;
// from 'charcoal-core'
use Charcoal\Model\ModelInterface;
use Charcoal\Source\DatabaseSource;
// from PDO
use PDO;
// From pimple
use Pimple\Container;
// PHP
use Exception;

/**
 * Abstract Migration
 *
 * A migration must defines directives for a Database migration for these scenarios :
 *  - up : update to specified version;
 *  - down : revert from specified version;
 */
abstract class AbstractMigration
{
    /**
     * @var PDO $pdo
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $feedbacks = [];

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
        $this->setPdo($data['database']);
        $this->setDatabaseConfig($data['database/config']);

        // Optional dependencies injection via Pimple Container
        if (isset($data['container'])) {
            $this->setDependencies($data['container']);
        }
    }

    /**
     * @return string
     */
    public function version(): string
    {
        if (!isset($this->version)) {
            $this->version = preg_replace('/.*Migration/', '', static::class);
        }

        return $this->version;
    }

    /**
     * @return PDO
     */
    protected function getPdo(): PDO
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
    protected function getDatabaseConfig(): DatabaseConfig
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
    protected function addFeedback(string $feedback): self
    {
        $this->feedbacks[] = $feedback;

        return $this;
    }

    /**
     * @param string $error Error messages.
     * @return $this
     */
    protected function addError(string $error): self
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * @return array
     */
    public function getFeedbacks(): array
    {
        return $this->feedbacks;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A Pimple DI service container.
     * @return void
     */
    protected function setDependencies(Container $container): void
    {
        // This method is a stub.
        // Reimplement in children method to inject dependencies in your class from a Pimple container.
    }

    /**
     * Apply migration
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Revert migration
     *
     * @return void
     */
    abstract public function down(): void;

    /**
     * Short description of what the patch will do.
     *
     * @return string
     */
    abstract public function description(): string;
}
