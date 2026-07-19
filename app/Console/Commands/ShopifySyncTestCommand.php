<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ShopifySyncTestCommand extends Command
{
    protected $signature = 'shopify:sync:test {scenario? : Optional test class suffix, e.g. ImportStartupSyncTest}';

    protected $description = 'Run live Shopify GraphQL sync tests (requires SHOPIFY_LIVE_TESTS=true)';

    public function handle(): int
    {
        if (! config('shopify.live_tests')) {
            $this->error('Set SHOPIFY_LIVE_TESTS=true in .env to run live tests.');

            return Command::FAILURE;
        }

        $scenario = $this->argument('scenario');
        $filter = $scenario
            ? '--filter='.$scenario
            : '--group=live';

        $result = Process::path(base_path())
            ->env(['SHOPIFY_LIVE_TESTS' => 'true'])
            ->run('php artisan test tests/ShopifySync '.$filter);

        $this->output->write($result->output());

        return $result->successful() ? Command::SUCCESS : Command::FAILURE;
    }
}
