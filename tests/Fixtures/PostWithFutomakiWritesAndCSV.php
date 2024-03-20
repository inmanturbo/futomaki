<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Inmanturbo\Futomaki\HasCSV;
use Inmanturbo\Futomaki\HasFutomakiWrites;

class PostWithFutomakiWritesAndCSV extends Model
{
    use HasCSV;
    use HasFutomakiWrites;

    protected $schema = [
        'id' => 'id',
        'title' => 'string',
        'content' => 'text',
    ];

    protected $writeDatabase = 'remote_tests';

    protected $writeDriver = 'mariadb';

    protected $writeTable = 'remote_posts';

    public $timestamps = true;

    public $guarded = [];

    public function getCSVRows()
    {
        return $this->writeFactory()->run(function () {
            return DB::table($this->writeTable)->get()->map(fn ($remoteItem) => (array) $remoteItem)->toArray();
        })->return();
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
