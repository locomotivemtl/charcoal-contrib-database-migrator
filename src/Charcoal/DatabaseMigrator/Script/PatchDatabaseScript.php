<?php

namespace Charcoal\DatabaseMigrator\Script;

// From 'charcoal-app'
use Charcoal\App\Script\AbstractScript;
use Charcoal\App\Script\CronScriptInterface;
use Charcoal\App\Script\CronScriptTrait;

// From 'charcoal-config'
use Charcoal\Config\ConfigInterface;

// Local dependencies
use Charcoal\DatabaseMigrator\Service\Migrator;

// From 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;

// From pimple
use Pimple\Container;

use Exception;

// From Psr-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Patch Database Script
 */
class PatchDatabaseScript extends AbstractScript implements CronScriptInterface
{
    use CronScriptTrait;

    /**
     * @var Migrator
     */
    protected $migrator;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var FactoryInterface
     */
    protected $patchFactory;

    /**
     * @param Container $container A Pimple DI Container instance.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        $this->migrator     = $container['charcoal/database-migrator'];
        $this->patchFactory = $container['patch/factory'];
        $this->config       = $container['config'];
    }

    /**
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);

        $this->migrator->addPatches($this->searchPatches());

        $currentVersion = $this->migrator->checkDbVersion();
        $patches        = $this->migrator->availablePatches();

        $list      = [];
        $processed = [];

        foreach ($patches as $patch) {
            $list[] = [
                'Version'     => '<green>'.$patch::DB_VERSION.'</green>',
                'Description' => $patch['description'],
                'Author'      => $patch['author'],
            ];
        }

        $this->climate()->description('Database Migrator');
        $this->climate()->info('Migrating database from version ('.$currentVersion.')');

        // No patch to apply, exit,
        if (empty($patches)) {
            $this->climate()->info(
                'There\'s currently no available patch for the current Database version ('.$currentVersion.')'
            );
        } else {
            $input    = $this->climate()
                             ->out('There\'s <blue>'.count($patches).'</blue> patch(es) available.')
                             ->input('<green>(patch[p], interactive[i], list[l], abort[a], help[h])?</green>');
            $commands = [
                [
                    'ident'       => 'patch',
                    'short-code'  => 'p',
                    'description' => 'Process all the available patches',
                ], [
                    'ident'       => 'interactive',
                    'short-code'  => 'i',
                    'description' => 'Process patches one by one and control if each one should be applied',
                ], [
                    'ident'       => 'list',
                    'short-code'  => 'l',
                    'description' => 'List all available patches',
                ], [
                    'ident'       => 'abort',
                    'short-code'  => 'a',
                    'description' => 'Abort the database migration',
                ], [
                    'ident'       => 'help',
                    'short-code'  => 'h',
                    'description' => 'Display this help menu',
                ],
            ];

            $input->accept(array_merge(
                array_column($commands, 'ident'),
                array_column($commands, 'short-code')
            ));

            while (true) {
                switch ($input->prompt()) {
                    case 'patch':
                    case 'p':
                        break 2;
                    case 'interactive':
                    case 'i':
                        $this->setInteractive(true);
                        break 2;
                    case 'list':
                    case 'l':
                        $this->climate()->table($list);
                        break;
                    case 'abort':
                    case 'a':
                        return $response;
                    case 'help':
                    case 'h':
                        $this->climate()->table($commands);
                        break;
                }
            }
        }

        $commands = [
            [
                'ident'       => 'yes',
                'short-code'  => 'y',
                'description' => 'Process the patch',
            ], [
                'ident'       => 'skip',
                'short-code'  => 's',
                'description' => 'Skip this patch and proceed with the rest',
            ], [
                'ident'       => 'abort',
                'short-code'  => 'a',
                'description' => 'Abort the rest of the database migration',
            ], [
                'ident'       => 'help',
                'short-code'  => 'h',
                'description' => 'Display this help menu',
            ],
        ];

        $progress = $this->climate()->progress(count($patches) + 1);

        foreach ($patches as $patch) {
            $progress->advance(1, sprintf(
                'Processing patch : <blue>%s | %s | %s</blue>',
                $patch::DB_VERSION,
                $patch['description'],
                $patch['author']
            ));
            if ($this->interactive()) {
                $input = $this->climate()->blue()->input(
                    'Proceed ? '.
                    '<white>(yes[y], skip[s], abort[a], help[h])</white>'
                );

                $input->accept(array_merge(
                    array_column($commands, 'ident'),
                    array_column($commands, 'short-code')
                ));

                while (true) {
                    switch ($input->prompt()) {
                        case 'yes':
                        case 'y':
                            break 2;
                        case 'skip':
                        case 's':
                            // Skip one loop
                            $processed[] = [
                                'status'      => '<yellow>SKIPPED</yellow>',
                                'Version'     => $patch::DB_VERSION,
                                'Description' => $patch['description'],
                                'Author'      => $patch['author'],
                            ];
                            continue 3;
                        case 'abort':
                        case 'a':
                            // Maybe print a summary
                            break 3;
                        case 'help':
                        case 'h':
                            $this->climate()->table($commands);
                            break;
                    }
                }
            }

            $this->migrator->up([$patch::DB_VERSION]);
            $feedbacks = $this->migrator->feedback($patch::DB_VERSION);
            array_map([$this->climate(), 'info'], $feedbacks);

            $errors = $this->migrator->errors($patch::DB_VERSION);
            if (!empty($errors)) {
                // Prompt to continue or stop there
                array_map([$this->climate(), 'error'], $errors);

                $continue = $this->climate()
                                 ->error('An error occurred while processing the patch : '.$patch::DB_VERSION)
                                 ->confirm('Would you like to process the rest of the patches?');

                $processed[] = [
                    'status'      => '<red>ERROR</red>',
                    'Version'     => $patch::DB_VERSION,
                    'Description' => $patch['description'],
                    'Author'      => $patch['author'],
                ];
                if (!$continue->confirmed()) {
                    break;
                } else {
                    continue;
                }
            }

            $processed[] = [
                'status'      => '<green>PROCESSED</green>',
                'Version'     => $patch::DB_VERSION,
                'Description' => $patch['description'],
                'Author'      => $patch['author'],
            ];
        }

        $progress->advance(1, '<bold>Processing complete</bold>');

        if (!empty($processed)) {
            $this->climate()->bold()->info('Summary :')->table($processed);
        }

        return $response;
    }

    /**
     * Searches for Patch files located in project or vendors
     * given they are located in src/Charcoal/Patch/*
     *
     * @return array
     */
    private function searchPatches()
    {
        $base = $this->base();

        $glob = $this->globRecursive($base.'{vendor/locomotivemtl/*/,}src/Charcoal/Patch/Patch*.php');

        // Create patch models
        return array_map(function ($patch) {
            $patch = preg_replace('/.*\/Charcoal\/Patch\//', '', $patch);
            $patch = rtrim($patch, '.php');

            try {
                return $this->patchFactory->create($this->generateMetadataIdent($patch));
            } catch (Exception $e) {
                $this->climate()->error($e->getMessage());

                return [];
            }
        }, $glob);
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
     * Generate a metadata identifier from the subject class name (FQN).
     *
     * Converts the subject class name from camelCase to kebab-case.
     *
     * @param string $subject The subject string.
     * @return string
     */
    protected function generateMetadataIdent($subject)
    {
        $ident = preg_replace('/([a-z])([A-Z])/', '$1-$2', $subject);
        $ident = strtolower(str_replace('\\', '/', $ident));

        return $ident;
    }

    /**
     * BASE URL
     * Realpath
     * @return string
     */
    public function base()
    {
        return realpath($this->config->get('base_path')).'/';
    }

    /**
     * @return integer
     */
    public function maxRecursiveLevel()
    {
        return 4;
    }
}
