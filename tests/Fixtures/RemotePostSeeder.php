<?php

namespace Inmanturbo\Futomaki\Tests\Fixtures;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RemotePostSeeder extends Seeder
{
    public function run()
    {

        for ($i = 0; $i < 10; $i++) {
            DB::table('remote_posts')->insert([
                'title' => "Remote Post $i Title",
                'content' => "Remote Post $i Content",
                'created_at' => now()->subDays(10),
                'updated_at' => now(),
            ]);
        }
    }
}
