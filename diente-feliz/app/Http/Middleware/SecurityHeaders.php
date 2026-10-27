<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SecurityHeaders {
    public function handle(Request $request, Closure $next) {
        $response = $next($request);
        foreach (['Content-Security-Policy'=>"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'",'X-Content-Type-Options'=>'nosniff','X-Frame-Options'=>'DENY','Referrer-Policy'=>'no-referrer','Permissions-Policy'=>'camera=(), microphone=(), geolocation=()','Cache-Control'=>'no-store, private'] as $key=>$value) $response->headers->set($key,$value);
        if ($request->isSecure()) $response->headers->set('Strict-Transport-Security','max-age=31536000');
        return $response;
    }
}
