<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Inmanturbo\Futomaki\HasCsv;

class PostWithCSV extends Model
{
    use HasCsv;

    protected $shouldDecorateWrites = true;

    protected $guarded = [];

    protected $schema = [
        'id' => 'id',
        'title' => 'string',
        'content' => 'text',
    ];

    protected $rows = [
        [
            'id' => 1,
            'title' => 'Post 1',
            'content' => 'Content 1',
        ],
        [
            'id' => 2,
            'title' => 'Post 2',
            'content' => 'Content 2',
        ],
    ];
}
