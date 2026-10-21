<?php

declare(strict_types=1);

use App\Auctions\Exceptions\BidRejected;
use App\Http\Middleware\EnsureSecondFactorOnRecall;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            /*
             | De actie-endpoints draaien bewust op de `web`-middleware en niet
             | op `api`: sessieauth, geen tokens in de MVP (hfst. 7). Dat levert
             | meteen CSRF-bescherming op het biedpad op, wat je bij een
             | endpoint dat geld kost graag hebt.
             |
             | Ze staan wel in een eigen bestand, want ze geven JSON terug —
             | het biedpaneel mag niet de hele pagina verversen.
             */
            Route::middleware('web')
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         | Webhooks van betaalproviders hebben geen sessie en dus geen
         | CSRF-token. De echtheid komt niet van dit token maar van de
         | verwerking: er wordt niets uit de payload geloofd — de status wordt
         | altijd bij de provider zelf opgehaald (hfst. 14).
         */
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // "Ingelogd blijven" omzeilt de tweede factor niet (hfst. 15).
        $middleware->web(append: [EnsureSecondFactorOnRecall::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         | Een geweigerd bod is geen serverfout maar een antwoord. Elke reden
         | krijgt zijn eigen status en zijn eigen tekst, want de UI toont ze
         | los: "je bent nog niet geverifieerd" heeft een knop naar de
         | verificatiestap, "je bod is te laag" heeft een bedrag (hfst. 6).
         */
        $exceptions->render(function (BidRejected $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json($exception->toArray(), $exception->reason->httpStatus());
        });
    })->create();
