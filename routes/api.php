<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuctionEventController;
use App\Http\Controllers\Api\AuctionStateController;
use App\Http\Controllers\Api\AutoBidController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\Api\BuyNowController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\DraftUploadController;
use App\Http\Controllers\Api\DutchAcceptController;
use App\Http\Controllers\Api\IvyTaskController;
use App\Http\Controllers\Api\ViewerPresenceController;
use App\Http\Controllers\Api\ViewingController;
use App\Http\Controllers\Api\WatchlistController;
use App\Models\AiInsight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Realtime- en actie-endpoints
|--------------------------------------------------------------------------
|
| Deze routes draaien op de `web`-middleware (zie bootstrap/app.php): sessie-
| auth, geen tokens in de MVP. Ze geven JSON terug en geen pagina - het
| biedpaneel mag niet de hele pagina verversen (hfst. 7).
|
| URL's zijn hier Engels omdat het geen pagina's zijn maar een interface.
| Wat de gebruiker leest is Nederlands; wat de client leest niet.
|
*/

Route::prefix('auctions/{auction}')->name('auctions.')->group(function (): void {

    /*
     | Lezen mag zonder account: de kavelpagina moet ook voor een bezoeker
     | kloppen, en dit is de pollingfallback als de websocket wegvalt.
     */
    Route::get('state', [AuctionStateController::class, 'show'])->name('state');
    Route::get('events', [AuctionEventController::class, 'index'])->name('events');

    /*
     | De kijkershartslag ("x kijken nu"). Zonder auth: gasten tellen mee.
     | Twee per minuut per kijker is normaal; de limiet vangt misbruik.
     */
    Route::post('presence', [ViewerPresenceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('presence');

    Route::middleware('auth')->group(function (): void {

        /*
         | Alles wat een bod kan opleveren zit achter dezelfde limiet: tien
         | pogingen per gebruiker per veiling per tien seconden. Niet per IP -
         | dat zou een heel kantoor achter één verbinding tegelijk uitsluiten
         | (hfst. 6).
         */
        Route::middleware('throttle:bidding')->group(function (): void {
            Route::post('bids', [BidController::class, 'store'])->name('bids.store');
            Route::post('auto-bid', [AutoBidController::class, 'store'])->name('auto-bid.store');
            Route::post('buy-now', [BuyNowController::class, 'store'])->name('buy-now');
            Route::post('dutch-accept', [DutchAcceptController::class, 'store'])->name('dutch-accept');
        });

        // Een maximum weghalen kost niets en hoeft dus niet begrensd.
        Route::delete('auto-bid', [AutoBidController::class, 'destroy'])->name('auto-bid.destroy');

        // De borg vóór het bieden boven de drempel (hfst. 9).
        Route::post('deposit', [DepositController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('deposit.store');
    });
});

/*
 | Op id en niet op slug. Kavels binden standaard op slug omdat dat de
 | deelbare URL is, maar dit is een interface en geen pagina - de client heeft
 | het id al uit de veilingstand.
 */
Route::middleware('auth')->group(function (): void {
    Route::post('lots/{lot:id}/watch', [WatchlistController::class, 'store'])->name('lots.watch');
    Route::delete('lots/{lot:id}/watch', [WatchlistController::class, 'destroy'])->name('lots.unwatch');

    // Bezichtiging aanvragen (hfst. 7): het adres volgt per mail, pas nadat
    // de verkoper het moment bevestigd heeft.
    Route::post('lots/{lot:id}/viewings', [ViewingController::class, 'store'])
        ->middleware('throttle:10,10')
        ->name('lots.viewings.store');
});

/*
 | De funnel. Bewust zónder auth-middleware: een concept werkt op het
 | cookie-token totdat er gepubliceerd wordt - dáár komt de accountvraag
 | (hfst. 13.4). DraftAccess bewaakt elke aanroep.
 */
Route::prefix('drafts')->name('drafts.')->group(function (): void {
    Route::post('/', [DraftController::class, 'store'])->middleware('throttle:10,10')->name('store');
    Route::get('{lot:id}', [DraftController::class, 'show'])->name('show');
    Route::patch('{lot:id}', [DraftController::class, 'update'])->middleware('throttle:120,1')->name('update');
    Route::post('{lot:id}/publish', [DraftController::class, 'publish'])->name('publish');

    Route::middleware('throttle:30,1')->group(function (): void {
        Route::post('{lot:id}/media', [DraftUploadController::class, 'storeMedia'])->name('media.store');
        Route::delete('{lot:id}/media/{media}', [DraftUploadController::class, 'destroyMedia'])->name('media.destroy');
        Route::post('{lot:id}/documents', [DraftUploadController::class, 'storeDocument'])->name('documents.store');
    });
});

/*
 | Ivy (hfst. 8). Ook zonder auth-middleware: de funnel werkt zonder account
 | en de taken zijn concept-gebonden - DraftAccess bewaakt via het onderwerp.
 | Starten is schaars (dit kost geld), pollen is goedkoop.
 */
Route::prefix('ivy')->name('ivy.')->group(function (): void {
    /*
     | Eén funnelanalyse start 3-4 taken; wie twee keer klikt zit met een
     | krappe teller meteen vast. Dertig per minuut is ruim voor eerlijk
     | gebruik - de echte kostenrem is de BudgetGuard, niet deze teller.
     */
    Route::post('tasks', [IvyTaskController::class, 'store'])->middleware('throttle:30,1')->name('tasks.store');
    Route::get('tasks/{run}', [IvyTaskController::class, 'show'])->middleware('throttle:120,1')->name('tasks.show');
});

/*
 | Een dashboardkaart wegklikken. Het signaal blijft in de tabel staan
 | (dismissed_at) - verdwenen is iets anders dan opgelost.
 */
Route::middleware('auth')->post('insights/{insight}/dismiss', function (Request $request, AiInsight $insight) {
    abort_unless($insight->user_id === $request->user()->id, 404);

    $insight->dismissed_at = now();
    $insight->save();

    return response()->json(['dismissed' => true]);
})->name('insights.dismiss');
