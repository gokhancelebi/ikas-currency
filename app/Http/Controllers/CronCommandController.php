<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

class CronCommandController extends Controller
{
    public function refresh_migrations(): void
    {
        Artisan::call('view:clear');
    }
}
