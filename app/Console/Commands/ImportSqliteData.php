<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time move of records from the SQLite file used before Docker into the current
 * (MySQL) database. Run after migrations; existing rows in the target are replaced.
 */
class ImportSqliteData extends Command
{
    protected $signature = 'app:import-sqlite {path : Path to the SQLite database file} {--force : Replace data even if the target already has users}';

    protected $description = 'Copy all application records from an SQLite database file into the current database';

    /** Framework bookkeeping tables that are not application records. */
    private const SKIPPED_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("SQLite file not found: {$path}");

            return self::FAILURE;
        }

        config(['database.connections.sqlite_import' => [
            'driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        $source = DB::connection('sqlite_import');
        $target = DB::connection();

        if ($target->table('users')->exists() && ! $this->option('force')) {
            $this->error('The target database already has users. Re-run with --force to replace its data.');

            return self::FAILURE;
        }

        $tables = $this->inDependencyOrder(collect($source->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
            ->pluck('name')
            ->reject(fn (string $table) => in_array($table, self::SKIPPED_TABLES, true))
            ->filter(fn (string $table) => Schema::hasTable($table))
            ->values()
            ->all());

        Schema::disableForeignKeyConstraints();
        try {
            foreach (array_reverse($tables) as $table) {
                $target->table($table)->delete();
            }
            foreach ($tables as $table) {
                $copied = 0;
                $batch = [];
                $rows = $source->table($table);
                if (Schema::connection('sqlite_import')->hasColumn($table, 'id')) {
                    $rows->orderBy('id');
                }
                foreach ($rows->cursor() as $row) {
                    $batch[] = (array) $row;
                    if (count($batch) === 500) {
                        $target->table($table)->insert($batch);
                        $copied += count($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $target->table($table)->insert($batch);
                    $copied += count($batch);
                }
                $this->line(sprintf('  %-28s %6d rows', $table, $copied));
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Imported '.count($tables).' tables. Run "php artisan inventory:verify" to confirm stock balances.');

        return self::SUCCESS;
    }

    /**
     * Order tables so every table comes after the tables its foreign keys point to.
     * Rows referencing their own table (such as reversals) are handled by copying in id order.
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function inDependencyOrder(array $tables): array
    {
        $dependsOn = [];
        foreach ($tables as $table) {
            $dependsOn[$table] = collect(Schema::getForeignKeys($table))
                ->pluck('foreign_table')
                ->reject(fn (string $parent) => $parent === $table || ! in_array($parent, $tables, true))
                ->unique()->values()->all();
        }

        $ordered = [];
        $visit = function (string $table) use (&$visit, &$ordered, $dependsOn): void {
            if (in_array($table, $ordered, true)) {
                return;
            }
            $ordered[] = '__visiting__'.$table;
            foreach ($dependsOn[$table] as $parent) {
                if (! in_array('__visiting__'.$parent, $ordered, true)) {
                    $visit($parent);
                }
            }
            $ordered = array_values(array_diff($ordered, ['__visiting__'.$table]));
            $ordered[] = $table;
        };
        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }
}
