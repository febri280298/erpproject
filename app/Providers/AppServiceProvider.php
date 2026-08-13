<?php

namespace App\Providers;

use App\Services\AccountMap;
use App\Services\DocumentNumberService;
use App\Services\LineItemCalculator;
use App\Services\ModuleRegistry;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stateless helpers — one instance per request keeps their caches warm.
        $this->app->singleton(SettingService::class);
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(AccountMap::class);
        $this->app->singleton(DocumentNumberService::class);
        $this->app->singleton(LineItemCalculator::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Model::preventLazyLoading($this->app->isLocal() && config('app.debug') === false);

        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian');

        // Super Admin bypasses every permission check.
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);

        // @module('accounting') … @endmodule — hides cross-module links in views.
        Blade::if('module', fn (string $module) => app(ModuleRegistry::class)->enabled($module));

        // Company profile is used by every layout and every printed document.
        View::composer(['layouts.*', 'partials.*', 'print.*'], function ($view) {
            $view->with('company', $this->companyProfile());
        });

        // Every view can ask which modules are switched on.
        View::share('modules', $this->app->make(ModuleRegistry::class));
    }

    /** @return array<string,mixed> */
    private function companyProfile(): array
    {
        $fallback = [
            'name' => config('app.name'),
            'address' => '',
            'phone' => '',
            'email' => '',
            'npwp' => '',
            'logo' => null,
        ];

        // Guard the very first request after `migrate:fresh`, before seeding.
        if (! Schema::hasTable('settings')) {
            return $fallback;
        }

        return array_merge($fallback, array_filter(app(SettingService::class)->company(), fn ($v) => $v !== null && $v !== ''));
    }
}
