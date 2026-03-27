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

        // 1. 모든 URL 생성을 HTTPS로 강제 (서명 일치를 위해 필수)
        \Illuminate\Support\Facades\URL::forceScheme('https');

        // 2. 프록시 헤더 신뢰 설정 (401 방지 핵심)
        if (app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        }
    }
}
