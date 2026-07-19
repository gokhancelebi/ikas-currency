<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('command:shopify')
            ->everyMinute()
//            ->sendOutputTo(storage_path('logs/shopify-'.time().'.log'));
            ->sendOutputTo(storage_path('logs/shopify.log'));
    }


    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
