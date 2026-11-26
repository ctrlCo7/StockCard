<?php

namespace App\Providers;

use App\Enums\RequisitionStatus;
use App\Models\ApplicationSetting;
use App\Models\Conversation;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Abilities that only the roles listed in config/permissions.php hold; administrators get no bypass. */
    private const REQUESTER_ONLY_ABILITIES = ['requisitions.create'];

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
        Paginator::useBootstrapFive();

        // Administrators may do everything; inactive accounts may do nothing.
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->is_active) {
                return false;
            }
            // Record-level policies are always evaluated, even for administrators: messages are
            // private to participants, and requisition actions depend on the RIS status.
            if (($arguments[0] ?? null) instanceof Conversation || ($arguments[0] ?? null) instanceof Requisition) {
                return null;
            }

            // Abilities reserved for the people who need the supplies; administrators process
            // requisitions but do not file them.
            if (in_array($ability, self::REQUESTER_ONLY_ABILITIES, true)) {
                return null;
            }

            return $user->isAdmin() ? true : null;
        });

        foreach (config('permissions') as $ability => $roles) {
            Gate::define($ability, fn (User $user) => $user->hasRole(...$roles));
        }

        // Settings are cached by ApplicationSetting, so this is cheap per view.
        View::composer('*', fn ($view) => $view->with('appSettings', ApplicationSetting::allWithDefaults()));

        // Number of requisitions waiting on the signed-in user's role (sidebar badge).
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $statuses = collect([
                $user?->can('requisitions.forward') ? RequisitionStatus::Submitted : null,
                $user?->can('requisitions.approve') ? RequisitionStatus::PendingApproval : null,
                $user?->can('requisitions.issue') ? RequisitionStatus::Approved : null,
            ])->filter();
            $view->with('navQueueCount', $statuses->isEmpty() ? 0 : Requisition::whereIn('status', $statuses)->count());
            $view->with('navUnreadMessages', $user ? Conversation::unreadFor($user)->count() : 0);
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('emails', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('postings', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
