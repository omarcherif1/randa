<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureCanAccessPage;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Sécurité des comptes (section 3) — s'auto-neutralisent pour les invités.
            EnsureAccountActive::class,
            EnsurePasswordChanged::class,
        ]);

        $middleware->alias([
            'page' => EnsureCanAccessPage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exigence 7 — page d'erreur explicite plutôt qu'un écran blanc, en
        // production. En local / tests on garde la trace de debug native.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if (
                ! app()->environment(['local', 'testing'])
                && ! $request->expectsJson()
                && in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)
            ) {
                return inertia('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
