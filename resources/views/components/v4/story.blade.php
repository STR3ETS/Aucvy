@props([
    'id',
    'kicker',
    'title',
    'sub',
    // Per stap: icon, title, text en panel (label, rows, footer).
    'steps',
])

{{--
    Een verhaal uit preview v4: de stappen links, het paneel rechts dat per
    stap laat zien wat Ivy op dat moment doet. De stappen spelen vanzelf af
    (v4Story in v4.js); een klik op een stap pauzeert en toont die stap.

    De rijen in het paneel: `done` geeft een vinkje, `v` een waarde rechts
    (met `tone` ok of warn), `bar` een balkje eronder, `quote` een citaat.
--}}
<section id="{{ $id }}" class="v4-section" aria-labelledby="{{ $id }}-title" x-data="v4Story({ steps: @js($steps) })">
    <div class="v4-head-row v4-head-row--tight">
        <div class="v4-head">
            <span class="v4-kicker">{{ $kicker }}</span>
            <h2 class="v4-h2" id="{{ $id }}-title">{{ $title }}</h2>
            <p class="v4-sub" style="margin-top:10px;font-size:15.5px;max-width:none">{{ $sub }}</p>
        </div>
        <button type="button" class="v4-play" :aria-label="playing ? 'Pauzeer de doorloop' : 'Speel de doorloop af'" @click="toggle()" x-show="! reduce">
            <x-v4.icon name="pause" :size="13" stroke="2.6" x-show="playing" />
            <x-v4.icon name="play" :size="13" stroke="2.6" x-show="! playing" />
            <span x-text="playing ? 'Pauze' : 'Speel af'"></span>
        </button>
    </div>

    <div class="v4-story">
        <ol>
            @foreach ($steps as $index => $step)
                <li>
                    <button type="button" class="v4-story-step" :class="{ 'is-on': scene === {{ $index }} }" :aria-current="scene === {{ $index }} ? 'step' : null" @click="go({{ $index }})">
                        <span class="track" aria-hidden="true" x-show="scene === {{ $index }}"><i :style="`height:${p * 100}%`"></i></span>
                        <span class="glyph"><x-v4.icon :name="$step['icon']" :size="15" stroke="2.3" /></span>
                        <span style="min-width:0;flex:1">
                            <span class="t">{{ $index + 1 }}. {{ $step['title'] }}</span>
                            <span class="d" x-show="scene === {{ $index }}" x-transition.opacity.duration.300ms>{{ $step['text'] }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>

        <div class="v4-story-panel">
            <div class="v4-panel">
                <div class="v4-glow v4-glow--grad" aria-hidden="true" style="top:-120px;right:-80px;width:320px;height:320px;filter:blur(70px);opacity:.28"></div>
                <div class="v4-panel-head">
                    <span class="label">Stap <span x-text="scene + 1"></span> · <span x-text="step.panel.label"></span></span>
                    <span class="count"><span x-text="scene + 1"></span>/<span x-text="total"></span></span>
                </div>
                <div class="v4-panel-track" aria-hidden="true"><i :style="`width:${p * 100}%`"></i></div>
                <div class="v4-panel-rows">
                    <template x-for="(row, index) in step.panel.rows" :key="scene + '-' + index">
                        <div class="v4-step" :class="{ 'is-on': at(.12 + index * .22), 'v4-panel-row': true, 'v4-panel-row--quote': row.quote }">
                            <span x-text="row.l"></span>
                            <template x-if="row.v"><b :class="row.tone || ''" x-text="row.v"></b></template>
                            <template x-if="row.done"><span class="done" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span></template>
                            <template x-if="row.bar"><span class="bar" aria-hidden="true"><i :style="`width:${row.bar * 100}%`"></i></span></template>
                        </div>
                    </template>
                </div>
                <span class="v4-panel-foot" x-text="step.panel.footer"></span>
            </div>
        </div>
    </div>
</section>
