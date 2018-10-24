<?php

namespace DownloadCenter;

use DownloadCenter\Facades\DownloadTask;
use Illuminate\Support\ServiceProvider;

class DownloadCenterProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/migrations');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('task', function ($app)
        {
            $downloadTask = new DownloadTask();

            return $downloadTask;
        });
    }
}
