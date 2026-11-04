<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\StaffRole;
use App\Compliance\Identity\FakeIdentityProvider;
use App\Compliance\Identity\IdentityProvider;
use App\Ivy\Chat\AnthropicChatProvider;
use App\Ivy\Chat\ChatProvider;
use App\Ivy\Chat\FakeChatProvider;
use App\Ivy\Contracts\LlmProvider;
use App\Ivy\Providers\AnthropicProvider;
use App\Ivy\Providers\FakeProvider;
use App\Live\FakeLiveProvider;
use App\Live\LiveKitProvider;
use App\Live\LiveProvider;
use App\Models\Auction;
use App\Models\User;
use App\Orders\Contracts\PaymentProvider;
use App\Orders\Providers\FakePaymentProvider;
use App\Orders\Providers\MollieProvider;
use App\Support\Shipping\Contracts\ShippingProvider;
use App\Support\Shipping\Providers\FakeShippingProvider;
use App\Support\Sms\LogSmsSender;
use App\Support\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // De provider achter Ivy - wisselen is één env-variabele (hfst. 14).
        // `fake` draait zonder sleutel en is deterministisch; tests kunnen de
        // interface eenvoudig herbinden aan een eigen stub.
        $this->app->bind(LlmProvider::class, fn (): LlmProvider => match (config('aucvy.ai.provider')) {
            'anthropic' => new AnthropicProvider,
            default => new FakeProvider,
        });

        // Zelfde patroon voor de betaalprovider (hfst. 9 en 14). Singleton:
        // de nepprovider en de nepcheckout moeten binnen één request dezelfde
        // instantie zien.
        $this->app->singleton(PaymentProvider::class, fn (): PaymentProvider => match (config('aucvy.payments.provider')) {
            'mollie' => new MollieProvider,
            default => new FakePaymentProvider,
        });

        // En voor transport (hfst. 14): een echte partner is één klasse erbij.
        $this->app->bind(ShippingProvider::class, fn (): ShippingProvider => match (config('aucvy.shipping.provider')) {
            default => new FakeShippingProvider,
        });

        // Sms (besluit Raph, 2 november 2026); de partij volgt (V3). Singleton,
        // zodat tests kunnen meelezen met wat de log-driver verstuurde.
        $this->app->singleton(SmsSender::class, fn (): SmsSender => match (config('aucvy.sms.driver')) {
            default => new LogSmsSender,
        });

        // Identiteit via de bank (iDIN, V7); 'manual' komt hier nooit langs.
        $this->app->bind(IdentityProvider::class, fn (): IdentityProvider => match (config('aucvy.identity.provider')) {
            default => new FakeIdentityProvider,
        });

        // Ivy als gesprekspartner (Ivy-briefing IVY-003): chat met tools; de nepversie draait zonder sleutel.
        $this->app->bind(ChatProvider::class, fn (): ChatProvider => match (config('aucvy.ivy.chat_provider')) {
            'anthropic' => new AnthropicChatProvider,
            default => new FakeChatProvider,
        });

        // Live veilen (briefing nieuwe functies): LiveKit Cloud, later zelf gehost; zonder sleutels de nepversie.
        $this->app->singleton(LiveProvider::class, fn (): LiveProvider => match (config('aucvy.live.provider')) {
            'livekit' => new LiveKitProvider(
                (string) config('aucvy.live.livekit.host'),
                (string) config('aucvy.live.livekit.api_key'),
                (string) config('aucvy.live.livekit.api_secret'),
                config('aucvy.live.livekit.ws_url'),
            ),
            default => new FakeLiveProvider,
        });
    }

    public function boot(): void
    {
        $this->configureEloquent();
        $this->configureDates();
        $this->configureRateLimiting();
        $this->configureAuthMails();
        $this->configurePasswords();

        // "Een medewerker beoordeelt - geen algoritme" (hfst. 9). Dit is die
        // medewerker.
        Gate::define('staff', fn (User $user): bool => $user->is_staff);

        // Rechten per onderdeel van het control center (briefing nieuwe
        // functies): can:admin-area,finance of can:admin-area,finance,write.
        Gate::define('admin-area', fn (User $user, string $area, ?string $mode = null): bool => StaffRole::of($user)?->can($area, $mode === 'write') ?? false);
    }

    /**
     * De standaardmails van het framework, in de taal van het platform. De
     * teksten volgen dezelfde toon als de rest: zeggen wat er gebeurt, niets
     * beloven wat er niet is.
     */
    private function configureAuthMails(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Bevestig je e-mailadres')
            ->greeting('Welkom bij Aucvy')
            ->line('Bevestig je e-mailadres om verder te gaan. Dit is de eerste stap van je verificatie - nodig vóór je eerste bod, niet vóór het rondkijken.')
            ->action('E-mailadres bevestigen', $url)
            ->line('Heb jij dit account niet aangemaakt? Dan kun je deze mail negeren.')
            ->salutation('Aucvy'));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Nieuw wachtwoord instellen')
            ->greeting('Wachtwoord vergeten?')
            ->line('Klik op de knop om een nieuw wachtwoord in te stellen. De link is 60 minuten geldig.')
            ->action('Nieuw wachtwoord instellen', url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)))
            ->line('Vroeg jij dit niet aan? Dan verandert er niets en kun je deze mail negeren.')
            ->salutation('Aucvy'));
    }

    /**
     * Maximaal tien biedpogingen per gebruiker per veiling per tien seconden.
     *
     * Bewust per gebruiker én per veiling, niet per IP: een heel kantoor achter
     * één verbinding zou anders elkaar uitsluiten op precies het moment dat een
     * veiling sluit. En bewust per veiling, want meebieden op drie kavels
     * tegelijk is normaal gedrag (hfst. 6).
     */
    /**
     * Acht tekens is geen wachtwoord voor een account dat geld ontvangt. In
     * productie minstens tien met letters en cijfers; lokaal en in tests
     * blijft de korte regel, anders wordt elke fixture een wachtwoordles.
     */
    private function configurePasswords(): void
    {
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()
            : Password::min(8));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('bidding', function (Request $request): Limit {
            $auction = $request->route('auction');
            $auctionId = $auction instanceof Auction ? $auction->id : (string) $auction;

            return Limit::perSecond(
                maxAttempts: (int) config('aucvy.auction.rate_limit.bids_per_user_per_auction'),
                decaySeconds: (int) config('aucvy.auction.rate_limit.decay_seconds'),
            )->by('bidding|'.$request->user()?->id.'|'.$auctionId);
        });
    }

    /**
     * Buiten productie faalt Eloquent luid in plaats van stil.
     *
     * Concreet levert dit drie dingen op:
     *
     *  - een `update()` met een niet-fillable veld gooit een fout in plaats van
     *    het veld weg te gooien. Dat raakt precies de tellers op `auctions`
     *    (extension_count, bid_count, last_sequence) die alleen de biedmotor
     *    mag schrijven;
     *  - een relatie die niet geladen was gooit een fout in plaats van stilletjes
     *    een query per rij af te vuren. Op een lijst met 40 kavels scheelt dat
     *    het verschil tussen 3 en 120 queries;
     *  - een typefout in een kolomnaam levert een fout op in plaats van null.
     *
     * Op productie staat dit uit: daar is een trage pagina beter dan een witte.
     */
    private function configureEloquent(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();
    }

    /**
     * CarbonImmutable in plaats van Carbon.
     *
     * Een veiling rekent voortdurend met eindtijden. Met de muteerbare variant
     * past `$auction->ends_at->addSeconds(60)` de datum zelf aan in plaats van
     * een nieuwe terug te geven - een klassieke bron van verschoven tijden.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
