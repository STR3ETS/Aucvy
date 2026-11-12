{{--
    Eén stap van Ivy in de analyse (stap 3): wat nog komt is grijs, wat loopt
    zegt wat Ivy nu doet, wat klaar is toont de uitkomst met het
    zekerheidslabel, nooit een kale modelbelofte (hfst. 8). Staat binnen een
    Alpine-lus met `item`; `product` is de JS-uitdrukking van het product
    waar de stap bij hoort, of null voor wat het hele concept geldt.
--}}
<li :class="['is-' + item.status, item.task === 'group_photos' && ivy.proposal && 'is-asking']">
    <img :src="'/images/agents/' + ivyAgent(item.task) + '.jpg'" alt="" loading="lazy">
    <div class="body">
        <strong x-text="ivyLabel(item.task)"></strong>
        <span class="now" x-show="item.status === 'busy'">
            <span x-text="ivyActivity(item, {{ $product ?? 'null' }}).text + '…'"></span>
            <span class="secs tabular" x-text="ivyActivity(item, {{ $product ?? 'null' }}).seconds + ' s'"></span>
        </span>
        <span class="sk fn-sk-line" x-show="item.status === 'waiting' && ivy.running"></span>
        <span class="result d-muted" x-show="item.status === 'waiting' && ! ivy.running">Wacht op je keuze</span>
        <span class="result" x-show="['done', 'failed', 'skipped'].includes(item.status)" x-text="ivyResult(item, {{ \Illuminate\Support\Js::from($conditionLabels) }}, {{ $product ?? 'null' }})"></span>
        <span class="hint" x-show="item.status === 'done' && item.run?.confidence_hint && item.task !== 'group_photos'" x-text="item.run?.confidence_hint"></span>

        {{-- Foto's van verschillende producten: Ivy stelt hier, in haar eigen stap, voor om er losse kavels van te maken. De verkoper kiest. --}}
        <div class="fn-split" x-show="item.task === 'group_photos' && ivy.proposal" x-cloak>
            <ul class="fn-split-groups">
                <template x-for="(group, index) in ivy.proposal ?? []" :key="index">
                    <li>
                        <span class="thumbs">
                            <template x-for="photo in groupPhotos(group).slice(0, 3)" :key="photo.id"><img :src="photo.url" alt=""></template>
                        </span>
                        <strong x-text="group.label"></strong>
                        <span class="d-muted" x-text="group.media.length === 1 ? '1 foto' : group.media.length + ' foto\'s'"></span>
                    </li>
                </template>
            </ul>
            <p class="fn-split-lead">Maak van elk product een eigen kavel, met een eigen advertentie en waarde-indicatie. Ivy gaat ze daarna een voor een af. Bundelen tot één veiling doe je bij Veilingen.</p>
            <div class="d-chips">
                <button type="button" class="d-btn d-btn-sq" @click="split()" x-text="'Maak er ' + (ivy.proposal?.length ?? 0) + ' kavels van'">Maak er losse kavels van</button>
                {{-- Meer dan twintig foto's past niet in één kavel. --}}
                <button type="button" class="d-btn d-btn-secondary d-btn-sq" @click="keepTogether()" x-show="(draft?.media?.length ?? 0) <= {{ $lotPhotoMax }}">Houd het één kavel</button>
            </div>
        </div>
    </div>
    <span class="state" aria-hidden="true">
        <span class="spin" x-show="item.status === 'busy'"></span>
        <span x-show="item.status === 'done'"><x-v4.icon name="check" :size="14" stroke="3" /></span>
        <span x-show="item.status === 'failed'">!</span>
    </span>
</li>
