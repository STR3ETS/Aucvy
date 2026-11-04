<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Distribution\Channel;
use App\Models\ChannelConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Kanalen koppelen (/account/koppelingen): per kanaal de status en
 * "Koppelen" of "Opnieuw koppelen". De OAuth-stappen staan klaar; zonder
 * app-sleutels van Meta of LinkedIn toont de pagina "Koppeling volgt" en
 * blijft de deelkit het kanaal dekken.
 */
final class ChannelConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $connections = ChannelConnection::query()->where('user_id', $request->user()->id)->get()->keyBy('channel');

        return view('account.connections', [
            'channels' => collect(Channel::CONNECTABLE)->map(fn (Channel $channel) => [
                'channel' => $channel,
                'connection' => $connections->get($channel->value),
                'available' => $this->isConfigured($channel),
            ]),
        ]);
    }

    public function connect(Request $request, string $channel): RedirectResponse
    {
        $channel = Channel::tryFrom($channel);

        abort_unless($channel !== null && in_array($channel, Channel::CONNECTABLE, true), 404);

        if (! $this->isConfigured($channel)) {
            return redirect()->route('account.connections')->withErrors(['channel' => $channel->label().' is nog niet te koppelen: de app-goedkeuring loopt. Gebruik tot die tijd de deelkit.']);
        }

        $state = Str::random(32);
        $request->session()->put('channel_oauth_state', [$channel->value, $state]);

        ChannelConnection::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'channel' => $channel->value],
            ['company_id' => $request->user()->company_id, 'status' => ChannelConnection::PENDING, 'last_error' => null],
        );

        $settings = (array) config('services.'.$channel->value);

        return redirect()->away($settings['authorize_url'].'?'.http_build_query([
            'client_id' => $settings['client_id'],
            'redirect_uri' => route('account.connections.callback', $channel->value),
            'scope' => $settings['scope'] ?? '',
            'response_type' => 'code',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, string $channel): RedirectResponse
    {
        $channel = Channel::tryFrom($channel);
        [$expectedChannel, $expectedState] = (array) $request->session()->pull('channel_oauth_state', [null, null]);

        abort_unless($channel !== null && $expectedChannel === $channel->value && hash_equals((string) $expectedState, (string) $request->query('state')), 403);

        $connection = ChannelConnection::query()->firstOrNew(['user_id' => $request->user()->id, 'channel' => $channel->value]);
        $connection->company_id = $request->user()->company_id;

        if ($request->query('error') || ! $request->query('code')) {
            $connection->forceFill(['status' => ChannelConnection::ERROR, 'last_error' => (string) ($request->query('error_description') ?? $request->query('error') ?? 'Geen code ontvangen.')])->save();

            return redirect()->route('account.connections')->withErrors(['channel' => 'Koppelen is niet gelukt: '.$connection->last_error]);
        }

        $settings = (array) config('services.'.$channel->value);
        $response = Http::timeout(20)->asForm()->post($settings['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => (string) $request->query('code'),
            'redirect_uri' => route('account.connections.callback', $channel->value),
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
        ]);

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $connection->forceFill(['status' => ChannelConnection::ERROR, 'last_error' => (string) ($response->json('error.message') ?? $response->json('error_description') ?? 'Het kanaal gaf geen toegang.')])->save();

            return redirect()->route('account.connections')->withErrors(['channel' => 'Koppelen is niet gelukt: '.$connection->last_error]);
        }

        $tokens = ['access_token' => (string) $response->json('access_token')];
        $expiresIn = (int) ($response->json('expires_in') ?? 0);

        $connection->forceFill([
            'tokens' => $tokens,
            'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'status' => ChannelConnection::CONNECTED,
            'last_error' => null,
        ])->save();

        return redirect()->route('account.connections')->with('status', $channel->label().' is gekoppeld.');
    }

    public function disconnect(Request $request, string $channel): RedirectResponse
    {
        ChannelConnection::query()->where('user_id', $request->user()->id)->where('channel', $channel)->delete();

        return redirect()->route('account.connections')->with('status', 'Koppeling verwijderd.');
    }

    private function isConfigured(Channel $channel): bool
    {
        $settings = (array) config('services.'.$channel->value, []);

        return ! empty($settings['client_id']) && ! empty($settings['client_secret']) && ! empty($settings['authorize_url']) && ! empty($settings['token_url']);
    }
}
