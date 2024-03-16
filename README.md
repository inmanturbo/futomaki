# Fat sushi rolls

[![Latest Version on Packagist](https://img.shields.io/packagist/v/inmanturbo/futomaki.svg?style=flat-square)](https://packagist.org/packages/inmanturbo/futomaki)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/inmanturbo/futomaki/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/inmanturbo/futomaki/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/inmanturbo/futomaki/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/inmanturbo/futomaki/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/inmanturbo/futomaki.svg?style=flat-square)](https://packagist.org/packages/inmanturbo/futomaki)

Escape your legacy project's database into a fresh green field. This is package uses [calebporzio/sushi](https://github.com/calebporzio/sushi) under the hood to fetch remote data and cache it locally in an sqlite database. Support remote writes (which bust and reload the cache) and local reads. Also supports transforming local data and a seperate table and column name(s) from remote and local.

## Installation

You can install the package via composer:

```bash
composer require inmanturbo/futomaki
```

## Usage

```php
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
        // this method is not needed if your local and remote tables will have the same name.
        return 'remote_posts';
    }

    public static function getRemoteDriver(): string
    {
        // you must have a configured database connection for the driver by the same name as the driver
        return 'mariadb';
    }

    public static function getRemoteDatabaseName(): string
    {
        return 'remote_tests';
    }

    public function fetchData() : array
    {
        // The fetchDataAsIs() implementation is provided by Futomaki, It assumes the local table will be identical to the remote.
        // this function will be run with the remote connection as the default connection!
        return $this->fetchDataAsIs();
    }

    public function checkForRemoteUpdates(): bool
    {
        // runs whenever the model boots. You can check some condition(s) here and return true to bust the cache.
        return false;
    }
}
```

### Advanced usage example

```php
    public function fetchData() : array
    {
        return once(fn () => DB::table($this->getRemoteTable() ?? $this->getTable())->get()->map(fn ($remoteItem) => [
            'title' => $remoteItem->title,
            'content' => $remoteItem->body,
            'excerpt' => mb_substr($remoteItem->body, 0, 100),
            'created_at' => $remoteItem->created_at,
            'updated_at' => $remoteItem->updated_at,
        ])->toArray());
    }
```

### A note on writing to remote

Remember to use your remote columns.

```php
Post::create([
    'title' => 'A Title',
    'body' => 'this is the content',
]);
```

Or implement a mutator

```php
    public function content(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value, array $attributes) => $this->writeBody($value, $attributes),
        );
    }

    public function writeBody(string $value, array $attributes): array
    {
        $attributes['body'] = $value;
        unset($attributes['content']);
        return $attributes;
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
