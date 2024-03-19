<?php

use Inmanturbo\Futomaki\Tests\Fixtures\PostWithCSV;
use Spatie\SimpleExcel\SimpleExcelReader;

afterEach(function () {
  $cache = storage_path('framework/cache');
  //remove all files in the cache directory
    $files = glob($cache.'/*'); // get all file names
    foreach($files as $file){ // iterate files
      if(is_file($file)) {
        unlink($file); // delete file
      }
    }
});

it('can load rows from csv', function () {
    $post = new PostWithCSV();
    expect(PostWithCSV::all()->count())->toBe(2);
});

it('can write to a csv', function () {
   $post = PostWithCSV::create([
        'id' => 3,
        'title' => 'Test Title',
        'content' => 'Test Content',
    ]);
    expect(PostWithCSV::all()->count())->toBe(3);
    expect(count(SimpleExcelReader::create($post->CSVPath())->getRows()->toArray()))->toBe(3);
});