<?php

namespace Inmanturbo\Futomaki\Tests;

use Envor\Datastore\DatastoreServiceProvider;
use Envor\SchemaMacros\SchemaMacrosServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Inmanturbo\Futomaki\FutomakiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Inmanturbo\\Futomaki\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            SchemaMacrosServiceProvider::class,
            DatastoreServiceProvider::class,
            FutomakiServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_futomaki_table.php.stub';
        $migration->up();
        */
    }
}
