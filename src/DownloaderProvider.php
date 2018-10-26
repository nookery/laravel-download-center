<?php

namespace LaravelDownloader;

use Illuminate\Support\ServiceProvider;

class DownloaderProvider extends ServiceProvider
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
        $this->app->singleton('downloader', function ($app)
        {
            $downloadTask = new Downloader();

            return $downloadTask;
        });
    }
}
