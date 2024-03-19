<?php

namespace Inmanturbo\Futomaki;

use Envor\Datastore\Contracts\HasDatastoreContext;
use Envor\Datastore\Databases\MariaDB;
use Envor\Datastore\Databases\MySql;
use Envor\Datastore\Databases\SQLite;
use Envor\Datastore\Datastore;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Sushi\Sushi;

trait Futomaki
{
    use Sushi;

    public static function writeRemote(): bool
    {
        return false;
    }

    public function forceReload()
    {
        $this->cacheFileNotFoundOrStale($this->sushiCachePath(), $this->cacheReferencePath(), $this);

        return $this;
    }

    protected function sushiCacheFileName()
    {
        return $this->sushiFilename().'.sqlite';
    }

    protected function sushiFilename()
    {
        return config('sushi.cache-prefix', 'sushi').'-'.Str::kebab(str_replace(['/', "\00", '\\'], ' ', static::class));
    }

    public static function savingFutumaki(Model $model)
    {
        if (! static::writeRemote() || ! $model->getRemoteTable() || $model->getTable() === $model->getRemoteTable()) {
            return;
        }

        $model->setTable($model->getRemoteTable());
        $model->saveQuietly();
    }

    public static function deletingFutumaki(Model $model)
    {
        if (! static::writeRemote() || ! $model->getRemoteTable() || $model->getTable() === $model->getRemoteTable()) {
            return;
        }

        $model->setTable($model->getRemoteTable());
        $model->deleteQuietly();
    }

    public static function savedFutumaki(Model $model)
    {
        match (true) {
            method_exists(static::class, 'getRemoteDatabaseName') 
            && method_exists(static::class, 'getRemoteDriver') 
            && true === static::writeRemote() => touch($model->cacheReferencePath()),
            default => $model->writeCSV(),
        };
    }

    public static function deletedFutumaki(Model $model)
    {
        match (true) {
            method_exists(static::class, 'getRemoteDatabaseName') 
            && method_exists(static::class, 'getRemoteDriver') 
            && true === static::writeRemote() => touch($model->cacheReferencePath()),
            default => $model->writeCSV(),
        };
    }

    public static function bootFutomaki()
    {
        $instance = (new static);

        if ($instance->checkForRemoteUpdates()) {
            touch($instance->cacheReferencePath());
        }

        static::saving(function (Model $model) {
            static::savingFutumaki($model);
        });

        static::deleting(function (Model $model) {
            static::deletingFutumaki($model);
        });

        static::saved(function (Model $model) {
            static::savedFutumaki($model);
        });

        static::deleted(function (Model $model) {
            static::deletedFutumaki($model);
        });
    }

    public function checkForRemoteUpdates(): bool
    {
        return false;
    }

    public function getRemoteTable(): ?string
    {
        return null;
    }

    protected function fetchDataAsIs(): array
    {
        return once(fn () => DB::table($this->getRemoteTable() ?? $this->getTable())->get()->map(fn ($item) => (array) $item)->toArray());
    }

    public function getRows()
    {
        $rows = $this->getRemoteData();

        return match (true) {
            count($rows) === 0 => SimpleExcelReader::create($this->cacheReferencePath())->getRows()->toArray(),
            count($rows) > 0 && true === $this->writeRemote() => $this->rowsFromWriter($rows),
            count($rows) > 0 => tap($rows, function ($rows) {
                $remoteRows = $this->rowsFromWriter($rows);
                $path = str()->of($this->cacheReferencePath())->replace('.csv', '.local.csv');
                $local = file_exists($path) ? SimpleExcelReader::create($path)->getRows()->toArray() : [];
                return array_merge($remoteRows, $local);
            }),
            default => throw new \Exception('No data found'),
        };
    }

    protected function sushiShouldCache()
    {
        return true;
    }

    protected function sushiCacheReferencePath()
    {
        if (! file_exists($this->cacheReferencePath())) {
            File::ensureDirectoryExists(dirname($this->cacheReferencePath()));
            touch($this->cacheReferencePath());
        }

        return $this->cacheReferencePath();
    }

    protected function afterMigrate(Blueprint $table)
    {
        $table->boolean('is_local')->default(true)->change();
    }

