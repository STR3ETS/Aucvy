<x-layouts.public title="Contact" eyebrow="Hulp"
    intro="Je krijgt per mail antwoord, meestal binnen één werkdag.">
    <x-slot:headline>Stel je vraag. <span class="g">Een mens leest mee.</span></x-slot:headline>
    <x-slot:actions>
        <x-marketing.hero-ivy
            question="Hoe snel krijg ik antwoord?"
            answer="Meestal binnen één werkdag, van een mens. Gaat het over een levering? Meld dat dan bij de bestelling, dan lopen de termijnen meteen."
            :chips="['Veelgestelde vragen', 'Geschilroute']" />
    </x-slot:actions>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 18px">{{ session('status') }}</p>
    @endif

    <div class="d-site-two">
        <form method="POST" action="{{ route('contact.store') }}" class="d-site-form">
            @csrf

            @if ($errors->any())
                <div role="alert" class="d-soft is-err" style="margin:0">{{ $errors->first() }}</div>
            @endif

            <label class="d-field">Naam
                <input type="text" name="name" value="{{ old('name', $user?->name) }}" required maxlength="120" autocomplete="name" class="d-input">
            </label>
            <label class="d-field">E-mailadres
                <input type="email" name="email" value="{{ old('email', $user?->email) }}" required maxlength="190" autocomplete="email" class="d-input">
            </label>
            <label class="d-field">Onderwerp
                <input type="text" name="subject" value="{{ old('subject') }}" required minlength="3" maxlength="150" class="d-input">
            </label>

            @if ($orders->isNotEmpty())
                <label class="d-field">Gaat het om een bestelling?
                    <select name="order_number" class="d-input">
                        <option value="">Nee, iets anders</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->order_number }}" @selected(old('order_number') === $order->order_number)>{{ $order->order_number }} · {{ $order->lot?->title }}</option>
                        @endforeach
                    </select>
                    <span class="d-hint">Een probleem met een levering meld je bij de bestelling zelf; daar lopen de termijnen.</span>
                </label>
            @else
                <label class="d-field">Bestelnummer (optioneel)
                    <input type="text" name="order_number" value="{{ old('order_number') }}" maxlength="32" placeholder="AUC-000123" class="d-input tabular">
                </label>
            @endif

            <label class="d-field">Je bericht
                <textarea name="message" rows="5" required minlength="20" maxlength="5000" class="d-textarea" style="margin-top:6px">{{ old('message') }}</textarea>
            </label>

            {{-- Honingpot tegen bots: onzichtbaar voor mensen. --}}
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <button type="submit" class="d-btn d-btn-wide d-btn-sq" style="min-height:52px">Versturen</button>
        </form>

        <aside class="d-stack">
            <div class="d-callout-warn">
                <span class="mark" aria-hidden="true">!</span>
                <div class="text">
                    <strong>Probleem met een levering?</strong>
                    <span>Meld dat bij de bestelling via Probleem melden. Dan staat de uitbetaling stil en lopen de termijnen; een mail doet dat niet.</span>
                </div>
            </div>
            @auth
                <div><a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('orders.index') }}">Naar je bestellingen</a></div>
            @endauth

            <div>
                <h2 class="d-site-h2">Eerst even kijken?</h2>
                <p class="d-sub" style="font-size:15px">Misschien staat het antwoord er al.</p>
            </div>
            <nav class="d-linklist" aria-label="Meer hulp">
                <a href="{{ route('faq') }}"><span>Veelgestelde vragen</span><x-v4.icon name="arrow-right" :size="16" stroke="2.2" /></a>
                <a href="{{ route('assurance') }}"><span>Zekerheid en de geschilroute</span><x-v4.icon name="arrow-right" :size="16" stroke="2.2" /></a>
                <a href="{{ route('how-it-works') }}"><span>Hoe het werkt</span><x-v4.icon name="arrow-right" :size="16" stroke="2.2" /></a>
            </nav>
        </aside>
    </div>
</x-layouts.public>
