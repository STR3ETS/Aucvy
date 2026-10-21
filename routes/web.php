<?php

declare(strict_types=1);

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CategoryIndexController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardListController;
use App\Http\Controllers\DevCheckoutController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\LotImportController;
use App\Http\Controllers\LotIndexController;
use App\Http\Controllers\LotManagementController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\OrderActionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderMessageController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleStudioController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\Staff\AuditLogController;
use App\Http\Controllers\Staff\BackofficeController;
use App\Http\Controllers\Staff\CategoryAdminController;
use App\Http\Controllers\Staff\ComplianceController;
use App\Http\Controllers\Staff\DisputeReviewController;
use App\Http\Controllers\Staff\LiveRoomController;
use App\Http\Controllers\Staff\ModerationController;
use App\Http\Controllers\Staff\QueueController;
use App\Http\Controllers\Staff\RiskController;
use App\Http\Controllers\Staff\SupportTicketController;
use App\Http\Controllers\Staff\SystemController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ViewingResponseController;
use App\Http\Controllers\WatchlistPageController;
use App\Models\User;
use App\Support\Experiments\HomeExperiment;
use App\Support\Privacy\ExportUserData;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

// De zoekpagina's doen per bezoek een stuk of tien queries met LIKE over de
// beschrijving; één client die ze in een lus opvraagt mag dat niet onbeperkt.
Route::get('/veilingen', [LotIndexController::class, 'index'])->middleware('throttle:120,1')->name('lots.index');

// Dezelfde pagina, met de categorie voorgeselecteerd (hfst. 7).
Route::get('/veilingen/{category}', [LotIndexController::class, 'index'])->middleware('throttle:120,1')->name('lots.category');

Route::get('/kavel/{lot}', [LotController::class, 'show'])->name('lots.show');
// Een veiling als verzameling kavels (Auction Studio, briefing sectie 1).
Route::get('/veiling/{sale}', [SaleController::class, 'show'])->name('sales.show');

// Het publieke verkopersprofiel met reviews en sterrenverdeling (hfst. 13.6).
Route::get('/verkoper/{user}', SellerProfileController::class)->middleware('throttle:120,1')->name('sellers.show');

// De zekerheidspagina: een belofte met deadlines - de termijnen komen uit
// dezelfde config die de code afdwingt (hfst. 13.6).
Route::view('/zekerheid', 'assurance')->name('assurance');

// De uitlegpagina's uit v4 (hfst. 2b): verkooproute en FAQ. Statisch, maar
// tarieven en termijnen komen uit dezelfde bron als de code.
Route::view('/verkooproute', 'selling-guide')->name('selling.guide');
Route::view('/faq', 'faq')->name('faq');

// De pagina's achter de hoofdnavigatie: het categorie-overzicht, de uitleg
// voor kopers en verkopers, en de zakelijke pagina.
Route::get('/categorieen', CategoryIndexController::class)->middleware('throttle:120,1')->name('categories.index');
Route::view('/hoe-het-werkt', 'how-it-works')->name('how-it-works');
Route::view('/zakelijk', 'business')->name('business');
Route::view('/prijzen', 'pricing')->name('pricing');

// Direct kopen als eigen overzicht (briefing sectie 1): dezelfde zoekpagina,
// vooraf gefilterd op de verkoopvorm.
Route::get('/direct-kopen', [LotIndexController::class, 'buyNow'])->middleware('throttle:120,1')->name('lots.buy-now');

// Support (briefing sectie 1): het contactformulier wordt een ticket.
Route::get('/contact', [SupportController::class, 'create'])->name('contact');
Route::post('/contact', [SupportController::class, 'store'])->middleware('throttle:5,10')->name('contact.store');

// Zoekmachines: robots.txt met de sitemap, en de sitemap met de publieke
// kavels en categorieën. Dynamisch, zodat ze de werkelijke URL's volgen.
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

