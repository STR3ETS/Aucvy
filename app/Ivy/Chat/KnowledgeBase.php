<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Models\KbArticle;
use App\Models\KbGap;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\Support\Str;

/**
 * De kennisbank van Ivy: vaste teksten met bron en versie, gevuld uit de
 * instellingen en de stand van 2 november. Oudere demo's met afwijkende
 * tarieven tellen niet. Een onbekende vraag komt geanonimiseerd in kb_gaps.
 */
final class KnowledgeBase
{
    public const string PITCH_VERSION = 'v1';

    public static function seed(): void
    {
        foreach (self::articles() as $slug => $article) {
            KbArticle::query()->firstOrCreate(['slug' => $slug], $article + ['status' => KbArticle::PUBLISHED]);
        }
    }

    /**
     * @return array<string, array{title: string, audience: string, body: string, source_url: string|null}>
     */
    public static function articles(): array
    {
        return [
            'pitch' => ['title' => 'Wat is Aucvy (pitch)', 'audience' => 'visitor', 'source_url' => null, 'body' => 'Aucvy is een online veilingplatform waar je zakelijke spullen zelf verkoopt, zonder veilinghuis. Jij maakt foto\'s, ik maak er kavels van met tekst en prijsadvies. Plaatsen is gratis. Je betaalt alleen als het verkocht wordt. Kopers betalen geen opgeld en zien alle kosten voordat ze bieden. Heb je iets te verkopen, of zoek je iets?'],
            'pitch-verkoper' => ['title' => 'Pitch voor verkopers', 'audience' => 'visitor', 'source_url' => null, 'body' => 'Je maakt foto\'s van wat je kwijt wilt. Ik herken het, schrijf de advertentie en geef een prijsadvies; jij keurt goed. Plaatsen is gratis, je betaalt alleen een succesfee als het verkocht is: {fee_tiers}. Het geld van de koper gaat via Aucvy en wordt na levering aan je uitbetaald. Wat wil je verkopen?'],
            'pitch-koper' => ['title' => 'Pitch voor kopers', 'audience' => 'visitor', 'source_url' => null, 'body' => 'Op Aucvy koop je zakelijke goederen van geverifieerde verkopers. Geen opgeld; je betaalt je bod, {admin_fee} administratiekosten per bestelling, btw waar die geldt en transport als je dat kiest. Alle kosten staan vóór het bieden vast en je betaling blijft bij de betaalpartij tot je hebt ontvangen. Wat zoek je?'],
            'pitch-groot' => ['title' => 'Pitch voor grote partijen', 'audience' => 'visitor', 'source_url' => null, 'body' => 'Een compleet bedrijf, een faillissement of een curator: dan maakt het team een plan met je. Ik leg je gegevens klaar en een medewerker neemt contact op. Zal ik je naam en e-mailadres noteren?'],
            'kosten-verkoper' => ['title' => 'Wat kost verkopen', 'audience' => 'all', 'source_url' => null, 'body' => 'Plaatsen is gratis. Bij verkoop betaal je een succesfee over het verkoopbedrag, als staffel op je omzet van de laatste 12 maanden: {fee_tiers}. Het tarief staat vast op het moment van verkoop. Btw over de fee volgens de factuur.'],
            'kosten-koper' => ['title' => 'Wat kost kopen', 'audience' => 'all', 'source_url' => null, 'body' => 'Geen opgeld. Je betaalt je bod, {admin_fee} administratiekosten (excl. btw) per bestelling, btw waar die geldt (bij de margeregeling niet) en transport als je dat kiest. Alles staat vóór het bieden in de kostenopbouw.'],
            'bieden-drempels' => ['title' => 'Wanneer mag je bieden', 'audience' => 'all', 'source_url' => null, 'body' => 'Bieden tot {bid_limit} kan met een bevestigd e-mailadres en telefoonnummer. Daarboven is je identiteit nodig. {deposits}. De borg krijg je op verzoek terug als er geen open aankopen zijn; betaal je een gewonnen kavel niet, dan wordt hij ingehouden. Elk bod is bindend.'],
            'reserve' => ['title' => 'De reserveprijs', 'audience' => 'all', 'source_url' => null, 'body' => 'Een verkoper kan een reserveprijs zetten. Kopers zien alleen óf de reserve is gehaald, nooit hoe hoog hij is. Wordt de reserve niet gehaald, dan wordt de kavel niet verkocht.'],
            'betalen-levering' => ['title' => 'Betalen, leveren en termijnen', 'audience' => 'all', 'source_url' => null, 'body' => 'Na winst betaal je binnen {payment_hours} uur via de betaalpartij. Het geld blijft daar tot je hebt ontvangen. Na ontvangst heb je {dispute_hours} uur om een probleem te melden; zonder melding wordt de verkoper uitbetaald. Zonder bevestiging geldt de kavel {auto_deliver_days} dagen na levering als geleverd.'],
            'verkopen-stappen' => ['title' => 'Zo verkoop je', 'audience' => 'all', 'source_url' => null, 'body' => 'Een kavel plaatsen kan alleen als geverifieerd bedrijf; particulieren kunnen wel kopen. Stappen: foto\'s maken, ik herken het product en schrijf de tekst, jij kiest veiling, afslag of koop direct, de toets controleert wat ontbreekt, en dan live. Transport en documenten regel je in de wizard.'],
            'veiligheid' => ['title' => 'Zekerheid bij Aucvy', 'audience' => 'all', 'source_url' => null, 'body' => 'Verkopers zijn geverifieerd op KvK en rekening. De betaling loopt via de betaalpartij en wordt pas na ontvangst uitbetaald. Bij een probleem reageert de verkoper binnen {seller_response_hours} uur en bemiddelt een medewerker als jullie er niet uitkomen. Berichten tussen koper en verkoper lopen via het platform.'],
            'ivy-niveaus' => ['title' => 'Hoeveel mag Ivy doen', 'audience' => 'all', 'source_url' => null, 'body' => 'Per kavel kies je: alleen melden (standaard), eerst vragen (ik zet een voorstel klaar en jij zegt ja), of automatisch tot jouw limiet (via het bestaande autobod, boven je limiet bied ik nooit). Alles blijft werken zonder mij; je kunt zelf bieden en meldingen uitzetten.'],
            'privacy-ai' => ['title' => 'Ivy en je gegevens', 'audience' => 'all', 'source_url' => null, 'body' => 'Ik ben een AI-assistent. Gesprekken worden bewaard om je te helpen; zie de privacyverklaring. Ik zie alleen wat van jou is en nooit gegevens van andere klanten. Juridische of fiscale vragen geef ik door aan een medewerker.'],
        ];
    }

