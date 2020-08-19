<?php

namespace Charcoal\DatabaseMigrator\Script;

// From 'charcoal-app'
use Charcoal\App\Script\AbstractScript;
use Charcoal\App\Script\CronScriptInterface;
use Charcoal\App\Script\CronScriptTrait;

// Local dependencies
use Charcoal\DatabaseMigrator\Service\Migrator;

// From pimple
use Pimple\Container;

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
     * @param Container $container A Pimple DI Container instance.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        $this->migrator = $container['charcoal/database-migrator'];
    }

    /**
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);
        $currentVersion = $this->migrator->checkDbVersion();
        $patches        = $this->migrator->availablePatches();

        $list      = [];
        $processed = [];

        foreach ($patches as $patch) {
            $list[] = [
                'Version'     => $patch::DB_VERSION,
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
            $progress->advance(1, 'Processing patch : <blue><bold>'.$patch::DB_VERSION.'</bold> | test | author</blue>');
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
}
