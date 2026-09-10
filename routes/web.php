<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevCheckoutController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\LotIndexController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\OrderActionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\Staff\DisputeReviewController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publieke routes
|--------------------------------------------------------------------------
|
| URL's zijn Nederlands, code en routenamen Engels (briefing hfst. 4).
| Dus /kavel/{lot:slug} met routenaam `lots.show`.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/veilingen', [LotIndexController::class, 'index'])->name('lots.index');

// Dezelfde pagina, met de categorie voorgeselecteerd (hfst. 7).
Route::get('/veilingen/{category}', [LotIndexController::class, 'index'])->name('lots.category');

Route::get('/kavel/{lot}', [LotController::class, 'show'])->name('lots.show');

// Het publieke verkopersprofiel met reviews en sterrenverdeling (hfst. 13.6).
Route::get('/verkoper/{user}', SellerProfileController::class)->name('sellers.show');

// De zekerheidspagina: een belofte met deadlines - de termijnen komen uit
// dezelfde config die de code afdwingt (hfst. 13.6).
Route::view('/zekerheid', 'assurance')->name('assurance');

// De uitlegpagina's uit v4 (hfst. 2b): verkooproute en FAQ. Statisch, maar
// tarieven en termijnen komen uit dezelfde bron als de code.
Route::view('/verkooproute', 'selling-guide')->name('selling.guide');
Route::view('/faq', 'faq')->name('faq');

// /zoeken uit de briefing (hfst. 7): zoeken en de Ivy-lezing wonen op de
// veilingenpagina - deze route stuurt er netjes heen met de vraag mee.
Route::get('/zoeken', fn (Request $request) => redirect()->route(
    'lots.index',
    array_filter(['q' => $request->query('q')]),
))->name('search');

// De verkoopfunnel. Toegankelijk zonder account; de accountvraag komt pas bij
// het publiceren (hfst. 13.4).
Route::get('/verkopen', FunnelController::class)->name('sell');

/*
|--------------------------------------------------------------------------
| Authenticatie
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/inloggen', [LoginController::class, 'create'])->name('login');
    Route::post('/inloggen', [LoginController::class, 'store'])->name('login.store');

    Route::get('/registreren', [RegisterController::class, 'create'])->name('register');
    Route::post('/registreren', [RegisterController::class, 'store'])->middleware('throttle:10,10')->name('register.store');

    Route::get('/wachtwoord-vergeten', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/wachtwoord-vergeten', [PasswordResetController::class, 'email'])->middleware('throttle:5,10')->name('password.email');
    Route::get('/wachtwoord-herstellen/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/wachtwoord-herstellen', [PasswordResetController::class, 'update'])->middleware('throttle:5,10')->name('password.update');
});

Route::post('/uitloggen', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Verificatie (hfst. 13.6)
|--------------------------------------------------------------------------
|
| Nodig vóór het eerste bod, niet vóór het rondkijken. De harde gate zit in
| PlaceBid; dit is de route ernaartoe.
|
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/verificatie', [VerificationController::class, 'show'])->name('verification.overview');
    Route::post('/verificatie/telefoon', [VerificationController::class, 'storePhone'])->name('verification.phone');
    Route::post('/verificatie/identiteit', [VerificationController::class, 'storeIdentity'])->name('verification.identity');
    Route::post('/verificatie/kvk', [VerificationController::class, 'storeKvk'])->name('verification.kvk');

    // De bevestigingslink uit de mail (signed) en het opnieuw versturen.
    Route::get('/email/verifieren/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        $request->user()->refreshBidPermission();

        return redirect()->route('verification.overview');
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/verificatielink', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('verification.link_sent'));
    })->middleware('throttle:6,1')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Bestellingen
|--------------------------------------------------------------------------
|
| Van hamerslag naar uitbetaling (hfst. 9). {order} bindt op het
| ordernummer - het autonummer blijft binnen.
|
*/

Route::middleware('auth')->group(function (): void {
    // Het werkplan van hfst. 13.5 - voor koper én verkoper, met een toggle.
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/bestellingen', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/bestellingen/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::post('/bestellingen/{order}/betalen', [OrderActionController::class, 'pay'])->name('orders.pay');
    Route::post('/bestellingen/{order}/verzonden', [OrderActionController::class, 'ship'])->name('orders.ship');
    Route::post('/bestellingen/{order}/geleverd', [OrderActionController::class, 'confirmDelivery'])->name('orders.deliver');
    Route::post('/bestellingen/{order}/probleem', [OrderActionController::class, 'reportProblem'])
        ->middleware('throttle:5,10')
        ->name('orders.dispute');

    // Reviews: alleen bij een afgeronde order, en ze blijven staan (13.6).
    Route::post('/bestellingen/{order}/review', [ReviewController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('orders.review');
    Route::post('/reviews/{review}/reactie', [ReviewController::class, 'reply'])
        ->middleware('throttle:5,10')
        ->name('reviews.reply');

    /*
     | De nepcheckout van de FakePaymentProvider. Met Mollie geconfigureerd is
     | dit een 404 - hij verdwijnt in productie vanzelf mét de provider.
     */
    Route::get('/dev/checkout/{payment}', [DevCheckoutController::class, 'show'])->name('dev.checkout');
    Route::post('/dev/checkout/{payment}', [DevCheckoutController::class, 'store'])->name('dev.checkout.store');
    Route::get('/dev/borg/{deposit}', [DevCheckoutController::class, 'showDeposit'])->name('dev.deposit');
    Route::post('/dev/borg/{deposit}', [DevCheckoutController::class, 'storeDeposit'])->name('dev.deposit.store');
});

/*
|--------------------------------------------------------------------------
| Beheer - de medewerker uit de geschilroute (hfst. 9)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:staff'])->prefix('beheer')->name('staff.')->group(function (): void {
    Route::get('/geschillen', [DisputeReviewController::class, 'index'])->name('disputes.index');
    Route::get('/geschillen/{dispute}', [DisputeReviewController::class, 'show'])->name('disputes.show');
    Route::post('/geschillen/{dispute}', [DisputeReviewController::class, 'resolve'])->name('disputes.resolve');
});

/*
 | De webhook van de betaalprovider: zonder auth en zonder CSRF (uitzondering
 | in bootstrap/app.php) - Mollie heeft geen sessie. De status komt uit de
 | provider-API, nooit uit deze request (hfst. 14).
 */
Route::post('/webhooks/mollie', MollieWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mollie');
