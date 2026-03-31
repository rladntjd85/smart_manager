<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['database.connections.mysql.timezone' => '+09:00']);

        date_default_timezone_set('Asia/Seoul');

        if (app()->environment('production')) {
            // 1. 모든 URL 생성을 HTTPS로 강제
            \Illuminate\Support\Facades\URL::forceScheme('https');

            // 2. 프록시 헤더 및 Root URL 설정 (Cloud Run 401/Mixed Content 방지)
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        }
    }
}
