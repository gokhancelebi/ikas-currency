<?php

namespace App\Console\Commands;

use App\Lib\ShopifySync\SyncCron;
use Illuminate\Console\Command;

class ShopifyCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:shopify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shopify price sync cron';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Shopify Sync Cron');

        $completed = app(SyncCron::class)->run();

        return $completed ? Command::SUCCESS : Command::FAILURE;
    }
}
