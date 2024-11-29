<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class GlobalHttpClientProvider extends ServiceProvider
{
    public function boot()
    {
        Http::macro('withGlobalConfig', function () {
            return Http::withOptions([
                'verify' => false,
                'timeout' => config('services.api.timeout', 30),
                'connect_timeout' => config('services.api.connect_timeout', 30),
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => '*',
                    'Access-Control-Allow-Headers' => '*',
                ])
                ->retry(3, 100);
        });
    }
}
