<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AccessMatrix;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        foreach (array_keys(AccessMatrix::ABILITIES) as $ability) {
            Gate::define($ability, fn (User $user) => AccessMatrix::allows($user->role, $ability));
        }

        // API agent: heartbeat 6/menit + batch event 60/menit + perintah 20/menit
        // per agent; batas 600/menit menyisakan ruang untuk kirim ulang antrean.
        RateLimiter::for('agent-api', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(600)->by($user instanceof Agent ? 'agent:'.$user->id : 'ip:'.$request->ip());
        });
        RateLimiter::for('agent-register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Audit login/logout (FR-11: setiap aksi penting dicatat).
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->record('login', $event->user, ['ip' => request()->ip()], $event->user);
            }
        });
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->record('logout', $event->user, [], $event->user);
            }
        });
        Event::listen(Failed::class, function (Failed $event) {
            app(AuditLogger::class)->record('login_gagal', null, [
                'email' => (string) ($event->credentials['email'] ?? ''),
                'ip' => request()->ip(),
            ]);
        });
    }
}
