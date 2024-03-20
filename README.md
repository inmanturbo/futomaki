# Fat sushi rolls

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inmanturbo/futomaki.svg?style=flat-square)](https://packagist.org/packages/inmanturbo/futomaki)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/inmanturbo/futomaki/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/inmanturbo/futomaki/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/inmanturbo/futomaki/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/inmanturbo/futomaki/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/inmanturbo/futomaki.svg?style=flat-square)](https://packagist.org/packages/inmanturbo/futomaki)

A set of features for eloquent built on top of [calebporzio/sushi](https://github.com/calebporzio/sushi).

## Installation

You can install the package via composer:

```bash
composer require inmanturbo/futomaki
```

## Usage

### Eloquent CSV Driver

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Inmanturbo\Futomaki\HasCSV;

class Post extends Model
{
    use HasCSV;

    protected $schema = [
        'id' => 'id',
        'title' => 'string',
        'content' => 'text',
    ];

    protected function CSVFileName()
    {
        return 'posts.csv';
    }

    protected function CSVDirectory()
    {
        return storage_path('csv');
    }
}
```

HasCSV uses sushi (array driver) under the hood, and supports defining a $rows property as well. A csv file will automatically be created using the defined $rows.

```php
use Illuminate\Database\Eloquent\Model;
use Inmanturbo\Futomaki\HasCSV;

class PostWithCSV extends Model
{
    use HasCSV;

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
```

### Using ->getCSVRows()

Implementing your own `getCSVRows()` method is supported as well.

```php
use Illuminate\Database\Eloquent\Model;
use Inmanturbo\Futomaki\HasCSV;

class PostWithCSV extends Model
{
    use HasCSV;

    protected $guarded = [];

    protected $schema = [
        'id' => 'id',
        'title' => 'string',
        'content' => 'text',
    ];

    public function getCSVRows()
    {
       return [
            ['id' => 1,'title' => 'Post 1', 'content' => 'Content 1'],
            ['id' => 2,'title' => 'Post 2','content' => 'Content 2'],
        ];
    }
}
```

### HasFutumakiWrites

HasFutomakiWrites is a trait which leverages eloquent's `saving()` and `deleting()` hooks to support writing sushi's changes out to another database, or api, etc.
Example Below.

```php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Inmanturbo\Futomaki\Futomaki;
use Inmanturbo\Futomaki\HasFutomakiWrites;

class PostWithFutomakiWrites extends Model
{
    use Futomaki;
    use HasFutomakiWrites;

    public $timestamps = true;

    public $guarded = [];

    protected $schema = [
        'id' => 'id',
        'title' => 'string',
        'content' => 'text',
    ];

    public function getRows()
    {
        return DB::connection('remote_posts')->table('posts')->get()->map(fn ($remoteItem) => [
            'id' => $remoteItem->id,
            'title' => $remoteItem->title,
            'content' => $remoteItem->body,
        ])->toArray();
    }

    public function futomakiSaving()
    {
        $values = [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->content,
        ];

        DB::connection('remote_posts')->transaction(function () {
            DB::connection('remote_posts')->table('posts')->upsert($values, $this->getKeyName());
        });
    }

    public function futomakiDeleting()
    {
        DB::connection('remote_posts')
            ->table('posts')
            ->where($this->getKeyName(), $this->getKey())
            ->delete();
    }
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [inmanturbo](https://github.com/inmanturbo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
