<?php

namespace App\Console\Commands;

use App\Lib\IkasSync\SyncCron;
use Illuminate\Console\Command;

class IkasCron extends Command
{
    protected $signature = 'command:ikas';

    protected $description = 'İkas price sync cron';

    public function handle(): int
    {
        $this->info('İkas Sync Cron');

        $completed = app(SyncCron::class)->run();

        return $completed ? Command::SUCCESS : Command::FAILURE;
    }
}
