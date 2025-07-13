<?php

namespace dayemsiddiqui\EloquentDefaults\Commands;

use Illuminate\Console\Command;

class EloquentDefaultsCommand extends Command
{
    public $signature = 'eloquent-defaults';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
