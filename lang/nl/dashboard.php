<?php

declare(strict_types=1);

return [

    'actions' => [
        'view' => 'Bekijken',
        'continue_draft' => 'Verder met je concept',
        'arrange' => 'Regelen',
        'respond' => 'Reageren',
        'pay' => 'Betalen',
        'confirm' => 'Bevestigen',
    ],

    'signals' => [
        'outbid' => 'Je bent overboden op ":lot"',
        'outbid_body' => 'De veiling loopt nog. Bied opnieuw of laat hem lopen - jij beslist.',
        'missing_transport' => 'Transportinformatie ontbreekt bij ":lot"',
        'missing_transport_body' => 'Kopers haken af als ze niet weten waar ze aan toe zijn.',
        'missing_ownership' => 'Eigendomsbewijs ontbreekt bij ":lot"',
        'missing_ownership_body' => 'Een eigendomsbewijs geeft kopers zekerheid en voorkomt gedoe bij de levering.',
        'unfinished_draft' => '{1} Eén concept staat klaar om afgemaakt te worden|[2,*] :count concepten staan klaar om afgemaakt te worden',
        'unfinished_draft_body' => 'Een concept in de la trekt geen bieders.',
        'payout_ready' => ':amount staat klaar voor uitbetaling',
        'payout_ready_body' => 'De houdtermijn is verstreken; het bedrag is vrijgegeven voor uitbetaling.',
        'order_to_ship' => '":lot" is betaald - regel de levering',
        'order_to_ship_body' => 'Zet hem klaar om op te halen of meld hem verzonden. Na zeven dagen stilte geldt hij als geleverd.',
        'dispute_response' => 'Geschil op bestelling :order wacht op je reactie',
        'dispute_response_body' => 'Reageer vóór :due - daarna gaat de melding zonder jouw kant van het verhaal naar de beoordelaar.',
        'order_to_pay' => 'Betaal ":lot" voor het einde van de termijn',
        'order_to_pay_body' => 'De betaaltermijn loopt tot :deadline; daarna vervalt de koop.',
        'delivery_confirm' => '":lot" is onderweg of staat klaar',
        'delivery_confirm_body' => 'Bevestig de levering zodra alles binnen en gecontroleerd is.',
    ],

    'done' => [
        'recognised' => '{1} 1 object herkend uit foto\'s|[2,*] :count objecten herkend uit foto\'s',
        'listings' => '{1} 1 advertentie geschreven|[2,*] :count advertenties geschreven',
        'estimates' => '{1} 1 waardeschatting gemaakt|[2,*] :count waardeschattingen gemaakt',
        'documents' => '{1} 1 document privé opgeslagen|[2,*] :count documenten privé opgeslagen',
        'orders' => '{1} 1 verkoop begeleid van bod tot betaling|[2,*] :count verkopen begeleid van bod tot betaling',
        'payouts' => '{1} 1 uitbetaling klaargezet|[2,*] :count uitbetalingen klaargezet',
    ],

    'intent' => [
        'title' => 'Koperintentie',
        'watchers' => 'volgen je kavels',
        'viewings' => 'kijkdagaanvragen',
        'bidders' => 'brachten een bod uit',
        'business' => 'zakelijke accounts daarvan',
        'privacy' => 'Gedragssignalen uit volglijsten, kijkdagen en biedingen - geen persoonsgegevens.',
    ],

    'forecast' => [
        'title' => 'Wat doet de vraag straks?',
        'measured' => 'gemeten',
        'predicted' => 'voorspeld',
        'empty' => 'Nog te weinig verkoophistorie voor een verwachting. Die groeit mee met je eerste verkopen.',
        'disclaimer' => 'De voorspelling is een bandbreedte op basis van je eigen weekcijfers, geen belofte.',
    ],

    'start' => [
        'title' => 'Welkom bij Aucvy',
        'intro' => 'Drie stappen om te beginnen:',
        'step_sell' => 'Plaats je eerste kavel - een paar foto\'s zijn genoeg, Ivy helpt met de rest.',
        'step_browse' => 'Bekijk de lopende veilingen en volg wat je interessant vindt.',
        'step_bid' => 'Bied mee - boven de borgdrempel vragen we eerst een borgreservering die vrijkomt als je niet wint.',
    ],

];
