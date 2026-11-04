<?php

declare(strict_types=1);

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CategoryIndexController;
use App\Http\Controllers\ChannelConnectionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardListController;
use App\Http\Controllers\DevCheckoutController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\LotConversationController;
use App\Http\Controllers\LotDossierController;
use App\Http\Controllers\LotImportController;
use App\Http\Controllers\LotIndexController;
use App\Http\Controllers\LotManagementController;
use App\Http\Controllers\LotShareController;
use App\Http\Controllers\MailConsentController;
use App\Http\Controllers\MailgunInboundController;
use App\Http\Controllers\MailgunWebhookController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\OrderActionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderMessageController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\RedirectFallbackController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleStudioController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShareImageController;
use App\Http\Controllers\Staff\AuditLogController;
use App\Http\Controllers\Staff\BackofficeController;
use App\Http\Controllers\Staff\CategoryAdminController;
use App\Http\Controllers\Staff\ComplianceController;
use App\Http\Controllers\Staff\ControlCenterController;
use App\Http\Controllers\Staff\ConversationController as StaffConversationController;
use App\Http\Controllers\Staff\CustomerController;
use App\Http\Controllers\Staff\DisputeReviewController;
use App\Http\Controllers\Staff\ErrorLogController;
use App\Http\Controllers\Staff\FinanceController;
use App\Http\Controllers\Staff\LiveRoomController;
use App\Http\Controllers\Staff\LotDossierController as StaffLotDossierController;
use App\Http\Controllers\Staff\MailBuilderController;
use App\Http\Controllers\Staff\ModerationController;
use App\Http\Controllers\Staff\QueueController;
use App\Http\Controllers\Staff\RedirectController;
use App\Http\Controllers\Staff\ReportController;
use App\Http\Controllers\Staff\ReviewModerationController;
use App\Http\Controllers\Staff\RiskController;
use App\Http\Controllers\Staff\SupportTicketController;
use App\Http\Controllers\Staff\SystemController;
use App\Http\Controllers\Staff\TicketController;
use App\Http\Controllers\Staff\TransactionController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TwoFactorSettingsController;
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

// Afmelden voor marketingmail: via de voet of met één klik (List-Unsubscribe-Post); de link is getekend.
Route::get('/mail/afmelden/{user}/{stream}', [MailConsentController::class, 'show'])->name('mail.unsubscribe');
Route::post('/mail/afmelden/{user}/{stream}', [MailConsentController::class, 'store'])->name('mail.unsubscribe.store');

Route::get('/kavel/{lot}', [LotController::class, 'show'])->name('lots.show');
// Delen en verspreiden (briefing nieuwe functies): deelbeeld per kavel, deling met eigen token.
Route::get('/kavel/{lot}/deelbeeld/{format}.jpg', ShareImageController::class)->where('format', 'square|portrait|landscape')->name('lots.share-image');
Route::post('/kavel/{lot}/delen', [LotShareController::class, 'store'])->middleware('throttle:30,10')->name('lots.share');
Route::get('/kavel/{lot}/d/{token}', [LotShareController::class, 'show'])->where('token', '[a-z0-9]{8,24}')->name('lots.shared');
Route::post('/kavel/{lot}/d/{token}/reactie', [LotShareController::class, 'react'])->where('token', '[a-z0-9]{8,24}')->middleware('throttle:10,10')->name('lots.shared.react');
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

