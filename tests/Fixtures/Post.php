<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Envor\Datastore\Contracts\HasDatastoreContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Inmanturbo\Futomaki\Futomaki;
use Inmanturbo\Futomaki\FutomakiContract;

class Post extends Model implements FutomakiContract
{
    use Futomaki;
    use HasFactory;

    public $timestamps = true;

    protected $guarded = [];

    protected $schema = [
        'id' => 'id',
    ];

    public function getRemoteTable(): ?string
    {
        return 'remote_posts';
    }

    public static function getRemoteDriver(): string
    {
        return 'mariadb';
    }

    public static function getRemoteDatabaseName(): string
    {
        return 'remote_tests';
    }

    public function fetchData()
    {
        return $this->fetchDataAsIs();
    }

    public function checkForRemoteUpdates(): bool
    {
        return false;

        $latestOnremote = static::getRemote()->run(fn () => DB::table($this->getRemoteTable() ?? $this->getTable())->max('updated_at'))->return();
        $latestRemoteTimestamp = strtotime($latestOnremote);
        $latestLocal = app(HasDatastoreContext::class)->datastoreContext()->database()->run(fn () => DB::table($this->getTable())->max('updated_at'))->return();
        $latestLocalTimestamp = strtotime($latestLocal);

        return $latestRemoteTimestamp > $latestLocalTimestamp;
    }
}
