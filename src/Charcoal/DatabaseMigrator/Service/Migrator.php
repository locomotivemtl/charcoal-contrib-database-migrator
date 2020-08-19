<?php

namespace Charcoal\DatabaseMigrator\Service;

use PDO;

/**
 * Migrator Service
 *
 * Handle relation between current database version and patches to run
 * Also processes available patches
 */
class Migrator
{
    const DB_VERSION_TABLE_NAME = '_db_versions';
    const DB_VERSION_COLUMN_NAME = 'version';
    const UP_ACTION = 'up';
    const DOWN_ACTION = 'down';

    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $patches = [];

    /**
     * @var array
     */
    protected $availablePatches = [];

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
     * @param PDO   $pdo     Database connector.
     * @param array $patches All the patches found.
     */
    public function __construct(PDO $pdo, array $patches = [])
    {
        $this->setPdo($pdo);
        $this->setPatches($patches);
    }

    /**
     * Apply patches  by passing the patches versions as an array
     * ex: [ '20191203160900', '20190309150000' ]
     *
     * @param array $patches A predefined list of patches to process (optional).
     * @return void
     */
    public function up(array $patches = [])
    {
        $availablePatches = $this->availablePatches();

        foreach ($availablePatches as $patch) {
            if (in_array($patch::DB_VERSION, $patches) || empty($patches)) {
                $patch->up();
                $this->addFeedback($patch::DB_VERSION, $patch->getFeedback());
                $this->addErrors($patch::DB_VERSION, $patch->getErrors());
                $this->updateDbVersionLog($patch::DB_VERSION);
            }
        }
    }

    /**
     * Revert patches by passing the patches versions as an array
     * ex: [ '20191203160900', '20190309150000' ]
     *
     * @param array $patches A predefined list of patches to process (optional).
     * @return void
     */
    public function down(array $patches = [])
    {
        $availablePatches = $this->availablePatches();

        foreach ($availablePatches as $patch) {
            if (in_array($patch::DB_VERSION, $patches) || empty($patches)) {
                $patch->down();
                $this->addFeedback($patch::DB_VERSION, $patch->getFeedback());
                $this->addErrors($patch::DB_VERSION, $patch->getErrors());
                $this->updateDbVersionLog($patch::DB_VERSION);
            }
        }
    }

    /**
     * @return array|mixed
     */
    public function availablePatches()
    {
        if ($this->availablePatches) {
            return $this->availablePatches;
        }

        $dbV = $this->checkDbVersion();

        if ($dbV === 0) {
            return $this->patches();
        }

        $this->availablePatches = array_filter($this->patches(), function ($patch) use ($dbV) {
            return $dbV < $patch::DB_VERSION;
        });

        return $this->availablePatches;
    }

    /**
     * @return integer|string
     */
    protected function checkDbVersion()
    {
        $q      = strtr('SHOW TABLES LIKE "%table"', ['%table' => self::DB_VERSION_TABLE_NAME]);
        $sth    = $this->pdo()->query($q);
        $exists = $sth->fetchColumn(0);

        if (!$exists) {
            $this->pdo()->query($this->tableSkeleton());

            return 0;
        }

        $q = strtr(
            'SELECT version FROM %table ORDER BY ts DESC LIMIT 1',
            ['%table' => self::DB_VERSION_TABLE_NAME]
        );

        $result = $this->pdo()->query($q)->fetch();

        return $result['version'];
    }

    /**
     * @return string
     */
    protected function tableSkeleton()
    {
        return strtr(
            '
            CREATE TABLE `%table` (
                id INT NOT NULL,
                %column VARCHAR(10),
                ts DATETIME,
                action VARCHAR(255),
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
     * @param string $v      The database version.
     * @param string $action The action.
     * @return void
     */
    protected function updateDbVersionLog($v, $action = self::UP_ACTION): void
    {
        $q = strtr(
            'INSERT INTO %table (id, `%column`, ts, action) VALUES (\'\', :v, NOW(), :action)',
            [
                '%table'  => self::DB_VERSION_TABLE_NAME,
                '%column' => self::DB_VERSION_COLUMN_NAME,
            ]
        );

        $sth = $this->pdo()->prepare($q);
        $sth->bindParam(':v', $v, PDO::PARAM_STR);
        $sth->bindParam(':action', $action, PDO::PARAM_STR);
        $sth->execute();
    }

    /**
     * @return mixed
     */
    public function pdo()
    {
        return $this->pdo;
    }

    /**
     * @param PDO $pdo PDO connector.
     * @return Migrator
     */
    public function setPdo(PDO $pdo)
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function patches()
    {
        return $this->patches;
    }

    /**
     * @param mixed $patches List of all patches.
     * @return Migrator
     */
    protected function setPatches($patches)
    {
        // Order from oldest to newest
        usort($patches, function ($item1, $item2) {
            return ($item1::DB_VERSION <=> $item2::DB_VERSION);
        });
        $this->patches = $patches;

        return $this;
    }

    /**
     * @param array $patches Patches to add.
     * @return $this
     */
    public function addPatches(array $patches)
    {
        return $this->setPatches(array_merge($this->patches, $patches));
    }

    /**
     * @return array
     */
    public function feedback(): array
    {
        return $this->feedback;
    }

    /**
     * @param array $feedback List of feedbacks.
     * @return Migrator
     */
    protected function setFeedback(array $feedback)
    {
        $this->feedback = $feedback;

        return $this;
    }

    /**
     * @param string $v        Database version.
     * @param array  $feedback List of feedbacks.
     * @return $this
     */
    protected function addFeedback($v, array $feedback = [])
    {
        if (!isset($this->feedback[$v])) {
            $this->feedback[$v] = [];
        }
        $this->feedback[$v] = array_merge($this->feedback[$v], $feedback);

        return $this;
    }

    /**
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param array $errors List of errors.
     * @return Migrator
     */
    protected function setErrors(array $errors)
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * @param string $v      Database version.
     * @param array  $errors List of errors.
     * @return $this
     */
    protected function addErrors($v, array $errors = [])
    {
        if (!isset($this->errors[$v])) {
            $this->errors[$v] = [];
        }
        $this->errors[$v] = array_merge($this->errors[$v], $errors);

        return $this;
    }
}
