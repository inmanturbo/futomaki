<?php

namespace Inmanturbo\Futomaki;

use Envor\Datastore\Datastore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasFutomakiWrites
{
    use HasDatastoreFactory;

    public function withFutomakiWrites(bool $shouldDecorateWrites = true): static
    {
        $this->shouldDecorateWrites = $shouldDecorateWrites;

        return $this;
    }

    public function withoutDecoratedWrites(): static
    {
        return $this->withDecoratedWrites(false);
    }

    public static function bootHasFutomakiWrites()
    {
        static::saving(function (Model $model) {
            $model->futomakiSaving();
        });

        static::deleting(function (Model $model) {
            $model->futomakiDeleting();
        });
    }

    public function writeFactory($database = null, $driver = null): ?Datastore
    {
        $database = match (true) {
            $database === null && method_exists($this, 'getWriteDatabaseName') => $this->getWriteDatabaseName(),
            $database === null && property_exists($this, 'writeDatabase') => $this->writeDatabase,
            default => $database,
        };

        $driver = match (true) {
            $driver === null && method_exists($this, 'getWriteDriver') => $this->getWriteDriver(),
            $driver === null && property_exists($this, 'writeDriver') => $this->writeDriver,
            default => $driver,
        };

        return match (true) {
            $database === null || $driver === null => null,
            default => $this->newDatastore($database, $driver),
        };
    }

    public function futomakiSaving()
    {
        if ($this?->shouldDecorateWrites === false) {
            return;
        }

        if ($datastore = $this->writeFactory()) {
            $datastore->run(function () {
                $writeTable = match (true) {
                    method_exists($this, 'getWriteTable') => $this->getWriteTable() ?? $this->getTable(),
                    property_exists($this, 'writeTable') => $this->writeTable ?? $this->getTable(),
                    default => $this->getTable(),
                };
                DB::table($writeTable)->upsert($this->getAttributes(), $this->getKeyName());
            });
        }
    }

    public function futomakiDeleting()
    {
        if ($this?->shouldDecorateWrites === false) {
            return;
        }

        if ($datastore = $this->writeFactory()) {
            $datastore->run(function () {
                $writeTable = match (true) {
                    method_exists($this, 'getWriteTable') => $this->getWriteTable() ?? $this->getTable(),
                    property_exists($this, 'writeTable') => $this->writeTable ?? $this->getTable(),
                    default => $this->getTable(),
                };
                DB::table($writeTable)->where($this->getKeyName(), $this->getKey())->delete();
            });
        }
    }
}
