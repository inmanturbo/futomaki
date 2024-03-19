<?php

use Envor\Datastore\Databases\MariaDB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inmanturbo\Futomaki\Tests\Fixtures\LocalPost;
use Inmanturbo\Futomaki\Tests\Fixtures\RemotePostSeeder;
use Spatie\Docker\DockerContainer;

beforeEach(function () {
    if (! `which mysql`) {
        $this->fail('MySQL client is not installed');
    }

    if (! `which docker`) {
        $this->fail('Docker is not installed');
    }

    $this->containerInstance = DockerContainer::create('mariadb:latest')
        ->setEnvironmentVariable('MARIADB_ROOT_PASSWORD', 'root')
        ->setEnvironmentVariable('MARIADB_DATABASE', 'laravel')
        ->name('mariadb_system')
        ->mapPort(10001, 3306)
        ->start();

    $i = 0;

    while ($i < 50) {
        $process = Process::run('mysql -u root -proot -P 10001 -h 127.0.0.1 laravel -e "show tables;"');
        if ($process->successful()) {
            break;
        }
        sleep(.5);
    }

    config(['database.connections.mariadb' => array_merge(config('database.connections.mariadb', config('database.connections.mysql')), [
        'database' => 'laravel',
        'host' => '127.0.0.1',
        'port' => '10001',
        'username' => 'root',
        'password' => 'root',
    ])]);

    $this->connection = 'mariadb';

    $refreshTest = MariaDB::make('remote_tests')->create()->run(function () {
        Schema::create('remote_posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        Artisan::call('db:seed', ['--class' => RemotePostSeeder::class]);
    });
});

afterEach(function () {
    $this->containerInstance->stop();
    unlink(storage_path('framework/cache/remote_posts.csv'));
    // unlink(storage_path('framework/cache/remote_posts.local.csv'));
});

it('can fetch from remote', function () {
    expect(LocalPost::all()->count())->toBe(10);
    expect(LocalPost::where('is_local', false)->get()->count())->toBe(10);
});

it('can write remote', function () {
    LocalPost::count();
    $post = LocalPost::create(['title' => 'test', 'content' => 'test']);

    expect(MariaDB::make('remote_tests')->run(function () {
        return DB::table('remote_posts')->count();
    })->return())->toBe(10);
});

it('will cache writes locally', function () {
    // expect(LocalPost::first()->forceReload()->all()->fresh()->count())->toBe(10);

    $post = LocalPost::create(['title' => 'test', 'content' => 'test', 'is_local' => true]);

    expect(LocalPost::first()->all()->fresh()->count())->toBe(11);
});
