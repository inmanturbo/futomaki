<?php

namespace Inmanturbo\Futomaki\Commands;

use Illuminate\Console\Command;

class FutomakiCommand extends Command
{
    public $signature = 'futomaki';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
