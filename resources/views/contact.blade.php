<x-layouts.public title="Contact" eyebrow="Hulp" heading="Stel je vraag" align="center"
    intro="Een mens leest mee en antwoordt per mail, meestal binnen één werkdag.">
    <div class="mx-auto max-w-3xl">

        @if (session('status'))
            <p class="mb-5 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-4 text-sm text-aj-success-text">{{ session('status') }}</p>
        @endif

        <div class="grid gap-6 md:grid-cols-[1fr_260px] md:items-start">
            <form method="POST" action="{{ route('contact.store') }}" class="space-y-4 rounded-aj-s border border-aj-border bg-aj-surface p-6">
                @csrf

                @if ($errors->any())
                    <div role="alert" class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-bold">Naam</span>
                        <input type="text" name="name" value="{{ old('name', $user?->name) }}" required maxlength="120" autocomplete="name"
                               class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                    </label>
                    <label class="block">
                        <span class="text-sm font-bold">E-mailadres</span>
                        <input type="email" name="email" value="{{ old('email', $user?->email) }}" required maxlength="190" autocomplete="email"
                               class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                    </label>
                </div>

                <label class="block">
                    <span class="text-sm font-bold">Onderwerp</span>
                    <input type="text" name="subject" value="{{ old('subject') }}" required minlength="3" maxlength="150"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>

                @if ($orders->isNotEmpty())
                    <label class="block">
                        <span class="text-sm font-bold">Gaat het om een bestelling?</span>
                        <select name="order_number" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                            <option value="">Nee, iets anders</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->order_number }}" @selected(old('order_number') === $order->order_number)>{{ $order->order_number }} · {{ $order->lot?->title }}</option>
                            @endforeach
                        </select>
                        <span class="block text-xs text-aj-muted">Een probleem met een levering meld je bij de bestelling zelf; daar lopen de termijnen.</span>
                    </label>
                @else
                    <label class="block">
                        <span class="text-sm font-bold">Bestelnummer (optioneel)</span>
                        <input type="text" name="order_number" value="{{ old('order_number') }}" maxlength="32" placeholder="AUC-000123"
                               class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                    </label>
                @endif

                <label class="block">
                    <span class="text-sm font-bold">Je bericht</span>
                    <textarea name="message" rows="6" required minlength="20" maxlength="5000"
                              class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm">{{ old('message') }}</textarea>
                </label>

                {{-- Honingpot tegen bots: onzichtbaar voor mensen. --}}
                <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <button type="submit" class="btn btn-primary">Versturen</button>
            </form>

            <aside class="space-y-4">
                <div class="rounded-aj-s border border-aj-border bg-aj-surface p-5">
                    <h2 class="text-sm font-extrabold">Eerst even kijken?</h2>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        <li><a href="{{ route('faq') }}" class="font-bold text-aj-blue-text underline">Veelgestelde vragen</a></li>
                        <li><a href="{{ route('assurance') }}" class="font-bold text-aj-blue-text underline">Zekerheid en de geschilroute</a></li>
                        <li><a href="{{ route('how-it-works') }}" class="font-bold text-aj-blue-text underline">Hoe het werkt</a></li>
                    </ul>
                </div>
                <div class="rounded-aj-s border border-aj-border bg-aj-surface p-5 text-sm">
                    <h2 class="text-sm font-extrabold">Probleem met een levering?</h2>
                    <p class="mt-1 text-aj-muted">Meld dat bij de bestelling via "Probleem melden". Dan staat de uitbetaling stil en lopen de termijnen; een mail doet dat niet.</p>
                    @auth
                        <a href="{{ route('orders.index') }}" class="mt-2 inline-block font-bold text-aj-blue-text underline">Naar je bestellingen</a>
                    @endauth
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