    /**
     * Zoekt het best passende artikel; null als niets past genoeg.
     *
     * @return array{slug: string, title: string, answer: string, version: int, source: string}|null
     */
    public function search(string $query, string $role): ?array
    {
        $stopwords = ['kunnen', 'jullie', 'ook', 'mijn', 'een', 'het', 'van', 'bij', 'wat', 'hoe', 'kan', 'als', 'dat', 'die', 'dit', 'voor', 'met', 'naar', 'over', 'zijn', 'hebben', 'heb', 'wordt', 'worden', 'nog', 'wel', 'niet', 'moet', 'mag', 'wil', 'wie', 'waar', 'wanneer', 'welke', 'hier', 'daar', 'dan', 'maar', 'want', 'dus'];
        $terms = array_values(array_filter(preg_split('/\W+/u', Str::lower($query)) ?: [], fn ($term) => mb_strlen($term) > 2 && ! in_array($term, $stopwords, true)));

        if ($terms === []) {
            return null;
        }

        if (! KbArticle::query()->exists()) {
            self::seed();
        }

        $synonyms = ['kosten' => ['fee', 'prijs', 'tarief', 'betalen', 'opgeld'], 'veilig' => ['zekerheid', 'vertrouwen', 'fraude', 'oplichting'], 'wat' => [], 'reserve' => ['reserveprijs', 'minimum'], 'borg' => ['bieden', 'identiteit', 'drempel'], 'pitch' => ['aucvy', 'doen', 'jullie', 'wat is']];
        $best = null;
        $bestScore = 0;

        foreach (KbArticle::query()->where('status', KbArticle::PUBLISHED)->whereIn('audience', ['all', $role])->get() as $article) {
            $haystack = Str::lower($article->title.' '.$article->body.' '.$article->slug);
            $score = 0;
            $matched = 0;

            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $score += 2;
                    $matched++;
                }

                foreach ($synonyms[$term] ?? [] as $synonym) {
                    if (str_contains($haystack, $synonym)) {
                        $score += 1;
                    }
                }
            }

            // Minstens één echt woord uit de vraag moet in het artikel staan; één los synoniem is te mager.
            if ($matched > 0 && $score > $bestScore) {
                $bestScore = $score;
                $best = $article;
            }
        }

        if ($best === null || $bestScore < 2) {
            KbGap::record($query, $role);

            return null;
        }

        return ['slug' => $best->slug, 'title' => $best->title, 'answer' => self::render($best->body), 'version' => $best->current_version, 'source' => route('faq').'#'.$best->slug];
    }

    public function pitch(string $variant = 'pitch'): string
    {
        $article = KbArticle::query()->where('slug', in_array($variant, ['pitch', 'pitch-verkoper', 'pitch-koper', 'pitch-groot'], true) ? $variant : 'pitch')->first();

        return self::render($article?->body ?? self::articles()['pitch']['body']);
    }

    /**
     * De bedragen komen bij het renderen uit de instellingen.
     */
    public static function render(string $body): string
    {
        $facts = PlatformFacts::all();

        return strtr($body, [
            '{fee_tiers}' => collect($facts['fee_tiers'])->map(fn (array $tier) => ($tier['up_to_cents'] === null ? 'daarboven' : 'tot '.Money::format((int) $tier['up_to_cents'])).' '.rtrim(rtrim(number_format((float) $tier['percent'], 1, ',', '.'), '0'), ',').'%')->implode(', '),
            '{admin_fee}' => Money::format((int) $facts['admin_fee_cents']),
            '{bid_limit}' => Money::format((int) $facts['bid_free_limit_cents']),
            '{deposits}' => collect($facts['deposit_tiers'])->map(fn (array $tier) => 'Boven '.Money::format((int) $tier['above_cents']).' is een borg van '.Money::format((int) $tier['deposit_cents']).' nodig')->implode('; '),
            '{payment_hours}' => (string) $facts['payment_deadline_hours'],
            '{dispute_hours}' => (string) $facts['dispute_window_hours'],
            '{auto_deliver_days}' => (string) $facts['auto_deliver_after_days'],
            '{seller_response_hours}' => (string) $facts['seller_response_hours'],
        ]);
    }
}
