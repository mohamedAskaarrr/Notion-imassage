<?php

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // Bind services into the container here.
    public function register(): void
    {
        $this->app->bind(ClientInterface::class, Client::class);
    }

    // Bootstrap any application services here.
    public function boot(): void {}
}