    protected function afterInsert(Blueprint $table)
    {
        $table->boolean('is_local')->default(true)->change();
    }

    public function writeCSV()
    {
        $remoteRows = $this->where('is_local', false)->get()->map(fn (self $item) => $item->toArray())->toArray();
        
        $this->rowsFromWriter($remoteRows);
        
        $localRows = $this->where('is_local', true)->get()->map(fn (self $item) => $item->toArray())->toArray();
        
        if (count($localRows) > 0) {
            $this->rowsFromWriter($localRows, str()->of($this->cacheReferencePath())->replace('.csv', '.local.csv'));
        }
    }

    protected static function cacheFileNotFoundOrStale($cachePath, $dataPath, $instance)
    {
        if (! file_exists($cachePath)) {
            file_put_contents($cachePath, '');
        }

        static::setMigrationConnection($cachePath);

        $schema =static::resolveConnection()->getSchemaBuilder();
        $schema->dropIfExists($instance->getTable());

        $instance->migrate();

        static::setSqliteConnection($cachePath);

        touch($cachePath, filemtime($dataPath));
    }

    protected static function setMigrationConnection($database = null)
    {
        static::$sushiConnection = static::getMigrationConnection($database);

        static::configureSushi();
    }

    protected static function configureSushi()
    {
        app('config')->set('database.connections.'.static::class, static::$sushiConnection->getConfig());
    }

    public function getSushiConfig($options = null): array
    {
        return Arr::get(app('config')->get('database.connections.'.static::class, []), $options) ?? [];
    }

    protected static function getMigrationConnection($database = null): Connection
    {
        return app(ConnectionFactory::class)->make($config = [
            'write' => static::localConfig($database),
            'read' => static::remoteConfig(),
        ]);
    }

    protected static function getSqliteConnection($database = null): Connection
    {
        return match(true) {
            static::writeRemote() => app(ConnectionFactory::class)->make($config = [
                'read' => static::localConfig($database),
                'write' => static::remoteConfig(),
            ]),
            default => app(ConnectionFactory::class)->make($config = static::localConfig($database)),
        }; 
    }

    protected static function setSqliteConnection($database = null)
    {
        static::$sushiConnection = static::getSqliteConnection($database);

        static::configureSushi();
    }

    protected function cacheReferencePath()
    {
        $table = $this->getRemoteTable() ?? $this->getTable();

        return storage_path('framework/cache/'.$table.'.csv');
    }

    protected function rowsFromWriter(array $rows, $path = null): array
    {
        $path = $path ?? $this->cacheReferencePath();

        $writer = SimpleExcelWriter::create($path);

        $writer->addHeader(array_keys($rows[0]));

        $writer->addRows($rows);

        $writer->close();

        return SimpleExcelReader::create($path)->getRows()->toArray();
    }

    protected static function localConfig($database = null): array
    {
        $datastoreContext = app(HasDatastoreContext::class)->datastoreContext();

        return match (isset($datastoreContext)) {
            true => $datastoreContext->database()->config,
            default => [
                'driver' => 'sqlite',
                'database' => $database,
            ],
        };
    }

    protected function getRemoteData(): array
    {
        return match (true) {
            method_exists(static::class, 'fetchData') => static::getRemote()->run(fn () => once(function () {
                return $this->fetchData();
            }))->return(),
            default => [],
        };
    }

    protected static function remoteConfig($database = null): array
    {
        return static::getRemote()->config;
    }

    protected static function getRemote(): Datastore
    {
        $database = method_exists(static::class, 'getRemoteDatabaseName') ? static::getRemoteDatabaseName() : null;
        $driver = method_exists(static::class, 'getRemoteDriver') ? static::getRemoteDriver() : null;

        return match (true) {
            is_string($database) && is_string($driver) => static::getRemoteDatastore($database, $driver),
            default => app(HasDatastoreContext::class)->datastoreContext()->database(),
        };
    }

    protected static function getRemoteDatastore(string $database, string $driver): Datastore
    {
        return match ($driver) {
            'mariadb' => MariaDB::make($database),
            'sqlite' => SQLite::make($database),
            'mysql' => MySql::make($database),
            '' => throw new \Exception('Driver not set'),
            default => throw new \Exception('Unsupported driver'),
        };
    }
}
