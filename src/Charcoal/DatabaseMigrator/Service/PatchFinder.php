<?php

namespace Charcoal\DatabaseMigrator\Service;

// From 'charcoal-config'
use Charcoal\Config\ConfigInterface;

// Local dependencies
use Charcoal\DatabaseMigrator\Exception\InvalidPatchException;

// From 'charcoal-factory'
use Charcoal\DatabaseMigrator\MigratorConfig;
use Charcoal\Factory\FactoryInterface;
use Exception;

/**
 * Locate the available patches
 */
class PatchFinder
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var FactoryInterface
     */
    protected $patchFactory;

    /**
     * @var ConfigInterface|MigratorConfig
     */
    protected $migratorConfig;

    /**
     * PatchFinder constructor.
     *
     * @param array $data Initialisation data.
     * @return void
     */
    public function __construct(array $data)
    {
        $this->config         = $data['config'];
        $this->patchFactory   = $data['patch/factory'];
        $this->migratorConfig = $data['migrator/config'];
    }

    /**
     * Searches for Patch files located in project or vendors
     * given they are located in src/Charcoal/Patch/*
     *
     * @param string|null $path The path where the patches are located.
     * @return array
     * @throws InvalidPatchException When the patch cannot be instantiated.
     */
    public function search($path = null)
    {
        $base = $this->base();

        $path = sprintf('/{vendor/locomotivemtl/*/,}{%s}/Patch*.php', ($path ?? $this->defaultPaths()));
        $glob = $this->globRecursive($base.$path);

        // Create patch models
        return array_map(function ($patch) {
            $patch = preg_replace('/.*\/Charcoal\/Patch\//', '', $patch);
            $patch = rtrim($patch, '.php');

            try {
                return $this->patchFactory->create($patch);
            } catch (Exception $e) {
                throw new InvalidPatchException($e->getMessage());
            }
        }, $glob);
    }

    /**
     * @return string
     */
    private function defaultPaths()
    {
        $paths = $this->migratorConfig->get('patches.paths');

        return implode(',', $paths);
    }

    /**
     * @param string  $pattern The pattern to search.
     * @param integer $flags   The glob flags.
     * @return array
     * @see http://in.php.net/manual/en/function.glob.php#106595
     */
    public function globRecursive($pattern, $flags = 0)
    {
        $max   = $this->maxRecursiveLevel();
        $i     = 1;
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', (GLOB_ONLYDIR | GLOB_NOSORT | GLOB_BRACE)) as $dir) {
            $files = array_merge($files, $this->globRecursive($dir.'/'.basename($pattern), $flags));
            $i++;
            if ($i >= $max) {
                break;
            }
        }

        return $files;
    }

    /**
     * BASE URL
     * Realpath
     * @return string
     */
    public function base()
    {
        return realpath($this->config->get('base_path'));
    }

    /**
     * @return integer
     */
    public function maxRecursiveLevel()
    {
        return 4;
    }
}
