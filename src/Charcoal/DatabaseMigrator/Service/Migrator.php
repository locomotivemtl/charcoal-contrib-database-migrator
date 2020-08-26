<?php

namespace Charcoal\DatabaseMigrator\Service;

use Charcoal\DatabaseMigrator\AbstractMigration;
use PDO;
use PDOException;

/**
 * Migrator Service
 *
 * Handle relation between current database version and migrations to run
 * Also processes available migrations
 */
class Migrator
{
    private const DB_VERSION_TABLE_NAME = '_db_versions';
    private const DB_VERSION_COLUMN_NAME = 'version';
    private const SKIP_ACTION = 'skip';
    private const UP_ACTION = 'up';
    private const DOWN_ACTION = 'down';

    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $migrations = [];

    /**
     * @var array
     */
    protected $availableMigrations = [];

    /**
     * List of feedbacks
     *  - multidimensional
     *  [DB_VERSION => [...]]
     *
     * @var array
     */
    protected $feedback = [];

    /**
     * List of errors
     *  - multidimensional
     *  [DB_VERSION => [...]]
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Migrator constructor.
     *
     * @param PDO   $pdo        Database connector.
     * @param array $migrations All the patches found.
     */
    public function __construct(PDO $pdo, array $migrations = [])
    {
        $this->setPdo($pdo);
        $this->setMigrations($migrations);
    }

    /**
     * Apply migrations by passing the migration versions as an array
     * ex: [ '20191203160900', '20190309150000' ]
     *
     * @param array $versions A predefined list of migrations to process (optional).
     * @return void
     */
    public function up(array $versions = []): void
    {
        $availableMigrations = $this->availableMigrations();

        foreach ($availableMigrations as $migration) {
            if (in_array($migration->version(), $versions) || empty($versions)) {
                $migration->up();
                $this->addFeedback($migration->version(), $migration->getFeedbacks());
                $this->addErrors($migration->version(), $migration->getErrors());
                $this->updateDbVersionLog($migration, self::UP_ACTION);
            }
        }
    }

    /**
     * Revert migrations by passing the migration versions as an array
     * ex: [ '20191203160900', '20190309150000' ]
     *
     * @param array $versions A predefined list of migrations to process (optional).
     * @return void
     */
    public function down(array $versions = []): void
    {
        $availableMigrations = array_reverse($this->availableMigrations());

        foreach ($availableMigrations as $migration) {
            if (in_array($migration->version(), $versions) || empty($versions)) {
                $migration->down();
                $this->addFeedback($migration->version(), $migration->getFeedbacks());
                $this->addErrors($migration->version(), $migration->getErrors());
                $this->updateDbVersionLog($migration, self::DOWN_ACTION);
            }
        }
    }

    /**
     * @return AbstractMigration[]
     */
    public function availableMigrations(): array
    {
        if ($this->availableMigrations) {
            return $this->availableMigrations;
        }

        $dbV = $this->checkDbVersion();

        if ($dbV === 0) {
            return $this->getMigrations();
        }

        $this->availableMigrations = array_filter($this->getMigrations(), function ($migration) use ($dbV) {
            return $dbV < $migration->version();
        });

        return $this->availableMigrations;
    }

    /**
     * @return integer|string
     */
    public function checkDbVersion()
    {
        if (isset($this->dbVersion)) {
            return $this->dbVersion;
        }

        $q      = strtr('SHOW TABLES LIKE "%table"', ['%table' => self::DB_VERSION_TABLE_NAME]);
        $sth    = $this->getPdo()->query($q);
        $exists = $sth->fetchColumn(0);

        if (!$exists) {
            $this->getPdo()->query($this->tableSkeleton());
            $this->dbVersion = 0;

            return $this->dbVersion;
        }

        $q = strtr(
            'SELECT version FROM %table ORDER BY ts DESC LIMIT 1',
            ['%table' => self::DB_VERSION_TABLE_NAME]
        );

        $result = $this->getPdo()->query($q)->fetch();

        $this->dbVersion = ($result['version'] ?? 0);

        return $this->dbVersion;
    }

