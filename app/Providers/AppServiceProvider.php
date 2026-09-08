<?php

namespace App\Providers;

use App\Services\Google\GoogleIndexingService;
use App\Services\Google\GoogleSearchConsoleService;
use App\Services\Google\ServiceAccountTokenProvider;
use App\Services\IndexNowService;
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

        $this->app->singleton(GoogleSearchConsoleService::class, function ($app) {
            return new GoogleSearchConsoleService(
                tokenProvider: $app->make(ServiceAccountTokenProvider::class),
                siteUrl: config('services.google_search_console.site_url', ''),
            );
        });

        $this->app->singleton(IndexNowService::class, function () {
            $cfg = config('services.indexnow');
            return new IndexNowService(
                apiKey: $cfg['api_key'] ?? '',
                keyFileUrl: $cfg['key_file_url'] ?? '',
                host: $cfg['host'] ?? '',
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
