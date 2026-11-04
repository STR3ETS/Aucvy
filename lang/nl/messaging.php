<?php

declare(strict_types=1);

/*
 | Berichten tussen koper en verkoper, de moderator-bot en de operator
 | (briefing nieuwe functies, 3 november 2026).
 */
return [

    'kinds' => [
        'question' => 'Korte vraag',
        'chat' => 'Gesprek',
        'live' => 'Livechat',
    ],

    'parts' => [
        'specificaties' => 'Specificaties',
        'staat' => 'Staat',
        'documenten' => 'Documenten',
        'ophalen' => 'Ophalen en transport',
        'betaling' => 'Betaling',
        'foto' => "Foto's",
    ],

    'signals' => [
        'none' => 'Geen signaal',
        'contact_before_payment' => 'Contact vóór betaling',
        'off_platform' => 'Buiten Aucvy betalen',
        'different_deal' => 'Andere afspraak dan de order',
        'bid_manipulation' => 'Biedafspraak',
        'offensive' => 'Grof of discriminerend',
        'phishing' => 'Phishing of malware',
        'doubt' => 'Twijfel van Ivy',
        'reported' => 'Gemeld door een deelnemer',
    ],

    'explanations' => [
        'contact' => 'Telefoonnummer of mailadres afgeschermd. Na de betaling mag het, voor het ophalen.',
        'off_platform' => 'Tegengehouden: dit gaat over betalen buiten Aucvy om. Een medewerker bekijkt het. Betaling loopt via de bestelling; dan blijft het geld beschermd.',
        'bid_manipulation' => 'Tegengehouden: afspraken over bieden zijn niet toegestaan. Een medewerker bekijkt het.',
        'offensive' => 'Niet verstuurd: dit bericht bevat scheldwoorden of discriminerende taal. Pas het aan en probeer het opnieuw.',
        'phishing' => 'Niet verstuurd: een verkorte link of inlogpagina mag niet in een gesprek. Links zijn hier nooit klikbaar.',
        'deal_money' => 'Dit klinkt als een andere prijs of voorwaarde. Een prijs of korting telt alleen als die in de bestelling staat.',
        'deal_pickup' => 'Dit klinkt als een afspraak. Een ophaalmoment telt pas als het in de bestelling staat.',
    ],

    'states' => [
        'held' => 'Vastgehouden, de ander ziet dit bericht nog niet.',
        'rejected' => 'Niet verstuurd.',
        'masked' => 'Contactgegevens afgeschermd.',
        'released' => 'Vrijgegeven door een medewerker.',
    ],

    'errors' => [
        'verify_first' => 'Bevestig eerst je e-mailadres en telefoonnummer. Dan kun je vragen stellen en berichten sturen.',
        'own_lot' => 'Je kunt geen gesprek starten over je eigen kavel.',
        'blocked' => 'Dit gesprek is gesloten.',
        'not_live' => 'Een vraag stellen kan alleen bij een kavel die online staat.',
        'nothing_to_publish' => 'Er is nog geen antwoord om bij de kavel te zetten.',
        'reason_required' => 'Geef een reden om dit gesprek te openen; er is geen vlag of melding.',
    ],

    'notices' => [
        'operator_watches' => 'Ivy controleert dit gesprek op afspraken buiten Aucvy om. Een medewerker kan meelezen bij een vlag, melding of geschil.',
        'operator_joined' => 'Een medewerker van Aucvy leest mee in dit gesprek.',
        'names' => 'De verkoper ziet je biednaam, niet je naam.',
        'question_public' => 'Is het antwoord belangrijk voor alle bieders, dan kan het zonder jouw naam bij de kavel komen te staan.',
        'no_contact' => 'Deel geen telefoonnummer of mailadres: die worden afgeschermd tot na de betaling.',
    ],

    'status' => [
        'sent' => 'Je bericht is verstuurd.',
        'held' => 'Je bericht is vastgehouden. Een medewerker bekijkt het.',
        'rejected' => 'Je bericht is niet verstuurd.',
        'published' => 'Het antwoord staat nu bij de kavel.',
        'reported' => 'Gemeld. Een medewerker bekijkt het gesprek.',
        'blocked' => 'Gesprek gesloten. Je krijgt geen berichten meer van deze partij.',
        'released' => 'Bericht vrijgegeven; de ontvanger ziet het nu.',
        'confirmed' => 'Tegenhouden bevestigd.',
        'replied' => 'Je reactie staat in het gesprek; beide partijen zien dat je meeleest.',
        'noted' => 'Notitie opgeslagen; alleen het team ziet hem.',
        'dispute' => 'Geschil gestart met het gesprek als bewijs.',
        'warned' => 'De verkoper is gewaarschuwd.',
    ],

];
