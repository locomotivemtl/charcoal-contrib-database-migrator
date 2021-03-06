<?php

namespace Charcoal\DatabaseMigrator;

// from 'charcoal-app'
use Charcoal\App\Config\DatabaseConfig;
// Local dependencies
use Charcoal\DatabaseMigrator\Exception\IrreversibleMigrationException;
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
    public const PROCESSED_STATUS = 'processed';
    public const SKIPPED_STATUS = 'skipped';
    public const FAILED_STATUS = 'failed';
    public const NOT_NEEDED_STATUS = 'not needed';

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
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $status;

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

        $this->init();
    }

    /**
     * This method is stub.
     * @return void
     */
    protected function init(): void
    {
        // This method is a stub.
        // Reimplement in children class to process data that is needed in both 'up' and 'down' methods.
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
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path Path for AbstractMigration.
     * @return self
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string $status Status for AbstractMigration.
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
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
        // Reimplement in children class to inject dependencies from a Pimple container.
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
     * @throws IrreversibleMigrationException When a migration revert cannot be done.
     */
    public function down(): void
    {
        throw new IrreversibleMigrationException('Migration [%s] cannot be reverted');
    }

    /**
     * Short description of what the patch will do.
     *
     * @return string
     */
    abstract public function description(): string;
}
