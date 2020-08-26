<?php

namespace Charcoal\DatabaseMigrator\Service;

// Local dependencies
use Charcoal\DatabaseMigrator\Exception\InvalidMigrationException;
// From 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;
use Exception;

/**
 * Locate the available migrations
 */
class MigrationFinder
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var FactoryInterface
     */
    protected $migrationFactory;

    /**
     * MigrationFinder constructor.
     *
     * @param string           $basePath         Application base path.
     * @param FactoryInterface $migrationFactory Migration factory.
     * @return void
     */
    public function __construct(string $basePath, FactoryInterface $migrationFactory)
    {
        $this->basePath         = $basePath;
        $this->migrationFactory = $migrationFactory;
    }

    /**
     *
     * Retrieve all available migrations.
     *
     * @param string|string[] $paths One or more migration paths.
     * @return object[] One or more migration instances.
     * @throws InvalidMigrationException When the patch cannot be instantiated.
     */
    public function findMigrations($paths): array
    {
        $files = $this->findFiles($paths);

        $pattern = implode('|', array_map('preg_quote', (array)$paths, ['!']));
        $pattern = '!^.*/('.$pattern.')/!';

        return array_map(function ($path) use ($pattern) {
            require_once $path;

            $path  = str_replace($this->getBasePath(), '', $path);
            $file  = preg_replace($pattern, '', $path);
            $class = str_replace(['/', '.php'], ['\\', ''], $file);

            try {
                $migration = $this->migrationFactory->create($class);

                return $migration->setPath($path);
            } catch (Exception $e) {
                throw new InvalidMigrationException($e->getMessage());
            }
        }, $files);
    }

    /**
     * Search the file system from the base path for any migration files among the given paths.
     *
     * @param string|string[] $paths One or more migration paths.
     * @return string[] One or more migration files.
     */
    public function findFiles($paths): array
    {
        $paths   = implode(',', (array)$paths);
        $pattern = $this->getBasePath().'/{vendor/locomotivemtl/*/,}{'.$paths.'}/Migration*.php';

        return $this->glob($pattern);
    }

    /**
     * Retrieve the base search path.
     *
     * @return string
     */
    protected function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Recursively find path names matching a pattern.
     *
     * @param string  $pattern The `glob()` pattern.
     * @param integer $flags   The `glob()` flags.
     * @param integer $depth   The maximum allowed depth.
     * @return string[] One or more matched files.
     */
    protected function glob(string $pattern, int $flags = 0, int $depth = 4): array
    {
        $files = glob($pattern, $flags);

        $level = 1;
        foreach (glob(dirname($pattern).'/*', (GLOB_ONLYDIR | GLOB_NOSORT | GLOB_BRACE)) as $dir) {
            $files = array_merge($files, $this->glob($dir.'/'.basename($pattern), $flags));

            $level++;
            if ($level >= $depth) {
                break;
            }
        }

        return $files;
    }
}