// Juridisch en de cookiekeuze (besluit Raph, 2 november 2026).
Route::get('/voorwaarden', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::post('/cookies/voorkeur', [CookieConsentController::class, 'store'])->middleware('throttle:30,1')->name('cookies.consent');

// Documenten-oogje (briefing nieuwe functies): bekijken na controle op de server; het bestand via een getekende URL.
Route::get('/documenten/{document}/bekijk', [DocumentController::class, 'show'])->name('documents.view');
Route::get('/documenten/{document}/bestand', [DocumentController::class, 'file'])->name('documents.file');

// Blog uit het CMS van Eazyonline (besluit Raph, 2 november 2026).
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('blog.show');

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
    // Naast de rem per e-mailadres (5 per minuut) ook één per adres, tegen
    // het proberen van gelekte wachtwoorden over veel accounts tegelijk.
    Route::post('/inloggen', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');

    // De tweede inlogstap (hfst. 15): app, mail of herstelcode.
    Route::get('/inloggen/code', [TwoFactorController::class, 'create'])->name('two-factor.challenge');
    Route::post('/inloggen/code', [TwoFactorController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.store');
    Route::post('/inloggen/code/mail', [TwoFactorController::class, 'sendMail'])->middleware('throttle:3,10')->name('two-factor.mail');

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
    Route::post('/verificatie/telefoon', [VerificationController::class, 'storePhone'])->middleware('throttle:10,10')->name('verification.phone');
    Route::post('/verificatie/telefoon/opnieuw', [VerificationController::class, 'resendPhoneCode'])->middleware('throttle:5,10')->name('verification.phone.resend');
    Route::post('/verificatie/telefoon/bevestigen', [VerificationController::class, 'confirmPhone'])->middleware('throttle:20,10')->name('verification.phone.confirm');
    Route::post('/verificatie/identiteit', [VerificationController::class, 'storeIdentity'])->name('verification.identity');
    Route::get('/verificatie/identiteit/terug', [VerificationController::class, 'identityReturn'])->middleware('throttle:20,10')->name('verification.identity.return');
    Route::get('/verificatie/identiteit/nepbank', [VerificationController::class, 'fakeBank'])->middleware('signed')->name('verification.identity.fake');
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
    // Berichten (briefing nieuwe functies): inbox en gesprek voor koper en verkoper.
    Route::get('/dashboard/berichten', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/dashboard/berichten/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::post('/dashboard/berichten/{conversation}', [ConversationController::class, 'store'])->middleware('throttle:60,10')->name('messages.store');
    Route::get('/dashboard/berichten/{conversation}/berichten', [ConversationController::class, 'messages'])->name('messages.poll');
    Route::get('/dashboard/berichten/{conversation}/bijlage/{message}', [ConversationController::class, 'attachment'])->name('messages.attachment');
    Route::post('/dashboard/berichten/{conversation}/publiceren/{message}', [ConversationController::class, 'publish'])->name('messages.publish');
    Route::post('/dashboard/berichten/{conversation}/melden', [ConversationController::class, 'report'])->middleware('throttle:10,10')->name('messages.report');
    Route::post('/dashboard/berichten/{conversation}/blokkeren', [ConversationController::class, 'block'])->name('messages.block');
    Route::post('/kavel/{lot}/vraag', [LotConversationController::class, 'ask'])->middleware('throttle:20,10')->name('lots.ask');
    Route::post('/dashboard/kavels/{lot:id}/bericht/{buyer}', [LotConversationController::class, 'message'])->middleware('throttle:20,10')->name('dashboard.lots.message');

    // Het kaveldossier (briefing nieuwe functies): tabs Overzicht, Berichten, Documenten, Verspreiding, Dossier.
    Route::get('/dashboard/kavels/{lot:id}', [LotDossierController::class, 'show'])->name('dashboard.lots.show');
    Route::get('/dashboard/kavels/{lot:id}/bewerken', [LotManagementController::class, 'edit'])->name('dashboard.lots.edit');
    Route::patch('/dashboard/kavels/{lot:id}/documenten/{document}', [DocumentController::class, 'updateVisibility'])->name('dashboard.lots.documents.visibility');
    // Verspreiding: deelkit en social push.
    Route::post('/dashboard/kavels/{lot:id}/verspreiding/ivy', [DistributionController::class, 'askIvy'])->middleware('throttle:10,10')->name('dashboard.lots.distribution.ivy');
    Route::post('/dashboard/kavels/{lot:id}/verspreiding/planning', [DistributionController::class, 'approvePlanning'])->name('dashboard.lots.distribution.approve');
    Route::post('/dashboard/kavels/{lot:id}/verspreiding/{distribution}/tekst', [DistributionController::class, 'updateText'])->name('dashboard.lots.distribution.text');
    Route::post('/dashboard/kavels/{lot:id}/verspreiding/{distribution}/geplaatst', [DistributionController::class, 'markPosted'])->name('dashboard.lots.distribution.posted');
    Route::post('/dashboard/kavels/{lot:id}/verspreiding/{distribution}/offline', [DistributionController::class, 'markWithdrawn'])->name('dashboard.lots.distribution.withdrawn');
    // Kanalen koppelen (/account/koppelingen).
    Route::get('/account/koppelingen', [ChannelConnectionController::class, 'index'])->name('account.connections');
    Route::get('/account/koppelingen/{channel}/koppelen', [ChannelConnectionController::class, 'connect'])->name('account.connections.connect');
    Route::get('/account/koppelingen/{channel}/terug', [ChannelConnectionController::class, 'callback'])->name('account.connections.callback');
    Route::delete('/account/koppelingen/{channel}', [ChannelConnectionController::class, 'disconnect'])->name('account.connections.disconnect');
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
    Route::post('/account/mail', [MailConsentController::class, 'update'])->name('account.mail-consent');
    Route::post('/account/sessies/uitloggen', [AccountSettingsController::class, 'logoutOtherSessions'])->middleware('throttle:5,10')->name('account.sessions.logout');

    // Authenticator-app (besluit Raph, 2 november 2026); elk beheer vraagt het wachtwoord.
    Route::middleware('throttle:10,10')->group(function (): void {
        Route::post('/account/tweestaps', [TwoFactorSettingsController::class, 'start'])->name('account.two-factor.start');
        Route::post('/account/tweestaps/bevestigen', [TwoFactorSettingsController::class, 'confirm'])->name('account.two-factor.confirm');
        Route::post('/account/tweestaps/annuleren', [TwoFactorSettingsController::class, 'cancel'])->name('account.two-factor.cancel');
        Route::post('/account/tweestaps/herstelcodes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])->name('account.two-factor.recovery');
        Route::delete('/account/tweestaps', [TwoFactorSettingsController::class, 'disable'])->name('account.two-factor.disable');
    });

    Route::get('/bestellingen', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/bestellingen/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/facturen/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
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
    // Melden haalt niets weg; een medewerker beslist (Google-principe).
    Route::post('/reviews/{review}/melden', [ReviewReportController::class, 'store'])
        ->middleware('throttle:10,10')
        ->name('reviews.report');

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
    // Control center (briefing nieuwe functies): cijfers, Wacht op jou, systemen, lijsten.
    Route::get('/', [ControlCenterController::class, 'index'])->name('index');
    Route::post('/taken/{task}/klaar', [ControlCenterController::class, 'completeTask'])->name('tasks.complete');

    Route::middleware('area:finance')->group(function (): void {
        Route::get('/financien', [FinanceController::class, 'index'])->name('finance');
        Route::post('/financien/kosten', [FinanceController::class, 'storeCost'])->name('finance.costs');
        Route::post('/financien/uitbetalingen/{payout}/iban', [FinanceController::class, 'requestIban'])->name('finance.iban');
        Route::post('/financien/export', [FinanceController::class, 'export'])->name('finance.export');
    });

    Route::get('/transacties', [TransactionController::class, 'index'])->middleware('area:transactions')->name('transactions');

    Route::middleware('area:customers')->group(function (): void {
        Route::get('/klanten', [CustomerController::class, 'index'])->name('customers');
        Route::get('/klanten/{customer}', [CustomerController::class, 'show'])->name('customers.show')->withTrashed();
        Route::post('/klanten/{customer}/notitie', [CustomerController::class, 'storeNote'])->middleware('area:customers,write')->name('customers.note');
        Route::post('/klanten/{customer}/taak', [CustomerController::class, 'storeTask'])->middleware('area:customers,write')->name('customers.task');
        Route::post('/klanten/{customer}/taak/{task}/klaar', [CustomerController::class, 'completeTask'])->middleware('area:customers,write')->name('customers.task.complete');
    });

    Route::middleware('area:service')->group(function (): void {
        Route::get('/service', [TicketController::class, 'index'])->name('service');
        Route::get('/service/{ticket}', [TicketController::class, 'show'])->name('service.show');
        Route::middleware('area:service,write')->group(function (): void {
            Route::post('/service/{ticket}/antwoord', [TicketController::class, 'reply'])->name('service.reply');
            Route::post('/service/{ticket}/notitie', [TicketController::class, 'note'])->name('service.note');
            Route::patch('/service/{ticket}', [TicketController::class, 'update'])->name('service.update');
            Route::post('/service/{ticket}/concept', [TicketController::class, 'draft'])->name('service.draft');
            Route::post('/service/{ticket}/opgelost', [TicketController::class, 'resolve'])->name('service.resolve');
            Route::post('/service/{ticket}/geschil', [TicketController::class, 'dispute'])->name('service.dispute');
        });
    });

    Route::middleware('area:reports')->group(function (): void {
        Route::get('/rapporten', [ReportController::class, 'index'])->name('reports');
        Route::post('/rapporten', [ReportController::class, 'run'])->name('reports.run');
        Route::get('/rapporten/download', [ReportController::class, 'download'])->name('reports.download');
    });

    // Mail Builder (briefing nieuwe functies): 46 mails, reeksen, campagnes. Eigenaar en marketing.
    Route::middleware('area:mail')->group(function (): void {
        Route::get('/mail', [MailBuilderController::class, 'index'])->name('mail');
        Route::get('/mail/{template}', [MailBuilderController::class, 'edit'])->name('mail.edit');
        Route::get('/mail/{template}/preview', [MailBuilderController::class, 'preview'])->name('mail.preview');
        Route::middleware('area:mail,write')->group(function (): void {
            Route::put('/mail/{template}', [MailBuilderController::class, 'update'])->name('mail.update');
            Route::post('/mail/{template}/test', [MailBuilderController::class, 'test'])->middleware('throttle:10,10')->name('mail.test');
            Route::post('/mail/{template}/versies/{version}', [MailBuilderController::class, 'restore'])->name('mail.restore');
            Route::post('/mail-reeksen/{automation}', [MailBuilderController::class, 'toggleAutomation'])->name('mail.automations.toggle');
            Route::post('/mail-campagnes', [MailBuilderController::class, 'storeCampaign'])->name('mail.campaigns');
            Route::post('/mail-campagnes/{campaign}/annuleren', [MailBuilderController::class, 'cancelCampaign'])->name('mail.campaigns.cancel');
        });
        Route::post('/rapporten/planning', [ReportController::class, 'storeSchedule'])->name('reports.schedule');
        Route::delete('/rapporten/planning/{schedule}', [ReportController::class, 'destroySchedule'])->name('reports.schedule.destroy');
    });
    Route::get('/activiteit', [BackofficeController::class, 'activity'])->name('activity');
    Route::get('/live', [LiveRoomController::class, 'index'])->name('live');
    Route::get('/live/paneel', [LiveRoomController::class, 'panel'])->name('live.panel');
    Route::get('/kavels', [BackofficeController::class, 'lots'])->name('lots');
    Route::post('/kavels/concepten-verwijderen', [BackofficeController::class, 'destroyDraftLots'])->name('lots.destroy-drafts');
    Route::get('/kavels/{lot:id}', [BackofficeController::class, 'showLot'])->name('lots.show')->withTrashed();
    Route::get('/kavels/{lot:id}/dossier', [StaffLotDossierController::class, 'show'])->name('lots.dossier')->withTrashed();

    // De operator: wachtrij en meelezen (briefing nieuwe functies).
    Route::get('/gesprekken', [StaffConversationController::class, 'index'])->name('conversations');
    Route::get('/gesprekken/{conversation}', [StaffConversationController::class, 'show'])->name('conversations.show');
    Route::post('/gesprekken/{conversation}/vrijgeven/{message}', [StaffConversationController::class, 'release'])->name('conversations.release');
    Route::post('/gesprekken/{conversation}/bevestigen/{message}', [StaffConversationController::class, 'confirm'])->name('conversations.confirm');
    Route::post('/gesprekken/{conversation}/reageren', [StaffConversationController::class, 'reply'])->name('conversations.reply');
    Route::post('/gesprekken/{conversation}/notitie', [StaffConversationController::class, 'note'])->name('conversations.note');
    Route::post('/gesprekken/{conversation}/geschil', [StaffConversationController::class, 'dispute'])->name('conversations.dispute');
    Route::post('/gesprekken/{conversation}/waarschuwen', [StaffConversationController::class, 'warn'])->name('conversations.warn');
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
    Route::post('/reviews/{review}/publiceren', [ReviewModerationController::class, 'publish'])->name('reviews.publish');
    Route::post('/reviews/{review}/verwijderen', [ReviewModerationController::class, 'remove'])->name('reviews.remove');
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
    Route::get('/fouten', [ErrorLogController::class, 'index'])->name('errors');
    Route::post('/fouten/{event}/afhandelen', [ErrorLogController::class, 'resolve'])->name('errors.resolve');
    Route::post('/fouten/{event}/heropenen', [ErrorLogController::class, 'reopen'])->name('errors.reopen');

    // Oude ingang van de klantenservice; de tickets staan nu onder /admin/service.
    Route::get('/support', [TicketController::class, 'index'])->middleware('area:service')->name('support');
    Route::post('/support/{ticket}/sluiten', [SupportTicketController::class, 'close'])->name('support.close');
    Route::post('/support/{ticket}/heropenen', [SupportTicketController::class, 'reopen'])->name('support.reopen');

    // Doorverwijzingen van oude paden (masterbriefing 7.2).
    Route::get('/doorverwijzingen', [RedirectController::class, 'index'])->name('redirects');
    Route::post('/doorverwijzingen', [RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('/doorverwijzingen/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');

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

// Inkomende mail via een Mailgun-route wordt een ticket (control center).
Route::post('/webhooks/mailgun/inbound', MailgunInboundController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mailgun.inbound');

// Afleverstatus van Mailgun; alleen met een geldige handtekening.
Route::post('/webhooks/mailgun', MailgunWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.mailgun');

/*
 | Geen route? Dan eerst de doorverwijzingen (masterbriefing 7.2), anders de
 | gewone 404. Dit draait alleen als niets anders paste.
 */
Route::fallback(RedirectFallbackController::class)->name('fallback');
