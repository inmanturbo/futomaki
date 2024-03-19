<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Inmanturbo\Futomaki\HasDecoratedWrites;
use Sushi\Sushi;

class PostWithDecoratedWrites extends Model
{
    use Sushi;
    use HasDecoratedWrites;

    protected $writeDatabase = 'remote_tests';

    protected $writeDriver = 'mariadb';

    protected $writeTable = 'remote_posts';

    public $timestamps = true;

    public $guarded = [];

    public function getRows()
    {
        return $this->writeFactory()->run(function () {
           return DB::table($this->writeTable)->get()->map(fn ($remoteItem) => (array) $remoteItem)->toArray();
        })->return();
    }

    protected function sushiShouldCache()
    {
        true;
    }

    public function unlinkFile()
    {
        if(file_exists($this->sushiCachePath())) {
            unlink($this->sushiCachePath());
        }
    }

    public function forceReload()
    {
        $this->unlinkFile();
        $this->refresh();
    }
}
