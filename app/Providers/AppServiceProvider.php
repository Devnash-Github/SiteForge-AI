<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GroqClient;
use App\Services\Research\GoogleCseSearchProvider;
use App\Services\Research\NullSearchProvider;
use App\Services\Research\SearchProvider;
use App\Services\Research\SerperSearchProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiClient::class, function () {
            $provider = strtolower((string) config('services.ai.provider'));
            if ($provider === '') {
                $provider = filled(config('services.gemini.key')) || blank(config('services.groq.key')) ? 'gemini' : 'groq';
            }

            return $provider === 'groq' ? new GroqClient : new GeminiClient;
        });

        $this->app->bind(SearchProvider::class, function () {
            return match (config('services.search.provider')) {
                'serper' => new SerperSearchProvider,
                'google_cse' => new GoogleCseSearchProvider,
                default => new NullSearchProvider,
            };
        });
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(12)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
