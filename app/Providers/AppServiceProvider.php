<?php

namespace App\Providers;

use App\Services\Google\GoogleIndexingService;
use App\Services\Google\ServiceAccountTokenProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ServiceAccountTokenProvider::class, function () {
            $config = config('services.google_indexing');

            return new ServiceAccountTokenProvider(
                credentialsPath: $config['credentials_path'],
                scope: $config['scope'],
                tokenUri: $config['token_uri'],
            );
        });

        $this->app->singleton(GoogleIndexingService::class, function ($app) {
            return new GoogleIndexingService(
                tokenProvider: $app->make(ServiceAccountTokenProvider::class),
                publishEndpoint: config('services.google_indexing.publish_endpoint'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
