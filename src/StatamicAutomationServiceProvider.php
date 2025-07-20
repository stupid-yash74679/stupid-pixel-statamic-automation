<?php

namespace StupidPixel\StatamicAutomation;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class StatamicAutomationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    public function register()
    {
        //
    }
}
