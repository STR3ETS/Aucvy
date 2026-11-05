{{-- Instellingen, onderdeel gegevens: Naam, biednaam en telefoonnummer. Eigen pagina sinds het ontwerp van november. --}}
<section id="gegevens" class="d-card" aria-labelledby="profiel">
    <h2 id="profiel" class="d-h3" style="font-size:17px">Persoonlijke gegevens</h2>
    <p class="d-sub">Je e-mailadres is {{ $user->email }}. Dat wijzigen gaat via de klantenservice, omdat het je inlognaam en je bevestiging is.</p>
    <form method="POST" action="{{ route('account.profile') }}" class="d-stack-tight" style="margin-top:12px">
        @csrf
        @method('PATCH')
        <label class="d-field">Naam
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name" class="d-input">
        </label>
        <label class="d-field">Biednaam
            <input type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}" maxlength="40" placeholder="Bieder {{ $user->bidderNumber() }}" class="d-input">
            <span class="d-hint">Zo zien verkopers en andere bieders je. Leeg is "Bieder {{ $user->bidderNumber() }}". Nooit je echte naam, mailadres of telefoonnummer.</span>
        </label>
        <label class="d-field">Telefoonnummer
            <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="+31 6 12345678" class="d-input">
            <span class="d-hint">Een nieuw nummer vraagt om een nieuwe bevestiging bij je verificatie.</span>
        </label>
        <button type="submit" class="d-btn d-btn-sq" style="align-self:flex-start">Opslaan</button>
    </form>
</section>
