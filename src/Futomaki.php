<?php

namespace Inmanturbo\Futomaki;

use Sushi\Sushi;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Envor\Datastore\Datastore;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;
use Envor\Datastore\Databases\MySql;
use Illuminate\Support\Facades\File;
use Envor\Datastore\Databases\SQLite;
use Envor\Datastore\Databases\MariaDB;
use Illuminate\Database\Eloquent\Model;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Envor\Datastore\Contracts\HasDatastoreContext;
use Illuminate\Database\Connectors\ConnectionFactory;

trait Futomaki
{
    use Sushi;

    protected function sushiCacheFileName()
    {
        return $this->sushiFilename().'.sqlite';
    }

    protected function sushiFilename()
    {
        return config('sushi.cache-prefix', 'sushi').'-'.Str::kebab(str_replace(['/', "\00", '\\'], ' ', static::class));
    }

    public static function bootFutomaki()
    {
        $instance = (new static);

        match($instance->checkForRemoteUpdates()) {
            true => touch($instance->cacheReferencePath()),
        };

        static::saving(function (Model $model) {

            if(! $model->getRemoteTable() || $model->getTable() === $model->getRemoteTable()) {
                return;
            }

            config([static::class . '.table' => $model->getRemoteTable() ?? $model->getTable()]);
            $model->setTable($model->getTable());
            $model->saveQuietly();
            match (true) {
                method_exists(static::class, 'getRemoteDatabaseName') && method_exists(static::class, 'getRemoteDriver') => touch($model->cacheReferencePath()),
                default => $model->writeCSV(),
            };
            return false;
        });

        static::saved(function (Model $model) {
            match (true) {
                method_exists(static::class, 'getRemoteDatabaseName') && method_exists(static::class, 'getRemoteDriver') => touch($model->cacheReferencePath()),
                default => $model->writeCSV(),
            };
        });

        static::deleted(function (Model $model) {
            match (true) {
                method_exists(static::class, 'getRemoteDatabaseName') && method_exists(static::class, 'getRemoteDriver') => touch($model->cacheReferencePath()),
                default => $model->writeCSV(),
            };
        });
    }

    public function checkForRemoteUpdates() : bool
    {
        return false;
    }

    public function getTable() : string
    {
        return config(static::class . '.table', parent::getTable());
    }

    public function getRemoteTable() : ?string
    {
        return null;
    }

    protected function fetchDataAsIs() : array
    {
        return once(fn () => DB::table($this->getRemoteTable() ?? $this->getTable())->get()->map(fn ($item) => (array) $item)->toArray());
    }

    public function getRows()
    {
        $rows = $this->getRemoteData();

        return match (true) {
            count($rows) === 0 => SimpleExcelReader::create($this->cacheReferencePath())->getRows()->toArray(),
            count($rows) > 0 => $this->rowsFromWriter($rows),
            default => throw new \Exception('No data found'),
        };
    }

    protected function sushiShouldCache()
    {
        return true;
    }

    protected function sushiCacheReferencePath()
    {
        if(! file_exists($this->cacheReferencePath())) {
           File::ensureDirectoryExists(dirname($this->cacheReferencePath()));
           touch($this->cacheReferencePath());
        }

        return $this->cacheReferencePath();
    }

    public function writeCSV()
    {
        $rows = $this->all()->map(fn (self $item) => $item->toArray())->toArray();

        $rows = $this->rowsFromWriter($rows);
    }

    protected static function cacheFileNotFoundOrStale($cachePath, $dataPath, $instance)
    {
        static::setMigrationConnection($cachePath);

        static::resolveConnection()->getSchemaBuilder()->dropIfExists($instance->getTable());

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

    public function getSushiConfig($options = null) : array
    {
        return Arr::get(app('config')->get('database.connections.'.static::class, []), $options) ?? [];
    }

    protected static function getMigrationConnection($database = null) : Connection
    {
        return app(ConnectionFactory::class)->make($config = [
            'write' => static::localConfig($database),
            'read' => static::remoteConfig('refresh_test'),
        ]);
    }

    protected static function getSqliteConnection($database = null) : Connection
    {
        return app(ConnectionFactory::class)->make($config = [
            'read' => static::localConfig($database),
            'write' => static::remoteConfig('refresh_test'),
        ]);
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

    protected function rowsFromWriter(array $rows) : array
    {
        $writer = SimpleExcelWriter::create($this->cacheReferencePath());

        $writer->addHeader(array_keys($rows[0]));

        $writer->addRows($rows);

        $writer->close();

        return SimpleExcelReader::create($this->cacheReferencePath())->getRows()->toArray();
    }

    protected static function localConfig($database = null) : array
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

    protected function getRemoteData() : array
    {
        return match (true) {
            method_exists(static::class, 'fetchData') => static::getRemote()->run(fn () => once(function () {
                return $this->fetchData();
            }))->return(),
            default => [],
        };
    }

    protected static function remoteConfig($database = null) : array
    {
        return static::getRemote()->config;
    }

    protected static function getRemote() : Datastore
    { 
        $database = method_exists(static::class, 'getRemoteDatabaseName') ? static::getRemoteDatabaseName() : null;
        $driver = method_exists(static::class, 'getRemoteDriver') ? static::getRemoteDriver() : null;

        return match (true) {
            is_string($database) && is_string($driver) => static::getRemoteDatastore($database, $driver),
            default => app(HasDatastoreContext::class)->datastoreContext()->database(),
        };
    }

    protected static function getRemoteDatastore(string $database, string $driver) : Datastore
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