<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Inmanturbo\Futomaki\HasCsv;
use Inmanturbo\Futomaki\HasFutomakiWrites;

class PostWithRemoteReadCSVAndLocalWrites extends Model
{
    use HasCsv;
    use HasFutomakiWrites;

    protected $schema = [
        'id' => 'id',
        'title' => 'string',
        'content' => 'text',
    ];

    public bool $shouldDecorateWrites = false;

    protected $writeDatabase = 'remote_tests';

    protected $writeDriver = 'mariadb';

    protected $writeTable = 'remote_posts';

    public $timestamps = true;

    public $guarded = [];

    public function getCsvRows()
    {
        $remoteRows = $this->writeFactory()->run(function () {
            return DB::table($this->writeTable)->get()->map(fn ($remoteItem) => (array) $remoteItem)->toArray();
        })->return();

        $remoteIds = array_column($remoteRows, 'id');

        if (! file_exists($this->sushiCachePath())) {
            return $remoteRows;
        }

        $localRows = $this->whereNotIn('id', $remoteIds)->get()->map(fn ($localItem) => $localItem->toArray())->toArray();

        return array_merge($remoteRows, $localRows);
    }

    protected function sushiShouldCache()
    {

    }

    public function unlinkFile()
    {
        if (file_exists($this->sushiCachePath())) {
            unlink($this->sushiCachePath());
        }
    }

    public function forceReload()
    {
        $this->unlinkFile();
    }
}
