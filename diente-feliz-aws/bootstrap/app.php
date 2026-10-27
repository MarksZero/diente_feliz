<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias(['enabled'=>\App\Http\Middleware\EnabledUser::class]);
        $middleware->trustHosts(at: function () {
            $hostname = parse_url(config('app.url'), PHP_URL_HOST);
            if (!is_string($hostname) || $hostname === '') {
                throw new \RuntimeException('APP_URL debe contener el dominio de la aplicación.');
            }
            return ['^'.preg_quote($hostname, '/').'$'];
        }, subdomains: false);
        $middleware->trustProxies(
            at: array_values(array_filter(explode(',', env('TRUSTED_PROXIES', '')))),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
