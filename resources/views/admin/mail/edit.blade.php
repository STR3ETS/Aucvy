<x-layouts.admin :title="$template->number.'. '.$template->name" :subtitle="$template->streamLabel().' · '.$template->trigger.($template->notification ? ' · verstuurd door '.$template->notification : '')">
    <p class="text-sm text-aj-muted"><a href="{{ route('admin.mail') }}" class="underline">Mail Builder</a> · {{ $template->key }}</p>

    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_420px] lg:items-start"
         x-data="mailEditor({{ Js::from($version?->blocks ?? []) }}, {{ Js::from(route('admin.mail.preview', $template)) }})">
        <form method="POST" action="{{ route('admin.mail.update', $template) }}" class="space-y-4" @submit="serialise()">
            @csrf
            @method('PUT')
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5 text-sm">
                <div class="grid gap-3 sm:grid-cols-[1fr_180px]">
                    <label class="grid gap-1"><span class="font-bold">Onderwerp</span><input type="text" name="subject" x-model="subject" required maxlength="200" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px"></label>
                    <label class="grid gap-1"><span class="font-bold">Afzender</span><select name="sender" x-model="sender" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px"><option value="ivy">Bericht van Ivy</option><option value="aucvy">Aucvy</option></select></label>
                </div>
                <label class="mt-3 grid gap-1"><span class="font-bold">Preheader</span><input type="text" name="preheader" x-model="preheader" maxlength="200" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px"></label>
                <p class="mt-2 text-xs text-aj-muted">Velden: <code>@{{voornaam}}</code>, <code>@{{biednaam}}</code>, <code>@{{kavel.titel}}</code>, <code>@{{kavel.bod}}</code>, <code>@{{kavel.sluit}}</code>, <code>@{{kavel.link}}</code>, <code>@{{bestelling.totaal}}</code>, <code>@{{bedrag}}</code>, <code>@{{deadline}}</code>, <code>@{{feiten.biedgrens}}</code>, <code>@{{links.veilingen}}</code>. Kavels en bod worden bij het versturen opgehaald.</p>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5 text-sm" aria-labelledby="blokken">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="blokken" class="text-sm font-extrabold">Blokken</h2>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($blockTypes as $type)
                            <button type="button" class="rounded-full border border-aj-border px-2 py-0.5 text-xs font-bold hover:bg-aj-bg" @click="add('{{ $type }}')">+ {{ __('mailbuilder.blocks.'.$type) }}</button>
                        @endforeach
                    </div>
                </div>
                <ul class="mt-3 space-y-2">
                    <template x-for="(block, index) in blocks" :key="block._id">
                        <li class="rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-extrabold uppercase tracking-wide text-aj-muted" x-text="labels[block.type] ?? block.type"></span>
                                <span class="flex gap-2 text-xs">
                                    <button type="button" class="underline" @click="move(index, -1)" :disabled="index === 0">Omhoog</button>
                                    <button type="button" class="underline" @click="move(index, 1)" :disabled="index === blocks.length - 1">Omlaag</button>
                                    <button type="button" class="text-aj-error underline" @click="remove(index)">Weg</button>
                                </span>
                            </div>
                            <div class="mt-2 grid gap-2">
                                <template x-if="['status', 'ivy_tip'].includes(block.type)"><input type="text" x-model="block.title" placeholder="Titel" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"></template>
                                <template x-if="['heading', 'text', 'status', 'ivy_tip', 'footnote'].includes(block.type)"><textarea x-model="block.text" rows="2" placeholder="Tekst" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-2"></textarea></template>
                                <template x-if="block.type === 'button'"><div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"><input type="text" x-model="block.label" placeholder="Knoptekst" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"><input type="text" x-model="block.url" placeholder="Link of veld, bijv. @{{kavel.link}}" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"><label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="block.secondary"> Tweede knop</label></div></template>
                                <template x-if="block.type === 'amount'"><div class="grid gap-2 sm:grid-cols-3"><input type="text" x-model="block.label" placeholder="Label" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"><input type="text" x-model="block.value" placeholder="Bedrag of veld" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"><input type="text" x-model="block.note" placeholder="Toelichting" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"></div></template>
                                <template x-if="block.type === 'code'"><input type="text" x-model="block.value" placeholder="@{{code}}" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"></template>
                                <template x-if="block.type === 'image'"><div class="grid gap-2 sm:grid-cols-2"><input type="url" x-model="block.url" placeholder="Afbeeldings-URL" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"><input type="text" x-model="block.alt" placeholder="Alt-tekst" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:34px"></div></template>
                                <template x-if="block.type === 'lots'"><div class="flex flex-wrap items-center gap-2 text-xs"><label>Bron <select x-model="block.source" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:30px"><option value="context">Kavel(s) van de mail</option><option value="latest">Nieuwste live kavels</option></select></label><label>Aantal <input type="number" min="1" max="6" x-model.number="block.limit" class="w-16 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:30px"></label></div></template>
                                <template x-if="block.type === 'lines'"><div class="space-y-1"><template x-for="(item, i) in block.items" :key="i"><div class="flex gap-1"><input type="text" x-model="item.label" placeholder="Label" class="flex-1 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:30px"><input type="text" x-model="item.value" placeholder="Waarde" class="flex-1 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:30px"><button type="button" class="text-xs underline" @click="block.items.splice(i, 1)">weg</button></div></template><button type="button" class="text-xs font-bold underline" @click="block.items.push({label: '', value: ''})">+ regel</button></div></template>
                                <template x-if="block.type === 'steps'"><div class="space-y-1"><template x-for="(item, i) in block.items" :key="i"><div class="flex gap-1"><input type="text" x-model="block.items[i]" placeholder="Stap" class="flex-1 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2" style="min-height:30px"><button type="button" class="text-xs underline" @click="block.items.splice(i, 1)">weg</button></div></template><button type="button" class="text-xs font-bold underline" @click="block.items.push('')">+ stap</button></div></template>
                            </div>
                        </li>
                    </template>
                </ul>
                <input type="hidden" name="blocks" x-ref="blocks">
            </section>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Opslaan als nieuwe versie</button>
                <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="refresh()">Preview verversen</button>
            </div>
        </form>

        <aside class="space-y-4">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-4" aria-labelledby="preview">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="preview" class="text-sm font-extrabold">Preview met voorbeelddata</h2>
                    <span class="flex gap-1 text-xs"><button type="button" class="rounded-full px-2 py-0.5 font-bold" :class="mobile ? 'border border-aj-border' : 'bg-aj-navy text-white'" @click="mobile = false">Desktop</button><button type="button" class="rounded-full px-2 py-0.5 font-bold" :class="mobile ? 'bg-aj-navy text-white' : 'border border-aj-border'" @click="mobile = true">Mobiel</button></span>
                </div>
                <div class="mt-3 overflow-hidden rounded-aj-sm border border-aj-border bg-aj-bg" :style="mobile ? 'width:390px;max-width:100%' : ''">
                    <iframe title="Preview" :src="previewUrl" class="block w-full" style="height:640px;border:0;background:#f3f4f8"></iframe>
                </div>
                <form method="POST" action="{{ route('admin.mail.test', $template) }}" class="mt-3">@csrf<button type="submit" class="btn btn-ghost w-full justify-center" style="height:36px;font-size:12px">Stuur testmail naar {{ auth()->user()->email }}</button></form>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-4" aria-labelledby="versies">
                <h2 id="versies" class="text-sm font-extrabold">Versies</h2>
                <ul class="mt-2 divide-y divide-aj-border text-xs">
                    @foreach ($template->versions as $other)
                        <li class="flex items-center justify-between gap-2 py-1.5">
                            <span>v{{ $other->version }} · {{ $other->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}{{ $other->author ? ' · '.$other->author->name : ' · catalogus' }}</span>
                            @if ($other->id === $template->current_version_id)
                                <span class="font-bold text-aj-success-text">actief</span>
                            @else
                                <form method="POST" action="{{ route('admin.mail.restore', [$template, $other]) }}">@csrf<button type="submit" class="font-semibold underline">Terugzetten</button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-[11px] text-aj-muted">Een verstuurde mail houdt de versie waarmee hij is verstuurd.</p>
            </section>
        </aside>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mailEditor', (initial, previewBase) => ({
                blocks: initial.map((block) => ({ _id: Math.random().toString(36).slice(2), items: [], ...block })),
                subject: {{ Js::from($version?->subject ?? '') }},
                preheader: {{ Js::from($version?->preheader ?? '') }},
                sender: {{ Js::from($version?->sender ?? 'aucvy') }},
                mobile: false,
                previewUrl: previewBase,
                labels: {{ Js::from(collect($blockTypes)->mapWithKeys(fn ($type) => [$type => __('mailbuilder.blocks.'.$type)])) }},
                add(type) {
                    const base = { _id: Math.random().toString(36).slice(2), type, items: [] };
                    if (type === 'button') Object.assign(base, { label: 'Bekijk', url: '@{{link}}', secondary: false });
                    if (type === 'lots') Object.assign(base, { source: 'context', limit: 1 });
                    if (type === 'lines') base.items = [{ label: '', value: '' }];
                    if (type === 'steps') base.items = [''];
                    this.blocks.push(base);
                },
                remove(index) { this.blocks.splice(index, 1); },
                move(index, delta) {
                    const target = index + delta;
                    if (target < 0 || target >= this.blocks.length) return;
                    const [block] = this.blocks.splice(index, 1);
                    this.blocks.splice(target, 0, block);
                },
                clean() { return this.blocks.map(({ _id, ...block }) => block); },
                serialise() { this.$refs.blocks.value = JSON.stringify(this.clean()); },
                refresh() {
                    const params = new URLSearchParams({ subject: this.subject, preheader: this.preheader, sender: this.sender, blocks: JSON.stringify(this.clean()), t: Date.now() });
                    this.previewUrl = previewBase + '?' + params.toString();
                },
            }));
        });
    </script>
</x-layouts.admin>
