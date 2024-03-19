<?php

namespace Inmanturbo\Futomaki;

use Envor\Datastore\Datastore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasDecoratedWrites
{
    use HasDatastoreFactory;

    protected $shouldDecorateWrites = true;

    public function withDecoratedWrites(bool $shouldDecorateWrites = true): static
    {
        $this->shouldDecorateWrites = $shouldDecorateWrites;

        return $this;
    }

    public function withoutDecoratedWrites(): static
    {
        return $this->withDecoratedWrites(false);
    }

    public static function bootHasDecoratedWrites()
    {
        static::saving(function (Model $model) {
            $model->decoratedSaving();
        });

        static::deleting(function (Model $model) {
            $model->decoratedDeleting();
        });
    }

    public function writeFactory($database = null, $driver = null): ?Datastore
    {
        $database = match (true) {
            null === $database && method_exists($this, 'getWriteDatabaseName') => $this->getWriteDatabaseName(),
            null === $database && property_exists($this, 'writeDatabase') => $this->writeDatabase,
            default => $database,
        };

        $driver = match (true) {
            null === $driver && method_exists($this, 'getWriteDriver') => $this->getWriteDriver(),
            null === $driver && property_exists($this, 'writeDriver') => $this->writeDriver,
            default => $driver,
        };

        return match (true) {
            null === $database || null === $driver => null,
            default => $this->newDatastore($database, $driver),
        };
    }
    
    public function decoratedSaving()
    {
        if(!$this->shouldDecorateWrites) {
            return;
        }

        if($datastore = $this->writeFactory()) {
            $datastore->run(function () use ($datastore) {
                $writeTable = match (true) {
                    method_exists($this, 'getWriteTable') => $this->getWriteTable() ?? $this->getTable(),
                    property_exists($this, 'writeTable') => $this->writeTable ?? $this->getTable(),
                    default => $this->getTable(),
                };
                DB::table($writeTable)->upsert($this->getAttributes(), $this->getKeyName());
            });
        }
    }

    public function decoratedDeleting()
    {
        if(!$this->shouldDecorateWrites) {
            return;
        }

        if($datastore = $this->writeFactory()) {
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