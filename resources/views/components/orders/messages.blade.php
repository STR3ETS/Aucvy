@props(['order', 'messages', 'action', 'viewer', 'staff' => false])

{{--
    De berichtendraad van een bestelling (briefing sectie 7): koper,
    verkoper en support in één draad. Wie wat schreef staat erbij; support
    is herkenbaar aan de eigen kleur.
--}}
<section id="berichten" class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="berichten-kop">
    <h2 id="berichten-kop" class="text-sm font-extrabold text-aj-soft">Berichten</h2>
    <p class="mt-1 text-xs text-aj-muted">
        @if ($staff)
            Alles wat koper en verkoper elkaar schreven; jouw bericht gaat naar allebei, als Aucvy support.
        @else
            Afspraken over ophalen, levering of een vraag: hier blijft het bij de bestelling staan. Support leest mee als dat nodig is.
        @endif
    </p>

    @if ($messages->isEmpty())
        <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-4 text-center text-sm text-aj-muted">Nog geen berichten.</p>
    @else
        <ol class="order-messages mt-4">
            @foreach ($messages as $message)
                @php $role = $message->roleFor($order); $mine = $message->user_id === $viewer->id; @endphp
                <li class="order-message role-{{ $role }}{{ $mine ? ' is-mine' : '' }}">
                    <span class="who">
                        @if ($role === 'support') Aucvy support @elseif ($mine) Jij @else {{ $message->author?->name ?? ($role === 'buyer' ? 'Koper' : 'Verkoper') }} @endif
                        <small>{{ $role === 'buyer' ? 'koper' : ($role === 'seller' ? 'verkoper' : 'support') }} · {{ $message->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</small>
                    </span>
                    <p>{!! nl2br(e($message->body)) !!}</p>
                </li>
            @endforeach
        </ol>
    @endif

    <form method="POST" action="{{ $action }}" class="mt-4 grid gap-2">
        @csrf
        <label class="sr-only" for="bericht-{{ $order->id }}">Bericht</label>
        <textarea id="bericht-{{ $order->id }}" name="body" rows="3" required maxlength="{{ \App\Models\OrderMessage::MAX_LENGTH }}" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm" placeholder="{{ $staff ? 'Bericht van support aan koper en verkoper' : 'Schrijf een bericht' }}">{{ old('body') }}</textarea>
        @error('body')
            <p class="text-xs text-aj-error">{{ $message }}</p>
        @enderror
        <div><button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Versturen</button></div>
    </form>
</section>
