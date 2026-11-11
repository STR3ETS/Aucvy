@use('App\Support\Money\Money')

{{--
    Het dashboard volgens het ontwerp van november (briefing-nieuwste):
    links de inhoud, rechts het team met wat op jou wacht en wat al gedaan
    is. Eén dashboard met twee gezichten: wie gepubliceerde kavels heeft,
    ziet het verkopersoverzicht; met de schakelaar kijk je als koper.
    Alles is gemeten of geteld; de pagina doet geen AI-calls.
--}}
@php $user = auth()->user(); @endphp
<x-layouts.dashboard title="Dashboard" :heading="$greeting.', '.$firstName" :intro="$stats['summary']">
    <x-slot:actions>
        @if ($isSeller)
            <a class="btn btn-white" href="{{ route('sell') }}">Kavel plaatsen</a>
        @else
            <a class="btn btn-white" href="{{ route('lots.index') }}">Bekijk veilingen</a>
        @endif
    </x-slot:actions>

    <x-slot:tiles>
        @foreach ($stats['tiles'] as $index => $tile)
            <x-dashboard.tile :tone="$tile['tone']" :label="$tile['label']" :value="$tile['value']" :sub="$tile['sub']" :href="$tile['href']"
                              :trend="$isSeller && $index === 0 ? $revenueTrend : null" />
        @endforeach
    </x-slot:tiles>

    <div x-data>
        {{-- Als verkoper of als koper: dezelfde pagina, een andere bril. --}}
        <nav class="d-seg dash-page-switch" aria-label="Weergave">
            <a href="{{ route('dashboard', ['rol' => 'verkoper']) }}" class="{{ $isSeller ? 'is-on' : '' }}" @if ($isSeller) aria-current="page" @endif>Als verkoper</a>
            <a href="{{ route('dashboard', ['rol' => 'koper']) }}" class="{{ $isSeller ? '' : 'is-on' }}" @unless ($isSeller) aria-current="page" @endunless>Als koper</a>
        </nav>

        @if ($isNew)
            {{-- Nieuwe gebruikers zien startstappen in plaats van lege blokken. --}}
            <section class="d-card dash-section" aria-label="Aan de slag" style="margin-top:0;margin-bottom:18px">
                <h2 class="d-h2">{{ __('dashboard.start.title') }}</h2>
                <p class="d-sub">{{ __('dashboard.start.intro') }}</p>
                <ol class="mt-4 space-y-3 text-sm">
                    <li class="flex items-start gap-3">
                        <span class="tabular font-extrabold text-aj-blue-text">1</span>
                        <span>{{ __('dashboard.start.step_sell') }}
                            <a href="{{ route('sell') }}" class="font-bold text-aj-blue-text underline">Kavel plaatsen</a></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="tabular font-extrabold text-aj-blue-text">2</span>
                        <span>{{ __('dashboard.start.step_browse') }}
                            <a href="{{ route('lots.index') }}" class="font-bold text-aj-blue-text underline">Naar de veilingen</a></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="tabular font-extrabold text-aj-blue-text">3</span>
                        <span>{{ __('dashboard.start.step_bid') }}</span>
                    </li>
                </ol>
            </section>
        @endif

        <div class="d-cols">
            <div class="d-col-main">
                @if ($isSeller)
                    @include('dashboard.partials.overview-seller')
                @else
                    @include('dashboard.partials.overview-buyer')
                @endif

                @include('dashboard.partials.feed')
            </div>

            @include('dashboard.partials.team-aside')
        </div>

        {{-- De verkoper ziet zijn kavels al bij "Interesse per kavel"; de hele lijst staat op Kavels. --}}
        @unless ($isSeller)
            @include('dashboard.partials.purchases')
        @endunless
    </div>
</x-layouts.dashboard>
