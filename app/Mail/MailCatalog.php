<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailTemplate;
use App\Models\MailTemplateVersion;
use Illuminate\Support\Facades\DB;

/**
 * De 46 mails uit het canvas "Alle mails", in één vast format: donkere kop,
 * één boodschap, bouwstenen, één hoofdknop, vaste voet. Service is niet af
 * te melden; activatie en campagne wel. De indeling is een voorstel dat
 * Don toetst. Bedragen en termijnen komen uit PlatformFacts, nooit uit de
 * tekst zelf.
 */
final class MailCatalog
{
    public const string IVY = 'ivy';

    public const string AUCVY = 'aucvy';

    /**
     * @return list<array{key: string, number: int, group: string, stream: string, name: string, trigger: string, subject: string, preheader: string, sender: string, notification: string|null, blocks: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        $s = MailTemplate::SERVICE;
        $a = MailTemplate::ACTIVATION;
        $c = MailTemplate::CAMPAIGN;
        $h = fn (string $text) => ['type' => 'heading', 'text' => $text];
        $t = fn (string $text) => ['type' => 'text', 'text' => $text];
        $st = fn (string $title, string $text) => ['type' => 'status', 'title' => $title, 'text' => $text];
        $ivy = fn (string $title, string $text) => ['type' => 'ivy_tip', 'title' => $title, 'text' => $text];
        $b = fn (string $label, string $url = '{{link}}', bool $secondary = false) => ['type' => 'button', 'label' => $label, 'url' => $url, 'secondary' => $secondary];
        $lot = fn () => ['type' => 'lots', 'source' => 'context', 'limit' => 1];
        $lots = fn (int $limit = 3) => ['type' => 'lots', 'source' => 'latest', 'limit' => $limit];
        $amount = fn (string $label, string $value, string $note = '') => ['type' => 'amount', 'label' => $label, 'value' => $value, 'note' => $note];
        $lines = fn (array $items) => ['type' => 'lines', 'items' => array_map(fn ($item) => ['label' => $item[0], 'value' => $item[1]], $items)];
        $steps = fn (array $items) => ['type' => 'steps', 'items' => $items];
        $foot = fn (string $text) => ['type' => 'footnote', 'text' => $text];

        $groups = ['account' => 'Account en verificatie', 'buyer' => 'Koper: zoeken en bieden', 'purchase' => 'Koper: betalen en ontvangen', 'seller' => 'Verkoper: plaatsen en interesse', 'sale' => 'Verkoper: betaling en uitbetaling', 'campaign' => 'Activatie en campagnes'];

        $mail = fn (int $number, string $key, string $group, string $stream, string $name, string $trigger, string $subject, string $preheader, string $sender, ?string $notification, array $blocks) => compact('key', 'number', 'stream', 'name', 'trigger', 'subject', 'preheader', 'sender', 'notification', 'blocks') + ['group' => $groups[$group]];

        return [
            $mail(1, 'welkom', 'account', $s, 'Welkom', 'Account aangemaakt', 'Welkom bij Aucvy, ik ben Ivy', 'Ik help je vinden wat je zoekt en houd je biedingen in de gaten.', self::IVY, 'Welcome', [
                $h('Welkom bij Aucvy'), $t('Ik ben Ivy. Ik help je vinden wat je zoekt en houd je biedingen in de gaten.'),
                $steps(['Vertel me wat je zoekt, dan bewaak ik het aanbod.', 'Bevestig je telefoon, dan kun je bieden tot {{feiten.biedgrens}}.', 'Boven {{feiten.biedgrens}} regel je je identiteit, in een paar stappen.']),
                $b('Vertel Ivy wat je zoekt'), $foot('Liever zelf kijken? Bekijk alle veilingen.'),
            ]),
            $mail(2, 'bevestig_email', 'account', $s, 'Bevestig je e-mailadres', 'Direct na registratie', 'Bevestig je e-mailadres', 'Dan staat je account klaar.', self::AUCVY, 'VerifyEmail', [
                $h('Nog één klik'), $t('Bevestig je e-mailadres, dan staat je account klaar.'),
                $st('Waarom dit nodig is', 'Zo weten we dat de meldingen over je biedingen aankomen. Heb je dit account niet aangemaakt? Dan kun je deze mail negeren.'),
                $b('Bevestig je e-mailadres'), $foot('Deze link werkt 24 uur.'),
            ]),
            $mail(3, 'wachtwoord_reset', 'account', $s, 'Wachtwoord opnieuw instellen', 'Op verzoek via Wachtwoord vergeten', 'Stel een nieuw wachtwoord in', 'Met de knop stel je het in.', self::AUCVY, 'ResetPassword', [
                $h('Nieuw wachtwoord'), $t('Je vroeg om een nieuw wachtwoord. Met de knop stel je het in.'),
                $st('Niet door jou aangevraagd?', 'Negeer deze mail. Je wachtwoord blijft dan hetzelfde.'),
                $b('Nieuw wachtwoord instellen'), $foot('Deze link werkt 60 minuten. We vragen nooit om je wachtwoord per mail.'),
            ]),
            $mail(4, 'inlogcode', 'account', $s, 'Inlogcode', 'Bij inloggen met tweede stap', 'Je inlogcode voor Aucvy', 'Vul deze code in om verder te gaan.', self::AUCVY, 'TwoFactorCode', [
                $h('Je inlogcode'), $t('Vul deze code in om verder te gaan.'), ['type' => 'code', 'value' => '{{code}}'],
                $st('Deel deze code met niemand', 'Ook medewerkers van Aucvy en Ivy vragen er nooit om.'),
                $foot('Deze code werkt 10 minuten. Niet door jou aangevraagd? Beveilig je account.'),
            ]),
            $mail(5, 'identiteit_bevestigd', 'account', $s, 'Identiteit bevestigd', 'Identiteitscheck goedgekeurd', 'Je identiteit is bevestigd', 'Bieden boven {{feiten.biedgrens}} kan nu.', self::IVY, 'VerificationDecided', [
                $h('Je kunt verder bieden'), $t('Je identiteit is bevestigd. Bieden boven {{feiten.biedgrens}} kan nu.'),
                $st('Identiteit bevestigd', 'Je hoeft dit niet nog een keer te doen.'), $lot(),
                $ivy('Goed om te weten', 'Boven {{feiten.borggrens}} is een borg van {{feiten.borg}} nodig. Die krijg je op verzoek terug als er geen open aankopen zijn.'),
                $b('Terug naar je kavel'), $b('Borg nu regelen', '{{links.borg}}', true),
            ]),
            $mail(6, 'identiteit_niet_gelukt', 'account', $s, 'Identiteit niet gelukt', 'Identiteitscheck afgewezen', 'Je identiteitscheck is niet gelukt', 'Dat is zo opgelost.', self::IVY, 'VerificationDecided', [
                $h('Nog een keer proberen'), $t('Het is deze keer niet gelukt. Dat is zo opgelost.'), $st('Reden', '{{reden}}'),
                $ivy('Zo lukt het wel', 'Leg je document op een lichte ondergrond. Zorg dat alle hoeken in beeld zijn. Maak de foto zonder flits.'),
                $b('Opnieuw proberen'), $foot('Lukt het niet? Vraag een medewerker.'),
            ]),
            $mail(7, 'borg_ontvangen', 'account', $s, 'Borg ontvangen', 'Borg betaald', 'Je borg van {{bedrag}} is ontvangen', 'Je kunt nu bieden boven {{feiten.borggrens}}.', self::IVY, null, [
                $h('Borg geregeld'), $t('Je kunt nu bieden boven {{feiten.borggrens}}.'), $amount('Algemene borg', '{{bedrag}}', 'ex btw, geldt voor je hele account'),
                $ivy('Wanneer krijg je hem terug?', 'Op verzoek, als er geen open aankopen zijn. Betaal je een gewonnen kavel niet, dan wordt hij ingehouden.'),
                $b('Terug naar de veilingen', '{{links.veilingen}}'), $foot('Boven {{feiten.borggrens_hoog}} is de borg {{feiten.borg_hoog}}.'),
            ]),
            $mail(8, 'bedrijf_geverifieerd', 'account', $s, 'Bedrijf geverifieerd', 'Bedrijfscontrole goedgekeurd, start reeks Eerste kavel', 'Je bedrijf is geverifieerd, plaats je eerste kavel', 'Je eerste kavel staat zo online.', self::IVY, 'VerificationDecided', [
                $h('Je kunt verkopen'), $t('Je bedrijf is geverifieerd. Je eerste kavel staat zo online.'),
                $st('Bedrijf geverifieerd', 'KvK-gegevens en rekeningnummer zijn gecontroleerd.'),
                $ivy('Stuur een paar foto\'s', 'Ik herken het product, schrijf de tekst en geef een prijsadvies. Jij keurt goed.'),
                $b('Plaats je eerste kavel', '{{links.verkopen}}'), $foot('Plaatsen is gratis. Je betaalt alleen bij verkoop.'),
            ]),

            $mail(9, 'autobod_aan', 'buyer', $s, 'Automatisch bieden staat aan', 'Koper zet automatisch bieden aan', 'Automatisch bieden staat aan tot {{limiet}}', 'Ik bied alleen als je overboden bent.', self::IVY, null, [
                $h('Ik bied mee'), $t('Automatisch bieden staat aan voor deze kavel.'), $lot(),
                $lines([['Jouw limiet', '{{limiet}}'], ['Ik bied alleen als je overboden bent', ''], ['Aangezet op', '{{moment}}']]),
                $ivy('Boven je limiet bied ik nooit', 'Bij je limiet stop ik en laat ik het je weten. Elk bod is bindend.'),
                $b('Bekijk je bod', '{{kavel.link}}'), $b('Automatisch bieden stoppen', '{{links.biedingen}}', true),
            ]),
            $mail(10, 'dit_zocht_je', 'buyer', $a, 'Dit zocht je toch?', 'Nieuwe kavel past goed bij een zoekvraag', 'Dit zocht je toch? {{kavel.titel}}', 'Nieuw binnen, en het past bij je zoekvraag.', self::IVY, 'AlertMatches', [
                $h('Dit zocht je toch?'), $t('Nieuw binnen, en het past bij je zoekvraag naar {{zoekvraag}}.'), $lot(),
                $ivy('Waarom ik aan jou dacht', '{{waarom}}'),
                $b('Wil je dat ik ga bieden?', '{{kavel.link}}'), $b('Bekijk kavel', '{{kavel.link}}', true), $foot('Niet wat je zoekt? Pas je zoekvraag aan.'),
            ]),
            $mail(11, 'alert_bundel', 'buyer', $a, 'Nieuwe kavels bij je alert', 'Meerdere nieuwe matches, per dag gebundeld', '{{aantal}} nieuwe kavels bij je zoekvraag', 'Vandaag binnengekomen bij je zoekvraag.', self::IVY, 'AlertMatches', [
                $h('{{aantal}} nieuwe kavels'), $t('Vandaag binnengekomen bij je zoekvraag.'), ['type' => 'lots', 'source' => 'context', 'limit' => 3],
                $b('Bekijk alle matches', '{{links.alerts}}'), $foot('Eén keer per dag. Pas je alert aan.'),
            ]),
            $mail(12, 'gevolgd_sluit_bijna', 'buyer', $a, 'Gevolgde kavel sluit bijna', '24 uur voor sluiting, nog geen bod', 'Morgen sluit de kavel die je volgt', 'Je volgt deze kavel en hebt nog niet geboden.', self::IVY, 'WatchlistEnding', [
                $h('Sluit morgen'), $t('Je volgt deze kavel en hebt nog niet geboden.'), $lot(), $st('Sluit over 24 uur', '{{kavel.sluit}}'),
                $ivy('Wil je niet steeds kijken?', 'Stel een maximum in, dan bied ik mee en waarschuw ik je als het nodig is.'),
                $b('Bied of stel een maximum in', '{{kavel.link}}'), $b('Niet meer volgen', '{{links.volglijst}}', true),
            ]),
            $mail(13, 'overboden', 'buyer', $s, 'Je bent overboden', 'Nieuw hoger bod, als de push niet aankwam', 'Je bent overboden op {{kavel.titel}}, nog {{resterend}}', 'Zal ik verhogen, of laat je hem gaan? Jij kiest.', self::IVY, 'OutbidMail', [
                $h('Je bent overboden'), $t('Zal ik verhogen, of laat je hem gaan? Jij kiest.'), $lot(), $st('Sluit over {{resterend}}', 'Jij stond op {{eigen_bod}}.'),
                $ivy('Wat ik ervan vind', '{{advies}}'),
                $b('Verhoog je bod', '{{kavel.link}}'), $b('Laat gaan', '{{links.biedingen}}', true), $foot('Je krijgt eerst een bevestigingsscherm met het actuele bod en alle kosten.'),
            ]),
            $mail(14, 'jouw_prijs_bereikt', 'buyer', $s, 'Jouw prijs is bereikt', 'Afslagprijs bereikt de grens van de koper (vlag afslag)', '{{kavel.titel}} staat op jouw prijs: {{kavel.bod}}', 'De afslag staat op de grens die je me gaf. Nu afmijnen?', self::IVY, null, [
                $h('Jouw prijs is bereikt'), $t('De afslag staat op de grens die je me gaf. Nu afmijnen?'), $lot(), $st('Zakt over {{resterend}}', 'Bij afslag wint wie als eerste toeslaat.'),
                $ivy('Goed om te weten', 'Wachten kan voordelig zijn, maar bij afslag wint wie als eerste toeslaat. {{volgers}} mensen volgen deze kavel.'),
                $b('Nu afmijnen voor {{kavel.bod}}', '{{kavel.link}}'), $b('Wacht verder', '{{kavel.link}}', true), $foot('Is de prijs veranderd als je klikt, dan zie je dat eerst.'),
            ]),
            $mail(15, 'limiet_bereikt', 'buyer', $s, 'Limiet bereikt', 'Automatisch bieden bereikt de limiet', 'Je limiet van {{limiet}} is bereikt', 'Ik heb geboden tot je limiet en bied niet verder.', self::IVY, null, [
                $h('Limiet bereikt'), $t('Ik heb geboden tot je limiet en bied niet verder.'), $lot(), $lines([['Jouw limiet', '{{limiet}}'], ['Sluit over', '{{resterend}}']]),
                $ivy('Jij beslist', 'Wil je verder, verhoog dan je limiet. Je budget was {{limiet}}.'),
                $b('Limiet aanpassen', '{{kavel.link}}'), $b('Laat gaan', '{{links.biedingen}}', true),
            ]),
            $mail(16, 'net_niet_gewonnen', 'buyer', $a, 'Net niet gewonnen', 'De dag na verlies', 'Net niet gewonnen, dit lijkt erop', '{{kavel.titel}} ging voor {{kavel.bod}}. Ik zoek gewoon door.', self::IVY, 'AuctionLost', [
                $h('Net niet gewonnen'), $t('{{kavel.titel}} ging voor {{kavel.bod}}. Ik zoek gewoon door.'), $lot(),
                $ivy('Dit lijkt erop', 'Deze kavels passen bij je zoekvraag en je budget.'), $lots(2),
                $b('Bekijk alle matches', '{{links.veilingen}}'), $foot('Klaar met zoeken? Stop deze zoekvraag.'),
            ]),

            $mail(17, 'gewonnen', 'purchase', $s, 'Gewonnen', 'Veiling gesloten, koper is winnaar', 'Gefeliciteerd, je hebt {{kavel.titel}} gewonnen', 'Nog één stap: betalen.', self::IVY, 'OrderCreated', [
                $h('Gefeliciteerd!'), $t('Je hebt deze kavel gewonnen. Nog één stap: betalen.'), $st('Je hebt gewonnen', 'Gesloten op {{moment}}.'), $lot(),
                $ivy('Wat is de volgende stap?', 'Je krijgt een betaalverzoek. Daarna regel ik samen met je het transport of het ophalen.'),
                $b('Naar betaling', '{{bestelling.link}}'),
            ]),
            $mail(18, 'betaalverzoek', 'purchase', $s, 'Betaalverzoek', 'Direct na winnen of koop direct', 'Betaal je kavel voor {{deadline}}', 'Rond je betaling af om de aankoop te bevestigen.', self::AUCVY, 'OrderCreated', [
                $h('Betaalverzoek'), $t('Je hebt deze kavel gewonnen. Rond je betaling af om de aankoop te bevestigen.'),
                $amount('Te betalen', '{{bestelling.totaal}}', 'Betaal voor {{deadline}}'), $lot(),
                $lines([['Winnend bod', '{{bestelling.hamer}}'], ['Opgeld', '{{bestelling.opgeld}}'], ['Administratie', '{{bestelling.administratie}}'], ['Transport', '{{bestelling.transport}}'], ['Totaal', '{{bestelling.totaal}}']]),
                $b('Nu betalen', '{{bestelling.link}}'), $foot('Je betaalt via de betaalpartij. Het geld blijft daar tot je de kavel hebt ontvangen.'),
            ]),
            $mail(19, 'betaalherinnering', 'purchase', $s, 'Betaalherinnering', '12 uur na het betaalverzoek', 'Nog {{resterend}} om te betalen', 'Je betaling voor deze kavel staat nog open.', self::AUCVY, null, [
                $h('Nog {{resterend}}'), $t('Je betaling voor deze kavel staat nog open.'), $amount('Te betalen', '{{bestelling.totaal}}', 'Betaal voor {{deadline}}'), $lot(),
                $st('Betaal je niet op tijd?', 'Dan kan de koop vervallen en wordt je borg ingehouden.'),
                $b('Nu betalen', '{{bestelling.link}}'), $foot('Lukt het niet? Vraag Ivy om hulp.'),
            ]),
            $mail(20, 'bestelling_bevestigd', 'purchase', $s, 'Bestelling bevestigd', 'Betaling ontvangen', 'Je betaling is binnen, bestelling {{bestelling.nummer}}', 'Bedankt voor je aankoop. Dit zijn de details.', self::IVY, 'OrderStatusChanged', [
                $h('Bestelling bevestigd'), $t('Bedankt voor je aankoop. Dit zijn de details.'), $st('Je bestelling is bevestigd', 'Bestelling {{bestelling.nummer}} · {{moment}}'), $lot(),
                $ivy('Wat gebeurt er nu?', 'De verkoper is geïnformeerd. Ik plan het transport met je in. Na ontvangst heb je {{feiten.meldtermijn}} om iets te melden.'),
                $b('Naar mijn bestelling', '{{bestelling.link}}'), $foot('Vragen? Vraag het Ivy of neem contact op.'),
            ]),
            $mail(21, 'transport_ingepland', 'purchase', $s, 'Je kavel komt eraan', 'Transport ingepland', 'Je kavel komt {{moment}}', 'Transport is ingepland. Dit is de stand.', self::IVY, 'OrderStatusChanged', [
                $h('Je kavel komt eraan'), $t('Transport is ingepland. Dit is de stand.'), $st('Bezorging', '{{moment}}'), $lot(),
                $steps(['Betaald', 'Transport ingepland', 'Ontvangst bevestigen, daarna {{feiten.meldtermijn}} om iets te melden']),
                $b('Bekijk je bestelling', '{{bestelling.link}}'), $foot('Past het moment niet? Vraag Ivy om te verzetten.'),
            ]),
            $mail(22, 'ophaalmoment_bevestigd', 'purchase', $s, 'Ophaalmoment bevestigd', 'Koper en verkoper kiezen een moment', 'Ophalen {{moment}}', 'Neem deze mail mee bij het ophalen.', self::IVY, 'ViewingConfirmed', [
                $h('Ophalen staat vast'), $t('Neem deze mail mee bij het ophalen.'), $lot(),
                $lines([['Wanneer', '{{moment}}'], ['Waar', '{{adres}}'], ['Ophaalcode', '{{code}}']]),
                $ivy('Waar je aan moet denken', 'Neem hulp en passend vervoer mee. Controleer de kavel ter plekke, voordat je hem meeneemt.'),
                $b('Bekijk je bestelling', '{{bestelling.link}}'), $b('Ander moment vragen', '{{bestelling.link}}', true),
            ]),
            $mail(23, 'alles_ontvangen', 'purchase', $s, 'Alles goed ontvangen?', 'Na bezorging of ophalen', 'Is alles goed aangekomen?', 'Bevestig de ontvangst, dan ronden we het af.', self::IVY, 'OrderStatusChanged', [
                $h('Alles goed ontvangen?'), $t('Bevestig de ontvangst, dan ronden we het af.'), $lot(),
                $st('Je hebt {{feiten.meldtermijn}}', 'Na bevestiging kun je nog {{feiten.meldtermijn}} een probleem melden. Zonder bevestiging komt het geld {{feiten.vrijgave}} na levering vrij.'),
                $b('Ja, alles is binnen', '{{bestelling.link}}'), $b('Er klopt iets niet', '{{bestelling.link}}', true),
            ]),
            $mail(24, 'waardering_vragen', 'purchase', $s, 'Hoe was je aankoop?', '2 dagen na ontvangst', 'Hoe was je aankoop bij deze verkoper?', 'Met één tik help je andere kopers.', self::IVY, 'ReviewRequested', [
                $h('Hoe was je aankoop?'), $t('Met één tik help je andere kopers.'), $lot(),
                $t('Je waardering staat op het profiel van de verkoper, met je biednaam.'),
                $b('Geef je waardering', '{{bestelling.link}}'),
            ]),
            $mail(25, 'melding_ontvangen', 'purchase', $s, 'Melding ontvangen', 'Koper meldt een probleem', 'We hebben je melding ontvangen, zaak {{zaak}}', 'Je betaling blijft vaststaan tot dit is opgelost.', self::AUCVY, 'OrderMessageReceived', [
                $h('Melding ontvangen'), $t('Je betaling blijft vaststaan tot dit is opgelost.'), $st('Zaak {{zaak}}', '{{omschrijving}}'), $lot(),
                $steps(['Melding ontvangen', 'Verkoper reageert binnen {{feiten.reactietermijn}}', 'Oplossing of bemiddeling; een medewerker kijkt mee als jullie er niet uitkomen']),
                $b('Bekijk je melding', '{{bestelling.link}}'),
            ]),
            $mail(26, 'terugbetaling', 'purchase', $s, 'Terugbetaling', 'Terugbetaling verwerkt', 'Je terugbetaling is verwerkt', 'Het bedrag gaat terug naar je rekening.', self::AUCVY, 'DisputeResolved', [
                $h('Terugbetaald'), $t('Het bedrag gaat terug naar je rekening.'), $amount('Terugbetaald', '{{bedrag}}', 'via je oorspronkelijke betaalmethode'), $lot(),
                $t('Staat binnen enkele werkdagen op je rekening. Hoe snel hangt af van je bank.'),
                $b('Bekijk het overzicht', '{{bestelling.link}}'),
            ]),

            $mail(27, 'concept_bijna_klaar', 'seller', $a, 'Bijna klaar om live te gaan', 'Concept 24 uur niet aangepast', 'Nog {{aantal}} dingen, dan staat je kavel online', 'Je concept blijft bewaard.', self::IVY, null, [
                $h('Bijna klaar om live te gaan'), $t('Nog {{aantal}} dingen, dan staat {{kavel.titel}} online.'), $lot(),
                $ivy('Wat er nog moet', '{{taken}}'),
                $b('Maak je kavel af', '{{kavel.bewerken}}'), $foot('Je concept blijft bewaard. Vragen? Vraag het Ivy.'),
            ]),
            $mail(28, 'kavel_live', 'seller', $s, 'Je kavel staat live', 'Ivy-toets geslaagd, kavel gepubliceerd', '{{kavel.titel}} staat live', 'Ik heb alles gecontroleerd. Kopers kunnen nu bieden.', self::IVY, 'ListingScreened', [
                $h('Je kavel staat live'), $t('Ik heb alles gecontroleerd. Kopers kunnen nu bieden.'), $lot(),
                $ivy('Wat ik nu voor je doe', 'Ik stuur je elke dag een overzicht, en direct een bericht als ik je nodig heb.'),
                $b('Bekijk je kavel', '{{kavel.link}}'), $b('Deel je kavel', '{{kavel.verspreiding}}', true),
            ]),
            $mail(29, 'kavel_niet_live', 'seller', $s, 'Kavel nog niet live', 'Ivy-toets vindt iets dat ontbreekt', 'Er ontbreekt nog iets aan je kavel', 'Je kavel kan nog niet live. Dit moet nog.', self::IVY, 'ListingScreened', [
                $h('Nog één ding'), $t('Je kavel kan nog niet live. Dit moet nog.'), $st('Wat ontbreekt', '{{taken}}'), $lot(),
                $ivy('Zo los je het op', 'Het kost een minuut. Bij twijfel over echtheid of verboden waar kijkt een medewerker mee.'),
                $b('Nu aanvullen', '{{kavel.bewerken}}'), $foot('Niet eens met deze controle? Vraag een medewerker.'),
            ]),
            $mail(30, 'dagoverzicht', 'seller', $s, 'Zo liep het vandaag', 'Dagelijks tijdens de veiling', 'Vandaag {{views}} views en {{bieders}} nieuwe bieders', 'De interesse in je kavel groeit.', self::IVY, 'WeeklyInsights', [
                $h('Zo liep het vandaag'), $t('De interesse in {{kavel.titel}} groeit. Nog {{resterend}}.'),
                $lines([['Hoogste bod', '{{kavel.bod}}'], ['Views', '+{{views}}'], ['Volgers', '+{{volgers}}'], ['Bieders', '+{{bieders}}']]),
                $ivy('Mijn tip voor morgen', '{{tip}}'),
                $b('Bekijk je kavel', '{{kavel.dossier}}'), $foot('Eén keer per dag. Pas je meldingen aan.'),
            ]),
            $mail(31, 'vraag_van_koper', 'seller', $s, 'Vraag van een koper', 'Koper stelt een vraag die Ivy niet kan beantwoorden', 'Een koper vraagt naar {{kavel.titel}}', '{{biednaam}} heeft een vraag die ik niet kan beantwoorden.', self::IVY, 'ConversationUnread', [
                $h('Vraag van een koper'), $t('{{biednaam}} heeft een vraag die ik niet kan beantwoorden.'), $lot(), $st('De vraag', '{{vraag}}'),
                $ivy('Mijn concept', '{{concept}}'),
                $b('Beantwoord de vraag', '{{link}}'), $foot('Contactgegevens delen kan niet in berichten. Je antwoord is zichtbaar bij de kavel.'),
            ]),
            $mail(32, 'jouw_beslissing', 'seller', $s, 'Jouw beslissing', 'Reserve niet gehaald, kort voor sluiting', 'Reserve niet gehaald, nog {{resterend}}', 'Zonder wijziging wordt hij niet verkocht.', self::IVY, null, [
                $h('Jouw beslissing'), $t('De reserve is nog niet gehaald. Zonder wijziging wordt hij niet verkocht.'), $st('Nog {{resterend}}', '{{kavel.titel}}'),
                $lines([['Hoogste bod', '{{kavel.bod}}'], ['Jouw reserve', '{{reserve}}'], ['Succesfee {{fee.percentage}}', '- {{fee.bedrag}}'], ['Jij ontvangt bij {{kavel.bod}}', '{{netto}}']]),
                $b('Verlaag naar {{kavel.bod}}', '{{kavel.dossier}}'), $b('Reserve laten staan', '{{kavel.dossier}}', true), $foot('Jij beslist, ik doe dit nooit zelf. Vraag een medewerker.'),
            ]),
            $mail(33, 'verkocht', 'seller', $s, 'Verkocht', 'Veiling gesloten met koper', 'Verkocht voor {{kavel.bod}}', 'De koper heeft nu {{feiten.betaaltermijn}} om te betalen.', self::IVY, 'OrderCreated', [
                $h('Verkocht!'), $t('{{kavel.titel}} is verkocht. De koper heeft nu {{feiten.betaaltermijn}} om te betalen.'), $st('Verkocht voor {{kavel.bod}}', 'Aan {{biednaam}}, {{bieders}} bieders in totaal.'), $lot(),
                $steps(['Koper betaalt binnen {{feiten.betaaltermijn}}', 'Afgifte plannen', 'Uitbetaling na ontvangst door de koper']),
                $b('Bekijk de verkoop', '{{bestelling.link}}'),
            ]),
            $mail(34, 'niet_verkocht', 'seller', $s, 'Niet verkocht', 'Veiling gesloten zonder koper', 'Je kavel is niet verkocht, dit kun je doen', 'Er is geen koper gevonden. Dit kost je niets.', self::IVY, 'AuctionWithdrawn', [
                $h('Niet verkocht'), $t('Er is geen koper gevonden. Dit kost je niets.'), $lot(),
                $ivy('Mijn advies', '{{advies}}'),
                $b('Opnieuw plaatsen', '{{kavel.dossier}}'), $b('Probeer afslag', '{{kavel.bewerken}}', true), $foot('Plaatsen blijft gratis. Vraag een medewerker.'),
            ]),

            $mail(35, 'betaling_ontvangen', 'sale', $s, 'Betaling ontvangen', 'Koper heeft betaald', 'De koper heeft betaald: {{kavel.bod}}', 'Nu nog de afgifte.', self::IVY, 'OrderStatusChanged', [
                $h('Betaling ontvangen'), $t('De koper heeft betaald. Nu nog de afgifte.'), $amount('Is binnen', '{{kavel.bod}}', 'Blijft bij de betaalpartij tot de koper heeft ontvangen'), $lot(),
                $lines([['Verkocht voor', '{{kavel.bod}}'], ['Succesfee {{fee.percentage}}', '- {{fee.bedrag}}'], ['Jij ontvangt', '{{netto}}']]),
                $b('Afgifte plannen', '{{bestelling.link}}'), $foot('Geef de kavel pas mee na deze betaalbevestiging.'),
            ]),
            $mail(36, 'koper_betaalt_niet', 'sale', $s, 'Koper betaalt niet', 'Betaaltermijn verstreken', 'De betaling voor {{kavel.titel}} blijft uit', 'Je hoeft de kavel niet af te geven.', self::IVY, 'OrderStatusChanged', [
                $h('Betaling blijft uit'), $t('De koper heeft niet op tijd betaald. Je hoeft de kavel niet af te geven.'), $st('Betaaltermijn verstreken', '{{biednaam}} had tot {{deadline}}.'), $lot(),
                $ivy('Wat ik voorstel', 'Ik zet de kavel voor je klaar om opnieuw te plaatsen. Je betaalt alleen bij een nieuwe verkoop.'),
                $b('Opnieuw plaatsen', '{{kavel.dossier}}'), $b('Vraag een medewerker', '{{links.contact}}', true),
            ]),
            $mail(37, 'afgifte_gepland', 'sale', $s, 'Afgifte gepland', 'Koper en verkoper kiezen een moment', 'De koper haalt {{moment}} op', '{{biednaam}} komt je kavel ophalen.', self::IVY, 'ViewingConfirmed', [
                $h('Afgifte gepland'), $t('{{biednaam}} komt je kavel ophalen.'), $lot(), $lines([['Wanneer', '{{moment}}'], ['Ophaalcode', '{{code}}']]),
                $ivy('Zo gaat het goed', 'Vraag om de ophaalcode, en laat de koper de kavel ter plekke controleren.'),
                $b('Bekijk de verkoop', '{{bestelling.link}}'), $b('Ander moment vragen', '{{bestelling.link}}', true),
            ]),
            $mail(38, 'uitbetaling_ingepland', 'sale', $s, 'Uitbetaling ingepland', 'Koper heeft ontvangst bevestigd', 'Je uitbetaling van {{netto}} is ingepland', 'De koper heeft ontvangen. Je geld is bijna onderweg.', self::IVY, 'OrderStatusChanged', [
                $h('Uitbetaling ingepland'), $t('De koper heeft ontvangen. Je geld is bijna onderweg.'), $amount('Je ontvangt', '{{netto}}', 'Na de meldtermijn'), $lot(),
                $steps(['Verkoop afgerond', '{{feiten.meldtermijn}} om iets te melden, tot {{deadline}}', 'Uitbetaling verzonden']),
                $b('Bekijk uitbetaling', '{{links.uitbetalingen}}'),
            ]),
            $mail(39, 'uitbetaling_verzonden', 'sale', $s, 'Uitbetaling verzonden', 'Meldtermijn voorbij, geen geschil', '{{netto}} is onderweg naar je rekening', 'De opbrengst is overgemaakt naar je bankrekening.', self::IVY, 'PayoutReleased', [
                $h('Uitbetaling verzonden'), $t('De opbrengst is overgemaakt naar je bankrekening.'), $st('Uitbetaling is verzonden', 'Staat binnen 1 tot 2 werkdagen op je rekening.'), $lot(),
                $lines([['Succesfee {{fee.percentage}}', '- {{fee.bedrag}}'], ['Uitbetaald', '{{netto}}']]),
                $b('Bekijk uitbetaling', '{{links.uitbetalingen}}'), $foot('Na 3 werkdagen niet ontvangen? Neem contact op.'),
            ]),
            $mail(40, 'geschil_reactie', 'sale', $s, 'Geschil: reactie nodig', 'Koper meldt een probleem', 'Een koper heeft een probleem gemeld, reageer binnen {{feiten.reactietermijn}}', 'Je uitbetaling staat even stil.', self::IVY, 'OrderMessageReceived', [
                $h('Reactie nodig'), $t('Een koper heeft een probleem gemeld. Je uitbetaling staat even stil.'), $amount('Bedrag staat vast', '{{bedrag}}', 'Reageer binnen {{feiten.reactietermijn}}'), $lot(),
                $ivy('Wat de koper meldt', '{{omschrijving}} Bekijk de foto\'s en kies wat je voorstelt: reparatie, terugbetaling, of je reactie.'),
                $b('Reageer op de melding', '{{bestelling.link}}'), $foot('Komen jullie er niet uit, dan kijkt een medewerker mee.'),
            ]),
            $mail(41, 'factuur_succesfee', 'sale', $s, 'Factuur succesfee', 'Na verkoop', 'Je factuur voor {{kavel.titel}} staat klaar', 'De factuur voor je succesfee is beschikbaar.', self::AUCVY, null, [
                $h('Je factuur staat klaar'), $t('De factuur voor je succesfee is beschikbaar.'), $lot(),
                $lines([['Factuurnummer', '{{factuur.nummer}}'], ['Succesfee {{fee.percentage}} over {{kavel.bod}}', '{{fee.bedrag}}'], ['Btw', '{{factuur.btw}}'], ['Verrekend met uitbetaling', '{{fee.bedrag}}']]),
                $b('Download factuur', '{{factuur.link}}'), $foot('Ook te vinden onder Financiën in je account.'),
            ]),
            $mail(42, 'fee_gezakt', 'sale', $s, 'Je fee is gezakt', 'Omzet komt in een lagere staffel', 'Je succesfee is nu {{fee.percentage}}', 'Je omzet op Aucvy is boven {{staffel.grens}} gekomen.', self::IVY, null, [
                $h('Je fee is gezakt'), $t('Je omzet op Aucvy is boven {{staffel.grens}} gekomen.'), $lines([['Je nieuwe succesfee', '{{fee.percentage}}, was {{staffel.vorige}}'], ['Volgende vanaf', '{{staffel.volgende}}']]),
                $ivy('Wat dit betekent', 'Het nieuwe tarief geldt voor elke volgende verkoop. Eerdere verkopen veranderen niet.'),
                $b('Bekijk je omzet', '{{links.financien}}'), $b('Plaats een nieuwe kavel', '{{links.verkopen}}', true),
            ]),

            $mail(43, 'slapende_koper', 'campaign', $a, 'Slapende koper', '6 maanden geen login, eenmalig', 'Zoek je nog naar {{zoekvraag}}?', 'Je zocht eerder naar {{zoekvraag}}. Dit loopt er nu.', self::IVY, null, [
                $h('Zoek je nog?'), $t('Je zocht eerder naar {{zoekvraag}}. Dit loopt er nu.'), $lots(2),
                $ivy('Niet meer nodig?', 'Geef het aan, dan bewaak ik deze zoekvraag niet meer.'),
                $b('Bekijk de kavels', '{{links.veilingen}}'), $b('Stop deze zoekvraag', '{{links.alerts}}', true),
            ]),
            $mail(44, 'nieuwe_kavels_week', 'campaign', $c, 'Nieuwe kavels deze week', 'Campagne vanuit de Mail Builder, met toestemming', 'Deze week nieuw: {{categorie}}', 'Een selectie uit de categorieën die je volgt.', self::AUCVY, null, [
                $h('Nieuw deze week'), $t('Een selectie uit de categorieën die je volgt.'), $lots(3),
                $b('Bekijk alle nieuwe kavels', '{{links.veilingen}}'), $foot('Kavels en biedingen worden opgehaald bij het versturen.'),
            ]),
            $mail(45, 'kijkdag', 'campaign', $c, 'Kijkdag', 'Campagne bij een kavel met kijkdag', 'Kom kijken: {{kavel.titel}}', 'Bekijk de inventaris voordat je biedt.', self::IVY, null, [
                $h('Kom kijken'), $t('Je kunt deze inventaris bekijken voordat je biedt.'), $lines([['Wanneer', '{{moment}}'], ['Waar', '{{adres}}'], ['Kavels', '{{aantal}}, bieden kan al']]),
                $ivy('Goed voorbereid', 'Maak vooraf een lijstje. Ik bewaar de kavels die je wilt bekijken.'),
                $b('Ik kom kijken', '{{kavel.link}}'), $b('Bekijk de kavels', '{{kavel.link}}', true),
            ]),
            $mail(46, 'nieuwsbrief', 'campaign', $c, 'Nieuwsbrief', 'Maandelijks, met toestemming', '{{titel}}', 'Tips van Aucvy over slim verkopen en kopen.', self::AUCVY, null, [
                $h('Uit de praktijk'), $t('Tips van Aucvy over slim verkopen en kopen.'), $st('{{titel}}', '{{intro}}'), $lots(1),
                $b('Lees het artikel', '{{link}}'), $foot('Elke maand één mail. Je kunt je altijd afmelden.'),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Zet de 46 mails in de database als ze er nog niet staan; een bestaande
     * template met eigen versies blijft onaangeroerd.
     */
    public static function seed(): void
    {
        DB::transaction(function (): void {
            foreach (self::all() as $entry) {
                $template = MailTemplate::query()->firstOrCreate(['key' => $entry['key']], [
                    'number' => $entry['number'],
                    'stream' => $entry['stream'],
                    'group' => $entry['group'],
                    'name' => $entry['name'],
                    'trigger' => $entry['trigger'],
                    'notification' => $entry['notification'],
                ]);

                if ($template->current_version_id === null) {
                    $version = MailTemplateVersion::create([
                        'template_id' => $template->id,
                        'version' => 1,
                        'subject' => $entry['subject'],
                        'preheader' => $entry['preheader'],
                        'sender' => $entry['sender'],
                        'blocks' => $entry['blocks'],
                        'created_at' => now(),
                    ]);

                    $template->forceFill(['current_version_id' => $version->id])->save();
                }
            }
        });
    }
}
