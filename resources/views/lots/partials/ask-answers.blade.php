{{--
    De vragen en antwoorden die de verkoper bij de kavel zette: openbaar,
    zonder naam van de vrager. In de linkerkolom van de kavelpagina.
--}}
@php $answers = $lot->publicAnswers; @endphp

@if ($answers->isNotEmpty())
    <section id="vragen" aria-labelledby="vragen-kop">
        <h2 id="vragen-kop" class="d-h2-page">Vragen en antwoorden</h2>
        <p class="d-sub">Antwoorden van de verkoper die voor alle bieders gelden. De naam van de vrager wordt niet getoond.</p>
        <div class="d-docs" style="margin-top:10px">
            @foreach ($answers as $answer)
                <div class="d-doc" style="display:block">
                    <p style="margin:0;font-size:14px;font-weight:700">{{ $answer->question }}</p>
                    <p class="d-prose" style="margin-top:4px">{{ $answer->answer }}</p>
                    <p class="d-hint">@if ($answer->partLabel())Over: {{ $answer->partLabel() }} · @endif antwoord van de verkoper</p>
                </div>
            @endforeach
        </div>
    </section>
@endif

