<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnabledUser {
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        if (!$user->enabled() || $request->session()->get('session_version') !== $user->session_version) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            return redirect('/login')->withErrors(['email'=>'La sesión ha finalizado. Ingresa nuevamente o consulta al administrador.']);
        }
        return $next($request);
    }
}
