<?php

namespace Inmanturbo\Futomaki;

use Envor\Datastore\Databases\MariaDB;
use Envor\Datastore\Databases\MySql;
use Envor\Datastore\Databases\SQLite;
use Envor\Datastore\Datastore;

trait HasDatastoreFactory
{
    protected static function newDatastore(string $database, string $driver): Datastore
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