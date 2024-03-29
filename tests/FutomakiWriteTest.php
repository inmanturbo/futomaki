<?php

use Envor\Datastore\Databases\MariaDB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inmanturbo\Futomaki\Tests\Fixtures\PostWithFutomakiWrites;
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
    (new PostWithFutomakiWrites())->unlinkFile();
});

it('can write to a remote database', function () {
    $post = PostWithFutomakiWrites::create([
        'title' => 'Test Title',
        'body' => 'Test Body',
    ]);

    expect($post->writeFactory()->run(fn () => DB::table('remote_posts')->get()->count())->return())->toBe(11);
    expect(PostWithFutomakiWrites::all()->count())->toBe(11);

    $post->update([
        'title' => 'Updated Title',
        'body' => 'Updated Body',
    ]);

    expect($post->writeFactory()->run(fn () => DB::table('remote_posts')->where('id', $post->id)->first())->return()->title)->toBe('Updated Title');
    expect(PostWithFutomakiWrites::where('id', $post->id)->first()->title)->toBe('Updated Title');

    (new PostWithFutomakiWrites())->forceReload();

    expect($post->writeFactory()->run(fn () => DB::table('remote_posts')->where('id', $post->id)->first())->return()->title)->toBe('Updated Title');
    expect(PostWithFutomakiWrites::where('id', $post->id)->first()->title)->toBe('Updated Title');

    $post->delete();

    expect($post->writeFactory()->run(fn () => DB::table('remote_posts')->get()->count())->return())->toBe(10);
});
