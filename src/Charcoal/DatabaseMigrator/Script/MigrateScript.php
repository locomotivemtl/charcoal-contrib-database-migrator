<?php

namespace Charcoal\DatabaseMigrator\Script;

// From 'charcoal-app'
use Charcoal\App\Script\AbstractScript;
use Charcoal\App\Script\CronScriptInterface;
use Charcoal\App\Script\CronScriptTrait;
// Local dependencies
use Charcoal\DatabaseMigrator\Exception\InvalidMigrationException;
use Charcoal\DatabaseMigrator\MigratorConfig;
use Charcoal\DatabaseMigrator\Service\Migrator;
use Charcoal\DatabaseMigrator\Service\MigrationFinder;
// From pimple
use Pimple\Container;
use Exception;
// From Psr-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Migrate Database Script
 */
class MigrateScript extends AbstractScript implements CronScriptInterface
{
    use CronScriptTrait;

    /**
     * @var Migrator
     */
    protected $migrator;

    /**
     * @var MigratorConfig
     */
    protected $migratorConfig;

    /**
     * @var MigrationFinder
     */
    protected $migrationFinder;

    /**
     * @param Container $container A Pimple DI Container instance.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        $this->migrator        = $container['charcoal/database-migrator/migrator'];
        $this->migratorConfig  = $container['charcoal/database-migrator/config'];
        $this->migrationFinder = $container['charcoal/database-migrator/migration/finder'];
    }

    /**
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);

        try {
            $migrations = $this->migrationFinder->findMigrations($this->getMigrationPaths());
        } catch (InvalidMigrationException $e) {
            $this->climate()->error($e->getMessage());

            return $response;
        }

        $this->migrator->addMigrations($migrations);

        $currentVersion = $this->migrator->checkDbVersion();
        $migrations        = $this->migrator->availableMigrations();

        // No migration to apply, exit,
        if (empty($migrations)) {
            $this->climate()->info(
                'There\'s currently no available migration for the current Database version ('.$currentVersion.')'
            );

            return $response;
        }

        $list      = [];
        $processed = [];

        foreach ($migrations as $migration) {
            $list[] = [
                'Version'     => '<green>'.$migration::DB_VERSION.'</green>',
                'Description' => $migration['description'],
                'Author'      => $migration['author'],
            ];
        }

        $this->climate()->description('Database Migrator');
        $this->climate()->info('Migrating database from version ('.$currentVersion.')');

        $input    = $this->climate()
                         ->out('There\'s <blue>'.count($migrations).'</blue> migration(s) available.')
                         ->input('<green>(migrate[m], interactive[i], list[l], abort[a], help[h])?</green>');
        $commands = [
            [
                'ident'       => 'migrate',
                'short-code'  => 'p',
                'description' => 'Process all the available migrations',
            ],
            [
                'ident'       => 'interactive',
                'short-code'  => 'i',
                'description' => 'Process migrations one by one and control if each one should be applied',
            ],
            [
                'ident'       => 'list',
                'short-code'  => 'l',
                'description' => 'List all available migrations',
            ],
            [
                'ident'       => 'abort',
                'short-code'  => 'a',
                'description' => 'Abort the database migration',
            ],
            [
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
                case 'migration':
                case 'm':
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

        $commands = [
            [
                'ident'       => 'yes',
                'short-code'  => 'y',
                'description' => 'Process the migration',
            ],
            [
                'ident'       => 'skip',
                'short-code'  => 's',
                'description' => 'Skip this migration and proceed with the rest',
            ],
            [
                'ident'       => 'abort',
                'short-code'  => 'a',
                'description' => 'Abort the rest of the database migration',
            ],
            [
                'ident'       => 'help',
                'short-code'  => 'h',
                'description' => 'Display this help menu',
            ],
        ];

        $progress = $this->climate()->progress(count($migrations) + 1);

        foreach ($migrations as $migration) {
            $progress->advance(1, sprintf(
                'Processing migration : <blue>%s | %s | %s</blue>',
                $migration::DB_VERSION,
                $migration['description'],
                $migration['author']
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
                                'Version'     => $migration::DB_VERSION,
                                'Description' => $migration['description'],
                                'Author'      => $migration['author'],
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

            try {
                $this->migrator->up([$migration::DB_VERSION]);
            } catch (Exception $exception) {
                // Do something

            }

            $feedbacks = $this->migrator->feedback($migration::DB_VERSION);
            array_map([$this->climate(), 'info'], $feedbacks);

            $errors = $this->migrator->errors($migration::DB_VERSION);
            if (!empty($errors)) {
                // Prompt to continue or stop there
                array_map([$this->climate(), 'error'], $errors);

                $continue = $this->climate()
                                 ->error('An error occurred while processing the migrtation : '.$migration::DB_VERSION)
                                 ->confirm('Would you like to process the rest of the migrations?');

                $processed[] = [
                    'status'      => '<red>ERROR</red>',
                    'Version'     => $migration::DB_VERSION,
                    'Description' => $migration['description'],
                    'Author'      => $migration['author'],
                ];
                if (!$continue->confirmed()) {
                    break;
                } else {
                    continue;
                }
            }

            $processed[] = [
                'status'      => '<green>PROCESSED</green>',
                'Version'     => $migration::DB_VERSION,
                'Description' => $migration['description'],
                'Author'      => $migration['author'],
            ];
        }

        $progress->advance(1, '<bold>Processing complete</bold>');

        if (!empty($processed)) {
            $this->climate()->bold()->info('Summary :')->table($processed);
        }

        return $response;
    }

    /**
     * Retrieve all of the migration paths.
     *
     * @return string[]
     */
    protected function getMigrationPaths(): array
    {
        return $this->migratorConfig->get('migrations.search_paths');
    }
}
