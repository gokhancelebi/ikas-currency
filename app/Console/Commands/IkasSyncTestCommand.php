<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class IkasSyncTestCommand extends Command
{
    protected $signature = 'ikas:sync:test {scenario? : Optional test class suffix, e.g. ImportStartupSyncTest}';

    protected $description = 'Run live İkas GraphQL sync tests (requires IKAS_LIVE_TESTS=true)';

    public function handle(): int
    {
        if (! config('ikas.live_tests')) {
            $this->error('Set IKAS_LIVE_TESTS=true in .env to run live tests.');

            return Command::FAILURE;
        }

        $scenario = $this->argument('scenario');
        $filter = $scenario
            ? '--filter='.$scenario
            : '--group=live';

        $result = Process::path(base_path())
            ->env(['IKAS_LIVE_TESTS' => 'true'])
            ->run('php artisan test tests/IkasSync '.$filter);

        $this->output->write($result->output());

        return $result->successful() ? Command::SUCCESS : Command::FAILURE;
    }
}
