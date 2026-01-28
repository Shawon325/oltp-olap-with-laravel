<?php

namespace App\Console\Commands;

use Closure;
use ReflectionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;

class MigrateCommand extends BaseMigrateCommand
{
    /**
     * @param  Dispatcher  $dispatcher
     */
    public function __construct(Dispatcher $dispatcher)
    {
        parent::__construct(app("migrator"), $dispatcher);
    }

    /**
     * @return int
     * @throws FileNotFoundException
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $database = $this->option('database');

        $this->migrator->usingConnection($database,
            function () use ($database) {
                $this->prepareDatabase();

                $migrations = $this->migrator->getMigrationFiles($this->getMigrationPaths());

                $migrationsToRun = $this->filterMigrationsByConnection($migrations,
                    $database);

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
    protected function filterMigrationsByConnection(
        array $migrations,
        ?string $connection
    ): array {
        $filteredMigrations = [];

        foreach ($migrations as $name => $path) {
            $resolver = Closure::bind(function ($path) {
                return $this->resolvePath($path);
            }, $this->migrator, get_class($this->migrator));

            $migration = $resolver($path);

            if ($migration->getConnection() === $connection) {
                $filteredMigrations[$name] = $path;
            }
        }

        return $filteredMigrations;
    }
}
