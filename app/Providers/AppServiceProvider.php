<?php

namespace App\Providers;

use App\Models\School;
use App\Observers\SchoolObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        School::observe(SchoolObserver::class);
    }
}