    /**
     * @return string
     */
    protected function tableSkeleton(): string
    {
        return strtr(
            '
            CREATE TABLE `%table` (
                id INT NOT NULL AUTO_INCREMENT,
                %column VARCHAR(14),
                ts DATETIME,
                action VARCHAR(255),
                path VARCHAR(255),
                PRIMARY KEY (id)
            );
            ',
            [
                '%table'  => self::DB_VERSION_TABLE_NAME,
                '%column' => self::DB_VERSION_COLUMN_NAME,
            ]
        );
    }

    /**
     * @param AbstractMigration $migration The migration.
     * @param string            $action    The action.
     * @return void
     */
    public function updateDbVersionLog(AbstractMigration $migration, string $action): void
    {
        $q = strtr(
            'INSERT INTO `%table` (`%column`, ts, action, path) VALUES (:version, NOW(), :action, :path)',
            [
                '%table'  => self::DB_VERSION_TABLE_NAME,
                '%column' => self::DB_VERSION_COLUMN_NAME,
            ]
        );

        $version = $migration->version();
        $path    = $migration->getPath();

        $sth = $this->getPdo()->prepare($q);
        $sth->bindParam(':version', $version, PDO::PARAM_STR);
        $sth->bindParam(':action', $action, PDO::PARAM_STR);
        $sth->bindParam(':path', $path, PDO::PARAM_STR);
        try {
            $sth->execute();
        } catch (PDOException $e) {
            $this->addErrors($version, [$e->getMessage()]);
        }
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param PDO $pdo PDO connector.
     * @return Migrator
     */
    public function setPdo(PDO $pdo): self
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * @return AbstractMigration[]
     */
    protected function getMigrations(): array
    {
        return $this->migrations;
    }

    /**
     * @param mixed $migrations List of all migrations.
     * @return Migrator
     */
    protected function setMigrations($migrations): self
    {
        // Order from oldest to newest
        usort($migrations, function (AbstractMigration $item1, AbstractMigration $item2) {
            return ($item1->version() <=> $item2->version());
        });
        $this->migrations = $migrations;

        return $this;
    }

    /**
     * @param array $migrations Migrations to add.
     * @return $this
     */
    public function addMigrations(array $migrations): self
    {
        return $this->setMigrations(array_merge($this->migrations, $migrations));
    }

    /**
     * @param string|null $version The migration ident.
     * @return array
     */
    public function getFeedback(string $version = null): array
    {
        return ($this->feedback[$version] ?? $this->feedback);
    }

    /**
     * @param array $feedback List of feedbacks.
     * @return Migrator
     */
    protected function setFeedback(array $feedback): self
    {
        $this->feedback = $feedback;

        return $this;
    }

    /**
     * @param string $version  Database version.
     * @param array  $feedback List of feedbacks.
     * @return $this
     */
    protected function addFeedback(string $version, array $feedback = []): self
    {
        if (!isset($this->feedback[$version])) {
            $this->feedback[$version] = [];
        }
        $this->feedback[$version] = array_merge($this->feedback[$version], $feedback);

        return $this;
    }

    /**
     * @param string|null $version The migration ident.
     * @return array
     */
    public function getErrors(string $version = null): array
    {
        return ($this->errors[$version] ?? $this->errors);
    }

    /**
     * @param array $errors List of errors.
     * @return Migrator
     */
    protected function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * @param string $version Database version.
     * @param array  $errors  List of errors.
     * @return $this
     */
    protected function addErrors(string $version, array $errors): self
    {
        if (!isset($this->errors[$version])) {
            $this->errors[$version] = [];
        }
        $this->errors[$version] = array_merge($this->errors[$version], $errors);

        return $this;
    }
}
