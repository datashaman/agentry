<?php

namespace App\Providers;

use App\Events\OpsRequestTransitioned;
use App\Events\WorkItemClassified;
use App\Events\WorkItemTracked;
use App\Listeners\DispatchAgentWork;
use App\Listeners\DispatchWorkItemAgentWork;
use App\Listeners\DispatchWorkItemTeamWork;
use App\Services\WorkItemProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkItemProviderManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(OpsRequestTransitioned::class, DispatchAgentWork::class);
        Event::listen(WorkItemTracked::class, DispatchWorkItemAgentWork::class);
        Event::listen(WorkItemClassified::class, DispatchWorkItemTeamWork::class);

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('atlassian', \SocialiteProviders\Atlassian\Provider::class);
        });

        Gate::define('viewWebTinker', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }

            if (! $user) {
                return false;
            }

            $emails = array_filter(array_map('trim', explode(',', config('web-tinker.allowed_emails', ''))));

            return in_array($user->email, $emails);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
