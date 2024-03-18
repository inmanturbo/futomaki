<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Envor\Datastore\Contracts\HasDatastoreContext;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    public static function writeRemote(): bool
    {
        return true;
    }

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

    public function fetchData(): array
    {
        return once(fn () => DB::table($this->getRemoteTable() ?? $this->getTable())->get()->map(fn ($remoteItem) => [
            'title' => $remoteItem->title,
            'content' => $remoteItem->body,
            'excerpt' => mb_substr($remoteItem->body, 0, 100),
            'created_at' => $remoteItem->created_at,
            'updated_at' => $remoteItem->updated_at,
        ])->toArray());
    }

    public function content(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value, array $attributes) => $this->writeBody($value, $attributes),
        );
    }

    public function writeBody(string $value, array $attributes): array
    {
        if(static::writeRemote()){
            $attributes['body'] = $value;
            unset($attributes['content']);
        }
            
        return $attributes;
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
