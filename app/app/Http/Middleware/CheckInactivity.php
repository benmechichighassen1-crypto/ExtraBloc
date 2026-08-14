// app/Http/Middleware/CheckInactivity.php

public function handle($request, Closure $next)
{
    if (Auth::check()) {
        $last = session('last_activity');
        if ($last && now()->diffInMinutes($last) > 60) {
            Auth::logout();
            session()->invalidate();
            return redirect()->route('login')->with('message', 'Session expirée par inactivité.');
        }
        session(['last_activity' => now()]);
    }
    return $next($request);
}