<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MTProtoServiceInterface;
use App\Services\MTProtoService;

class MTProtoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(MTProtoServiceInterface::class, function ($app) {
            return new MTProtoService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
