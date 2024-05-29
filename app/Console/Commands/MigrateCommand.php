<?php

namespace App\Console\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Filesystem\Filesystem;
use ReflectionException;
use ReflectionMethod;

class MigrateCommand extends BaseMigrateCommand
{
    protected Filesystem $files;

    /**
     * @param  Dispatcher  $dispatcher
     * @param  Filesystem  $files
     */
    public function __construct(Dispatcher $dispatcher, Filesystem $files)
    {
        parent::__construct(app("migrator"), $dispatcher);

        $this->files = $files;
    }

    /**
     * @return int
     * @throws FileNotFoundException
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $database = $this->option('database');

        $this->migrator->usingConnection($database, function () use ($database) {
            $this->prepareDatabase();

            $migrations = $this->migrator->getMigrationFiles($this->getMigrationPaths());

            $migrationsToRun = $this->filterMigrationsByConnection($migrations, $database);

            $this->migrator->setOutput($this->output)
                ->run($migrationsToRun, [
                    'pretend' => $this->option('pretend'),
                    'step' => $this->option('step'),
                ]);
        });

        return 0;
    }

    /**
     * @param  array  $migrations
     * @param  string|null  $connection
     * @return array
     * @throws FileNotFoundException
     * @throws ReflectionException
     */
    protected function filterMigrationsByConnection(array $migrations, ?string $connection): array
    {
        $filteredMigrations = [];

        foreach ($migrations as $name => $path) {
            $method = new ReflectionMethod("Illuminate\Database\Migrations\Migrator", "resolvePath");
            $method->setAccessible(true);

            $migration = $method->invokeArgs($this->migrator, [$path]);

            if ($migration->getConnection() === $connection) {
                $filteredMigrations[$name] = $path;
            }
        }

        return $filteredMigrations;
    }
}
