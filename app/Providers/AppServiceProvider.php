<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        // 로컬 환경이 아닐 때(구글 서버일 때) HTTPS를 강제합니다.
        if (app()->environment('production') || config('app.env') === 'production') {
//            URL::forceScheme('https');
        }
    }
}