// De A/B-test op de home, naast elkaar: beide ontwerpen in een iframe, met
// een schakelaar voor desktop en mobiel. Zie HomeExperiment.
Route::get('/ab-test', fn (HomeExperiment $experiment) => view('experiments.home', [
    'enabled' => $experiment->isEnabled(),
    'share' => $experiment->redesignShare(),
    'default' => $experiment->defaultVariant(),
]))->name('experiments.home');

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

    // De tweede inlogstap bij een gekoppelde uitbetaling (hfst. 15).
    Route::get('/inloggen/code', [TwoFactorController::class, 'create'])->name('two-factor.challenge');
    Route::post('/inloggen/code', [TwoFactorController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.store');

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
    Route::post('/verificatie/ubo', [VerificationController::class, 'storeUbo'])->name('verification.ubo');

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

    // De lijstpagina's onder het dashboard (hfst. 7).
    Route::get('/dashboard/kavels', [DashboardListController::class, 'lots'])->name('dashboard.lots');
    Route::get('/dashboard/biedingen', [DashboardListController::class, 'bids'])->name('dashboard.bids');
    Route::get('/dashboard/uitbetalingen', [DashboardListController::class, 'payouts'])->name('dashboard.payouts');
    Route::get('/dashboard/geschillen', [DashboardListController::class, 'disputes'])->name('dashboard.disputes');
    Route::get('/dashboard/financien', [DashboardListController::class, 'finance'])->name('dashboard.finance');
    Route::get('/dashboard/financien/export', [DashboardListController::class, 'financeExport'])->name('dashboard.finance.export');

    // CSV-import van kavels (briefing sectie 4): uploaden, kolommen koppelen, importeren als concepten.
    Route::get('/dashboard/import', [LotImportController::class, 'index'])->name('dashboard.import');
    Route::post('/dashboard/import', [LotImportController::class, 'store'])->middleware('throttle:10,10')->name('dashboard.import.store');
    Route::get('/dashboard/import/{import}', [LotImportController::class, 'show'])->name('dashboard.import.show');
    Route::post('/dashboard/import/{import}/uitvoeren', [LotImportController::class, 'run'])->name('dashboard.import.run');

    // Team van het bedrijfsaccount (briefing sectie 2: organisatierollen).
    Route::get('/dashboard/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/dashboard/team/uitnodigen', [TeamController::class, 'invite'])->middleware('throttle:20,10')->name('team.invite');
    Route::patch('/dashboard/team/{member}/rol', [TeamController::class, 'updateRole'])->name('team.role');
    Route::delete('/dashboard/team/{member}', [TeamController::class, 'remove'])->name('team.remove');
    Route::delete('/dashboard/team/uitnodiging/{invitation}', [TeamController::class, 'revoke'])->name('team.revoke');
    Route::get('/team/uitnodiging/{invitation}', [TeamController::class, 'showInvitation'])->name('team.invitation');
    Route::post('/team/uitnodiging/{invitation}', [TeamController::class, 'accept'])->middleware('throttle:10,10')->name('team.accept');

    // Organisatie-instellingen (briefing sectie 4): bedrijf, pakket, verificatie, uitbetaalrekening.
    Route::get('/dashboard/organisatie', [OrganisationController::class, 'show'])->name('organisation.show');
    Route::patch('/dashboard/organisatie', [OrganisationController::class, 'update'])->name('organisation.update');
    Route::patch('/dashboard/organisatie/uitbetaling', [OrganisationController::class, 'updatePayout'])->middleware('throttle:5,10')->name('organisation.payout');

    /*
     | Eigen kavels beheren (briefing sectie 4): bewerken, pauzeren,
     | hervatten, dupliceren, opnieuw plaatsen en bulk.
     */
    Route::post('/dashboard/kavels/bulk', [LotManagementController::class, 'bulk'])->middleware('throttle:10,10')->name('dashboard.lots.bulk');
    Route::get('/dashboard/kavels/{lot:id}/bewerken', [LotManagementController::class, 'edit'])->name('dashboard.lots.edit');
    Route::patch('/dashboard/kavels/{lot:id}', [LotManagementController::class, 'update'])->name('dashboard.lots.update');
    Route::post('/dashboard/kavels/{lot:id}/pauzeren', [LotManagementController::class, 'pause'])->name('dashboard.lots.pause');
    Route::post('/dashboard/kavels/{lot:id}/hervatten', [LotManagementController::class, 'resume'])->name('dashboard.lots.resume');
    Route::post('/dashboard/kavels/{lot:id}/dupliceren', [LotManagementController::class, 'duplicate'])->middleware('throttle:30,10')->name('dashboard.lots.duplicate');
    Route::post('/dashboard/kavels/{lot:id}/vertalen', [LotManagementController::class, 'translate'])->middleware('throttle:10,10')->name('dashboard.lots.translate');

    // Auction Studio (briefing sectie 4): veilingen plannen met meerdere kavels.
    Route::get('/dashboard/veilingen', [SaleStudioController::class, 'index'])->name('dashboard.sales');
    Route::get('/dashboard/veilingen/nieuw', [SaleStudioController::class, 'create'])->name('dashboard.sales.create');
    Route::post('/dashboard/veilingen', [SaleStudioController::class, 'store'])->name('dashboard.sales.store');
    Route::get('/dashboard/veilingen/{sale:id}', [SaleStudioController::class, 'edit'])->name('dashboard.sales.edit');
    Route::patch('/dashboard/veilingen/{sale:id}', [SaleStudioController::class, 'update'])->name('dashboard.sales.update');
    Route::post('/dashboard/veilingen/{sale:id}/kavels', [SaleStudioController::class, 'attach'])->name('dashboard.sales.attach');
    Route::delete('/dashboard/veilingen/{sale:id}/kavels/{lot:id}', [SaleStudioController::class, 'detach'])->name('dashboard.sales.detach');
    Route::post('/dashboard/veilingen/{sale:id}/kavels/{lot:id}/verplaatsen', [SaleStudioController::class, 'move'])->name('dashboard.sales.move');
    Route::post('/dashboard/veilingen/{sale:id}/publiceren', [SaleStudioController::class, 'publish'])->name('dashboard.sales.publish');
    Route::post('/dashboard/veilingen/{sale:id}/kavels-publiceren', [SaleStudioController::class, 'publishDrafts'])->middleware('throttle:10,10')->name('dashboard.sales.publish-drafts');
    Route::post('/dashboard/veilingen/{sale:id}/annuleren', [SaleStudioController::class, 'cancel'])->name('dashboard.sales.cancel');

    /*
     | Het koperportaal uit de briefing (sectie 3): volglijst, alerts en het
     | meldingencentrum; plus profiel en instellingen (sectie 2).
     */
    Route::get('/dashboard/volglijst', [WatchlistPageController::class, 'index'])->name('dashboard.watchlist');
    Route::delete('/dashboard/volglijst/{lot:id}', [WatchlistPageController::class, 'destroy'])->name('dashboard.watchlist.remove');

    Route::get('/dashboard/alerts', [SavedSearchController::class, 'index'])->name('dashboard.alerts');
    Route::post('/dashboard/alerts', [SavedSearchController::class, 'store'])->middleware('throttle:20,10')->name('dashboard.alerts.store');
    Route::patch('/dashboard/alerts/{savedSearch}', [SavedSearchController::class, 'update'])->name('dashboard.alerts.update');
    Route::delete('/dashboard/alerts/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('dashboard.alerts.destroy');

    Route::get('/dashboard/meldingen', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/dashboard/meldingen/gelezen', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/dashboard/meldingen/{id}', [NotificationCenterController::class, 'open'])->name('notifications.open');

    Route::get('/account', [AccountSettingsController::class, 'show'])->name('account.settings');
    Route::patch('/account/profiel', [AccountSettingsController::class, 'updateProfile'])->name('account.profile');
    Route::patch('/account/wachtwoord', [AccountSettingsController::class, 'updatePassword'])->middleware('throttle:5,10')->name('account.password');
    Route::patch('/account/meldingen', [AccountSettingsController::class, 'updateNotifications'])->name('account.notifications');
    Route::post('/account/sessies/uitloggen', [AccountSettingsController::class, 'logoutOtherSessions'])->middleware('throttle:5,10')->name('account.sessions.logout');

    Route::get('/bestellingen', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/bestellingen/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/bestellingen/{order}/berichten', [OrderMessageController::class, 'store'])->middleware('throttle:30,10')->name('orders.messages.store');

    Route::post('/bestellingen/{order}/betalen', [OrderActionController::class, 'pay'])->name('orders.pay');
    Route::post('/bestellingen/{order}/verzonden', [OrderActionController::class, 'ship'])->name('orders.ship');
    Route::post('/bestellingen/{order}/geleverd', [OrderActionController::class, 'confirmDelivery'])->name('orders.deliver');
    Route::post('/bestellingen/{order}/probleem', [OrderActionController::class, 'reportProblem'])
        ->middleware('throttle:5,10')
        ->name('orders.dispute');
    // De verkoper reageert op de melding; daarna ligt het bij een medewerker.
    Route::post('/bestellingen/{order}/geschil/reactie', [OrderActionController::class, 'respondToDispute'])
        ->middleware('throttle:10,10')
        ->name('orders.dispute.respond');

    // Reviews: alleen bij een afgeronde order, en ze blijven staan (13.6).
    Route::post('/bestellingen/{order}/review', [ReviewController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('orders.review');
    Route::post('/reviews/{review}/reactie', [ReviewController::class, 'reply'])
        ->middleware('throttle:5,10')
        ->name('reviews.reply');

    /*
     | De inzage-export (hfst. 15): tijdelijk getekende link uit de mail,
     | en alleen voor de gebruiker zelf - dubbel slot.
     */
    Route::get('/account/gegevens-export/{user}', function (Request $request, User $user) {
        abort_unless($request->user()->is($user), 403);

        $path = ExportUserData::path($user);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'aucvy-gegevens.json');
    })->middleware('signed')->name('account.export.download');

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
 | De backoffice op /admin: alles inzien in de huisstijl, met het menu
 | links. Zelfde staff-gate als de geschillenroute.
 */
Route::middleware(['auth', 'can:staff'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [BackofficeController::class, 'index'])->name('index');
    Route::get('/activiteit', [BackofficeController::class, 'activity'])->name('activity');
    Route::get('/live', [LiveRoomController::class, 'index'])->name('live');
    Route::get('/live/paneel', [LiveRoomController::class, 'panel'])->name('live.panel');
    Route::get('/kavels', [BackofficeController::class, 'lots'])->name('lots');
    Route::post('/kavels/concepten-verwijderen', [BackofficeController::class, 'destroyDraftLots'])->name('lots.destroy-drafts');
    Route::get('/kavels/{lot:id}', [BackofficeController::class, 'showLot'])->name('lots.show')->withTrashed();
    Route::post('/ai-runs/verwijderen', [BackofficeController::class, 'destroyAiRuns'])->name('ai-runs.destroy');
    Route::get('/veilingen', [BackofficeController::class, 'auctions'])->name('auctions');
    Route::get('/gebundelde-veilingen', [BackofficeController::class, 'sales'])->name('sales');
    Route::get('/biedingen', [BackofficeController::class, 'bids'])->name('bids');
    Route::get('/orders', [BackofficeController::class, 'orders'])->name('orders');
    Route::get('/orders/{order:id}', [BackofficeController::class, 'showOrder'])->name('orders.show');
    Route::post('/orders/{order:id}/berichten', [OrderMessageController::class, 'storeAsStaff'])->name('orders.messages.store');
    Route::get('/uitbetalingen', [BackofficeController::class, 'payouts'])->name('payouts');
    Route::get('/gebruikers', [BackofficeController::class, 'users'])->name('users');
    Route::get('/gebruikers/{user:id}', [BackofficeController::class, 'showUser'])->name('users.show')->withTrashed();
    Route::get('/bedrijven', [BackofficeController::class, 'companies'])->name('companies');
    Route::get('/bedrijven/{company:id}', [BackofficeController::class, 'showCompany'])->name('companies.show')->withTrashed();
    Route::get('/reviews', [BackofficeController::class, 'reviews'])->name('reviews');
    Route::get('/bezichtigingen', [BackofficeController::class, 'viewings'])->name('viewings');
    Route::get('/ai-runs', [BackofficeController::class, 'aiRuns'])->name('ai-runs');

    /*
     | Moderatie, compliance en het auditlog (briefing sectie 7 en de
     | admin-P0 uit de bouwlijst). Elk besluit loopt door een Action met
     | auditregel en mail; de pagina's zelf zijn dun.
     */
    Route::get('/moderatie', [ModerationController::class, 'index'])->name('moderation');
    Route::post('/moderatie/{lot:id}/goedkeuren', [ModerationController::class, 'approve'])->name('moderation.approve');
    Route::post('/moderatie/{lot:id}/afwijzen', [ModerationController::class, 'reject'])->name('moderation.reject');

    Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance');
    Route::post('/compliance/identiteit/{user:id}', [ComplianceController::class, 'decideKyc'])->name('compliance.kyc');
    Route::post('/compliance/bedrijf/{company:id}', [ComplianceController::class, 'decideKyb'])->name('compliance.kyb');
    Route::post('/compliance/blokkeren/{user:id}', [ComplianceController::class, 'block'])->name('compliance.block');
    Route::post('/compliance/vrijgeven/{user:id}', [ComplianceController::class, 'unblock'])->name('compliance.unblock');

    Route::get('/auditlog', [AuditLogController::class, 'index'])->name('auditlog');
    Route::get('/risico', [RiskController::class, 'index'])->name('risk');

    // De wachtrij als dead-letter-bak (briefing sectie 8).
    Route::get('/wachtrij', [QueueController::class, 'index'])->name('queue');
    Route::post('/wachtrij/opnieuw', [QueueController::class, 'retry'])->name('queue.retry');
    Route::post('/wachtrij/vergeten', [QueueController::class, 'forget'])->name('queue.forget');
    Route::post('/wachtrij/opruimen', [QueueController::class, 'flush'])->name('queue.flush');
    Route::get('/systeem', [SystemController::class, 'index'])->name('system');

    Route::get('/support', [SupportTicketController::class, 'index'])->name('support');
    Route::post('/support/{ticket}/sluiten', [SupportTicketController::class, 'close'])->name('support.close');
    Route::post('/support/{ticket}/heropenen', [SupportTicketController::class, 'reopen'])->name('support.reopen');

    Route::get('/categorieen', [CategoryAdminController::class, 'index'])->name('categories');
    Route::post('/categorieen', [CategoryAdminController::class, 'store'])->name('categories.store');
    Route::put('/categorieen/{category:id}', [CategoryAdminController::class, 'update'])->name('categories.update');
    Route::get('/categorieen/{category:id}/kenmerken', [CategoryAdminController::class, 'schema'])->name('categories.schema');
    Route::put('/categorieen/{category:id}/kenmerken', [CategoryAdminController::class, 'updateSchema'])->name('categories.schema.update');
    Route::delete('/categorieen/{category:id}', [CategoryAdminController::class, 'destroy'])->name('categories.destroy');
});

/*
 | De verkoper reageert op een bezichtigingsaanvraag via getekende links uit
 | zijn mail - zonder inloggen, net als de e-mailbevestiging (hfst. 7).
 */
Route::middleware('signed')->group(function (): void {
    Route::get('/bezichtigingen/{viewing}/bevestigen', [ViewingResponseController::class, 'confirm'])->name('viewings.confirm');
    Route::get('/bezichtigingen/{viewing}/afwijzen', [ViewingResponseController::class, 'decline'])->name('viewings.decline');
});

/*
 | De webhook van de betaalprovider: zonder auth en zonder CSRF (uitzondering
 | in bootstrap/app.php) - Mollie heeft geen sessie. De status komt uit de
 | provider-API, nooit uit deze request (hfst. 14).
 */
Route::post('/webhooks/mollie', MollieWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mollie');
