<x-layouts.admin title="Reviews" :subtitle="$toReview->count().' '.($toReview->count() === 1 ? 'review vraagt' : 'reviews vragen').' een besluit: automatisch tegengehouden of gemeld'">
    @if ($errors->any())
        <div role="alert" class="mt-4 d-soft is-err">{{ $errors->first() }}</div>
    @endif

    {{-- Te beoordelen (Google-principe): tegengehouden door de automatische
         controle, of online met een openstaande melding. Publiceren of
         verwijderen, altijd met spoor. --}}
    <section class="mt-6" aria-labelledby="te-beoordelen">
        <h2 id="te-beoordelen" class="d-h3">Te beoordelen</h2>
        @if ($toReview->isEmpty())
            <p class="mt-3 d-card d-admin-empty">Niets te beoordelen.</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($toReview as $review)
                    <li class="d-card">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm">
                                <span class="tabular font-extrabold">{{ $review->rating }}★</span>
                                <span class="text-aj-muted">· {{ $review->reviewer->name }} over {{ $review->seller->name }}
                                    · <a href="{{ route('admin.orders.show', $review->order) }}" class="d-tablelink">{{ $review->order->lot->title }}</a></span>
                            </p>
                            @if ($review->moderation_status === \App\Models\Review::STATUS_HELD)
                                <span class="rounded-full border border-aj-warn/40 px-2.5 py-1 text-xs font-bold text-aj-warn-text">Tegengehouden: {{ $review->moderation_reason?->label() }}</span>
                            @else
                                <span class="rounded-full border border-aj-border-strong px-2.5 py-1 text-xs font-bold">Online, {{ $review->reports->count() }} {{ $review->reports->count() === 1 ? 'melding' : 'meldingen' }}</span>
                            @endif
                        </div>
                        @if ($review->body)
                            <p class="mt-2 text-sm">"{{ $review->body }}"</p>
                        @endif
                        @foreach ($review->reports as $report)
                            <p class="mt-1 text-xs text-aj-muted">Gemeld door {{ $report->reporter->name }}: {{ $report->reason->label() }}{{ $report->note ? ' - "'.$report->note.'"' : '' }}</p>
                        @endforeach

                        <div class="mt-4 flex flex-wrap items-start gap-3">
                            <form method="POST" action="{{ route('admin.reviews.publish', $review) }}">
                                @csrf
                                <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="height:40px;font-size:13px">{{ $review->moderation_status === \App\Models\Review::STATUS_HELD ? 'Toch publiceren' : 'Laten staan' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reviews.remove', $review) }}" class="flex flex-1 flex-wrap gap-2">
                                @csrf
                                <label class="sr-only" for="remove-reason-{{ $review->id }}">Reden</label>
                                <select id="remove-reason-{{ $review->id }}" name="reason" required class="d-select" style="min-height:40px">
                                    @foreach (\App\Reviews\ReviewReason::cases() as $reason)
                                        <option value="{{ $reason->value }}" @selected($review->moderation_reason === $reason)>{{ ucfirst($reason->label()) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="note" required maxlength="1000" placeholder="Motivering (gaat in het auditlog)" aria-label="Motivering" class="min-w-0 flex-1 d-input" style="min-height:40px">
                                <button type="submit" class="d-btn d-btn-secondary d-btn-sq" style="height:40px;font-size:13px;color:var(--color-aj-error)">Offline halen</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="alle-reviews">
        <h2 id="alle-reviews" class="d-h3">Alle reviews</h2>
        <x-admin.table :items="$reviews">
            <x-slot:filters>
                <select name="status" aria-label="Status" class="d-select" style="min-height:44px">
                    <option value="">Alle statussen</option>
                    <option value="published" @selected(request('status') === 'published')>Online</option>
                    <option value="held" @selected(request('status') === 'held')>Tegengehouden</option>
                    <option value="removed" @selected(request('status') === 'removed')>Offline gehaald</option>
                </select>
            </x-slot:filters>
            <x-slot:head>
                <th>Id</th><th class="text-right">Sterren</th><th>Kavel</th><th>Reviewer</th><th>Verkoper</th><th>Status</th><th>Gepubliceerd</th>
            </x-slot:head>
            @foreach ($reviews as $review)
                <tr>
                    <td class="tabular text-aj-muted">{{ $review->id }}</td>
                    <td class="tabular text-right font-extrabold">{{ $review->rating }}</td>
                    <td class="max-w-[24ch] truncate">
                        <a href="{{ route('admin.orders.show', $review->order) }}" class="d-tablelink">{{ $review->order->lot->title }}</a>
                    </td>
                    <td><a href="{{ route('admin.users.show', $review->reviewer) }}" class="d-tablelink">{{ $review->reviewer->name }}</a></td>
                    <td><a href="{{ route('admin.users.show', $review->seller) }}" class="d-tablelink">{{ $review->seller->name }}</a></td>
                    <td>{{ ['published' => 'Online', 'held' => 'Tegengehouden', 'removed' => 'Offline gehaald'][$review->moderation_status] ?? $review->moderation_status }}</td>
                    <td class="whitespace-nowrap text-aj-muted">{{ $review->published_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nog niet' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </section>
</x-layouts.admin>
