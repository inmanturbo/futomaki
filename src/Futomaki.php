<?php

namespace Inmanturbo\Futomaki;

use Envor\Datastore\Contracts\HasDatastoreContext;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Arr;
use Sushi\Sushi;

trait Futomaki
{
    use HasDatastoreFactory;
    use Sushi;

    public function getRows()
    {
        return $this->rows;
    }

    protected static function setSqliteConnection($database = null)
    {
        $datastore = static::datastore($database);

        static::$sushiConnection = app(ConnectionFactory::class)->make($datastore->config);

        static::configureSushi();
    }

    protected static function cacheFileNotFoundOrStale($cachePath, $dataPath, $instance)
    {
        if (! file_exists($cachePath)) {
            file_put_contents($cachePath, '');
        }

        static::setSqliteConnection($cachePath);

        $instance->migrate();

        touch($cachePath, filemtime($dataPath));
    }

    public function migrate()
    {
        $rows = $this->getRows();
        $tableName = $this->getTable();

        $schema = static::resolveConnection()->getSchemaBuilder();
        $schema->dropIfExists($this->getTable());

        if (count($rows)) {
            $this->createTable($tableName, $rows[0]);
        } else {
            $this->createTableWithNoData($tableName);
        }

        foreach (array_chunk($rows, $this->getSushiInsertChunkSize()) ?? [] as $inserts) {
            if (! empty($inserts)) {
                static::insert($inserts);
            }
        }
    }

    protected static function configureSushi()
    {
        app('config')->set('database.connections.'.static::class, static::$sushiConnection->getConfig());
    }

    public function getSushiConfig($options = null): array
    {
        return Arr::get(app('config')->get('database.connections.'.static::class, []), $options) ?? [];
    }

    public function forceReload()
    {
        static::cacheFileNotFoundOrStale($this->sushiCachePath(), $this->dataPath(), $this);

        return $this;
    }

    public static function sushiDriver()
    {
        return (new static)?->futomakiDriver ?? 'sqlite';
    }

    public static function sushiDatabase()
    {
        $instance = new static;

        return (new static)?->futomakiDatabase ?? $instance->getSushiSushiDatabase();
    }

    public static function datastore($database = null, $driver = null)
    {
        $datastoreContext = app(HasDatastoreContext::class)->datastoreContext();

        if (isset($datastoreContext)) {
            return $datastoreContext->database();
        }

        $database = $database ?? static::sushiDatabase();
        $driver = $driver ?? static::sushiDriver();

        return static::newDatastore($database, $driver);
    }

    public function getSushiSushiDatabase()
    {
        return $this->sushiCachePath();
    }
}
