<?php

namespace App\Providers;

use App\Services\ArticleAssistant;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ArticleAssistant::class, fn () => new ArticleAssistant(
            config('services.anthropic.key'),
            config('services.anthropic.models'),
            config('services.anthropic.workspace'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
