# Vragen voor Raph: antwoorden en vervolg

Laatst bijgewerkt: 2 november 2026. Van: Boyd.

Raph beantwoordde op 2 november 34 van de 36 vragen (vragen 25 en 34 bleven leeg; daar geldt de standaardkeuze). Hieronder per vraag het antwoord en wat we ermee doen. Onderaan staan de vervolgvragen: een paar voor Raph, de rest voor Don.

Statuswoorden: **Besloten** = we bouwen ernaar. **Bij Don** = Raph vraagt het na; tot dan geldt de standaardkeuze. **Standaard** = geen antwoord, standaardkeuze geldt.

---

## Antwoorden per vraag

| # | Onderwerp | Antwoord van Raph | Wat we doen | Status |
| --- | --- | --- | --- | --- |
| 1 | Betaalprovider | Stripe Connect | Stripe Connect in plaats van Mollie. Koper betaalt aan het platform, het geld blijft bij Stripe tot de koper heeft ontvangen, daarna gaat het naar de verkoper. Verkopers doorlopen de onboarding van Stripe (Express), dat dekt ook hun bedrijfscontrole en rekeningnummer. | Besloten |
| 2 | Wie factureert | Vraagt Raph aan Don | Standaard (a) tot Don antwoordt. Geen factuurdocumenten bouwen voor het antwoord er is. | Bij Don |
| 3 | Btw | Vraagt Raph aan Don | Standaard: alleen Nederland, margeregeling waar de verkoper dat aangeeft, anders 21%. | Bij Don |
| 4 | Refunds | Automatisch via de PSP | Refunds en chargebacks via Stripe, vanuit de geschilroute en de backoffice. | Besloten |
| 5 | Boekhouding | Overleggen met Don | Csv-export blijft. | Bij Don |
| 6 | Prijzen | Navragen bij Don | Alles blijft zoals het staat. | Bij Don |
| 7 | Termijnen | Betalen direct; het geld wordt vrijgegeven als de klant heeft ontvangen | Betalen direct na winnen of kopen. Uitbetaling na bevestigde ontvangst. Zie vervolgvragen V1 en V2 voor de precieze termijnen. | Besloten, met vervolgvraag |
| 8 | Verificatieprovider | iDIN lijkt het beste, bespreken met Remco | Gebouwd: identiteit via de bank achter een eigen contract (heen naar de partij, terug met alleen de uitkomst en een referentie, 18+), met een nepbank voor lokaal. Handmatige beoordeling blijft de standaard tot iDIN live is. | Gebouwd, wacht op Remco |
| 9 | Landen en drempels | Verificatie (KYC) is noodzakelijk, land is niet bepalend | Verificatie vóór het eerste bod blijft, voor iedereen, ongeacht land of bedrag. | Besloten |
| 10 | Doelgroep | Zakelijke verkopers, particuliere kopers | Alleen zakelijke accounts met goedgekeurde bedrijfscontrole mogen publiceren. Kopers mogen particulier zijn. Zie vervolgvraag D1 over herroepingsrecht. | Besloten |
| 11 | Tweede factor | Akkoord voor het pakket (a) | Gebouwd: TOTP via een authenticator-app (QR of sleutel), acht herstelcodes die elk één keer werken, de mailcode als terugval; koppelen en ontkoppelen vraagt het wachtwoord. | Gebouwd |
| 12 | Telefoonnummer | Sms-dienst koppelen kan | Gebouwd: telefoon bevestigen met een code per sms (6 cijfers, 10 minuten, max. 3 codes per 10 minuten), achter een eigen contract; tot de partij er is gaat de sms naar het log. Zie V3. | Gebouwd, wacht op partij |
| 13 | Prijzen openbaar | Navragen bij Don | Verkochte kavels blijven zonder prijs. | Bij Don |
| 14 | Reviewbeleid | Het Google-principe: de verkoper kan reageren; automatische moderatie op scheldwoorden, politieke kleuring, discriminatie | Reviews gaan door een automatische controle vóór publicatie; wat erdoor valt, wordt niet gepubliceerd en gaat naar beheer. Iedereen kan een review melden. De verkoper reageert, maar verwijdert niets. | Besloten |
| 15 | Reputatiescore | Standaard, vanaf 10 | Score vanaf 10 afgeronde transacties, over de laatste 12 maanden. | Besloten |
| 16 | Publicatie | De bot kijkt of de kavel aan alle criteria voldoet (prijs, hoeveelheid per eenheid, garanties); de echtheid van documentatie moet worden aangevinkt; dan pas publiceren | Vooraf modereren door Ivy: de verkoper vinkt garantie en echtheid van documenten aan, Ivy toetst de kavel, en alleen een goedgekeurde kavel gaat live. Gebouwd: verkoper geeft hoeveelheid met eenheid en garantie op en vinkt de echtheid van documenten aan; Ivy toetst en zet live, stuurt terug of legt bij een medewerker. Zie V4. | Gebouwd |
| 17 | Verboden categorieën | Levert Raph aan; kijk ook wat andere platformen verbieden en houd dat gelijk | Gebouwd: een conceptlijst (wapens, explosieven, drugs, receptmedicijnen, levende en beschermde dieren, namaak, goederen zonder herkomst, gevaarlijke stoffen en meer) op basis van wat gangbare veilingplatformen verbieden. Een treffer gaat altijd naar een medewerker. Raph kan de lijst aanvullen. | Gebouwd, concept |
| 18 | Biedregel reserve | Navragen bij Don | Standaard: tillen tot de reserve. | Bij Don |
| 19 | Mail Builder | Mag gebruiken, met eigen templates | De Mail Builder van Eazy overnemen met eigen Aucvy-templates. | Besloten |
| 20 | Mailprovider | Mailgun, zet Raph maandag op | Gebouwd: Mailgun als mailer (EU-endpoint), afleverlog per mail, webhook met handtekeningcontrole voor afleveren, bounces en spammeldingen, en een Ivy-signaal als mail niet aankomt. Alleen de sleutels nog; zie V5. | Gebouwd, wacht op sleutels |
| 21 | CMS | Mail Builder mag; de blogmodule koppelen aan Eazyonline met een API, zoals FitnessAannemer en Eazyonline nu doen met NOVA van Inspace | Gebouwd: /blog en /blog/{slug} halen berichten op bij de Eazyonline-API met een leestoken, met cache en een reserveversie als de API even weg is, veilige HTML, SEO-gegevens en de sitemap. Alleen het token nog; zie V6. | Gebouwd, wacht op token |
| 22 | Tenants | Niet multi-tenant, blijft in eigen bezit | Eén omgeving. Branding en rechten per organisatie. | Besloten |
| 23 | AI-regio | Standaard | EU-endpoint zodra beschikbaar. | Besloten |
| 24 | Juridisch | Cookiebanner komt; aan de algemene voorwaarden werkt Raph; Don voor de rest | Gebouwd: cookiebanner (alles accepteren, alleen noodzakelijk, instellen), een echte cookieverklaring op /cookies die de cookies uit de code noemt, en /voorwaarden en /privacy als tijdelijke pagina (noindex) tot de teksten er zijn. Het A/B-experiment op de home draait alleen nog met toestemming voor statistiek. | Gebouwd, tekst volgt |
| 25 | De home | Geen antwoord | Standaard: zo laten met disclaimer. | Standaard |
| 26 | Huisstijl deel-visual | "Heb je toch al?" | Klopt: logo en kleuren staan in Aucvy. We gebruiken die. Formaten: vierkant, staand (story) en liggend (link-preview). | Besloten |
| 27 | Beveiligingsupdates | Geen idee, laat Raph aan ons | Technische keuze van Boyd: bijwerken. | Besloten |
| 28 | Sanctum | Mogelijk maken, nu niet van belang | Later. | Later |
| 29 | Larastan | Laat Raph aan ons over | Technische keuze van Boyd. | Besloten |
| 30 | Redis en Horizon | Laat Raph aan ons over | Technische keuze van Boyd; bij livegang als de hosting het biedt. | Besloten |
| 31 | Meilisearch | Prima | Zoeken via Scout met Meilisearch zodra de hosting het draait; tot dan blijft MySQL. | Besloten |
| 32 | Sentry | Fouten loggen met audit trail in het beheerpaneel; updates van Ivy over de status op het dashboard, zoals Izzy bij Eazy | Gebouwd: foutlog in beheer (zelfde soort fout telt op, geheimen gemaskeerd, afhandelen met notitie in het auditlog, heropent vanzelf als hij terugkomt) en een Ivy-kaart op het beheeroverzicht met de belangrijkste signalen, naar het voorbeeld van Izzy. | Gebouwd |
| 33 | Staging | Wij hosten; Don heeft toegang en Raph vraagt het uit | Staging zodra de toegang er is. | Bij Don |
| 34 | Marktdata | Geen antwoord | Standaard: geen bron; de waarde-indicatie blijft een indicatie. | Standaard |
| 35 | Vervoerder | Klaarzetten, komt op termijn | Het contract staat; een echte vervoerder volgt later. | Later |
| 36 | Werkverdeling | Boyd | Boyd doet alle modules. | Besloten |

---

## Vervolgvragen voor Raph

**V1. Hoe snel moet de koper betalen?**
Je schreef "betalen direct". Na winnen gaat de koper meteen naar de betaling. Maar een veiling kan om 22:00 sluiten terwijl de winnaar niet achter zijn scherm zit.
Opties: (a) 1 uur, (b) 24 uur, (c) 48 uur (zoals nu).
Standaard: (b) 24 uur, met een herinnering na 12 uur.
Antwoord:

**V2. Wanneer gaat het geld naar de verkoper?**
Je schreef "vrijgegeven wanneer de klant heeft ontvangen". De koper kan na ontvangst nog een probleem melden.
Opties: (a) direct als de koper ontvangst bevestigt, (b) na bevestiging plus 48 uur om een probleem te melden, (c) zoals nu: na de meldtermijn plus nog 7 dagen.
Standaard: (b). Bevestigt de koper niets, dan geldt een levering na 7 dagen als ontvangen.
Antwoord:

**V3. Welke sms-dienst?**
Mailgun doet geen sms.
Opties: (a) Bird (MessageBird), (b) CM.com (doet ook iDIN, handig als dat via hen gaat), (c) Twilio, (d) anders.
Standaard: (b) als Remco iDIN via CM.com regelt, anders (a).
Antwoord:

**V4. Wat doet de bot bij twijfel over een kavel?**
Opties: (a) terug naar de verkoper met de reden, (b) naar een medewerker, (c) terug naar de verkoper bij een ontbrekend gegeven en naar een medewerker bij twijfel over echtheid of verboden waar.
Standaard: (c). Zo gebouwd; een ander antwoord is een kleine aanpassing.
Antwoord:

**V5. Mailgun: domein en regio**
Nodig zodra je het opzet: het afzenderdomein (bijvoorbeeld mail.aucvy.nl), het afzenderadres, de API-sleutel, de webhooksleutel (Mailgun, onder Webhooks) en of het account in de EU-regio staat. Zet in Mailgun een webhook op https://aucvy.nl/webhooks/mailgun voor delivered, failed en complained.
Antwoord:

**V6. Blog via de Eazyonline-API**
Nodig: een leestoken voor Aucvy (Bearer) en, als die afwijkt van https://eazyonline.nl/api/v1, de URL. De blog leest berichten met type "blog".
Antwoord:

**V7. Remco en iDIN**
Via welke partij loopt iDIN (bijvoorbeeld CM.com, Signicat of een bank), en wanneer zijn er testgegevens?
Antwoord:

---

## Vragen die bij Don liggen

Tot Don antwoordt, geldt de standaardkeuze en loopt de bouw door.

| # | Vraag | Standaard tot het antwoord er is |
| --- | --- | --- |
| 2 | Wie verkoopt en wie factureert, juridisch | Koper betaalt aan het platform, platform factureert de fee aan de verkoper, verkoper factureert de kavel |
| 3 | Btw, marge, verlegging, buitenland | Alleen Nederland; marge waar de verkoper dat aangeeft, anders 21% |
| 5 | Boekhoudkoppeling | Csv-export |
| 6 | Prijzen per pakket, Ivy Boost, opgeld, borgdrempel | Zoals het nu staat |
| 13 | Gerealiseerde prijzen openbaar | Nee |
| 18 | Biedregel bij de reserveprijs | Tillen tot de reserve |
| 24 | Privacyverklaring en cookieverklaring | Geen livegang zonder deze teksten |
| 33 | Toegang tot de hosting voor staging | Geen staging |
| D1 | **Herroepingsrecht (nieuw).** Zakelijke verkopers verkopen online aan particulieren. Bij online verkoop van een bedrijf aan een consument geldt normaal 14 dagen bedenktijd; de uitzondering voor veilingen geldt alleen als de koper er persoonlijk bij kan zijn. Geldt de bedenktijd hier, en zo ja: wie betaalt het terugsturen, en hoe verhoudt het zich tot de afslag en koop direct? | We tonen de wettelijke informatie bij elke koop en bouwen een herroepingsmelding naast de geschilroute, zodra Don dit bevestigt |
| D2 | **Escrow op de site (nieuw).** Mag de site zeggen dat het geld bij Stripe blijft tot de koper ontvangen heeft? Het woord "escrow" of "derdengeldenrekening" gebruiken we niet zonder zijn akkoord. | Wel de feitelijke zin, niet het woord escrow |

---

## Antwoorden van Don (2 november)

De blauwe kleur van Don ging verloren bij het doorsturen; per regel is niet te zien wat van Don en wat van Raph komt. Het geldt als besloten tenzij Raph het tegenspreekt. De volledige uitwerking en de vervolgvragen N1 tot N13 staan in de doc "Aucvy: stand van zaken en aan te leveren".

| Onderwerp | Besluit | Gevolg |
| --- | --- | --- |
| Succesfee (6) | Staffel op jaaromzet verkoper: tot 25K 15%, 25-50K 12,5%, 50-250K 10%, 250-500K 9%, 500K-1M 8%, 1-2,5M 7%, vanaf 2,5M 6%. Geen terugclaimen achteraf. | Vervangt de fee per pakket (N1-N3) |
| Administratiekosten koper (6) | € 15 per factuur | Regel op elke order (N4) |
| Bieden en identificatie (6, 9) | Tot € 250 vrij bieden, daarboven volledig geïdentificeerd | Wijkt af van vraag 9; nieuw antwoord gaat voor (N5) |
| Borg (6) | Algemene borg € 200 ex btw boven € 2.500, € 500 boven € 10.000, verder bespreken | Eén borg per account, via Stripe (N6) |
| Btw (3) | Verkoper kiest per kavel marge of btw; regels overnemen van Troostwijk, Auctivo, OLVM | Keuze in de wizard; buitenland open (N13) |
| Boekhouding (5) | Exact Online via de API | Koppeling na toegang (N10) |
| Prijzen openbaar (13) | Ja, met wie er bood (biednaam, zoals OLVM), ook op het verkopersprofiel | Wijkt af van de standaard (N7) |
| Reserve (18) | Autobod tilt niet naar de reserve | Zo werkt het al (N9) |
| Uitbetaling (7) | Na ontvangst en wederzijdse waardering, Marktplaats-principe | Onderdeel van Stripe Connect (N8) |
| Voorwaarden (24) | Er al, van Yourauc (Remco) | Documenten opvragen (N11) |
| Hosting (33) | Geen antwoord; de software blijft van Yourauc en wordt aan Aucvy verhuurd | Eigendom en hosting bespreken (N12) |
| Wie factureert (2) | Niet beantwoord | Standaard blijft |

---

## Vervolgvragen na de bouw van 4 november

Alles uit de briefing nieuwe functies, de Ivy-briefing en de twee canvassen is gebouwd. Wat hieronder staat is wat van buiten moet komen; tot het er is, geldt de standaardkeuze en werkt het onderdeel in de handmatige of nepstand.

**N14. Meta-app voor Facebook en Instagram**
Plaatsen op een bedrijfspagina en op Instagram vraagt een Meta-app met app-review (één tot drie weken). Nodig: app-id en app-secret (META_APP_ID, META_APP_SECRET). Tot die tijd dekt de deelkit elk kanaal: tekst, beeld in drie formaten en link met herkomst, zelf plaatsen.
Standaard: deelkit.
Antwoord:

**N15. LinkedIn-app**
Plaatsen namens de ingelogde persoon via Share on LinkedIn. Nodig: client-id en client-secret (LINKEDIN_CLIENT_ID, LINKEDIN_CLIENT_SECRET). Bedrijfspagina's vragen een aparte goedkeuring.
Standaard: deelkit.
Antwoord:

**N16. EAN-dienst (Go-UPC)**
Productopzoeking op streepjescode. Go-UPC kost € 74,95 per maand voor 5.000 verzoeken. Advies uit de briefing: eerst met 50 echte labels uit de eigen categorieën testen. Nodig: de sleutel (GO_UPC_KEY). Tot die tijd wordt de code opgeslagen en leest Ivy de typeplaat.
Standaard: geen dienst, wel scannen en typeplaat lezen.
Antwoord:

**N17. LiveKit voor live uitzenden**
Nodig: een LiveKit Cloud-account (pilot ongeveer $ 69 per maand), host, websocket-URL, API-sleutel en secret (LIVEKIT_*). Vooraf bevestigen dat de verwerking in de EU kan. Voorstel uit de briefing: eerst een besloten proef achter de vlag, na Stripe Connect. De browserclient laadt nu van een CDN; als npm-pakket toevoegen kan na jouw akkoord.
Standaard: vlag uit; studio en kijker draaien in oefenstand zonder mediaserver.
Antwoord:

**N18. Mailgun-subdomein voor marketing**
Service en marketing elk een eigen subdomein met SPF, DKIM en DMARC. Nodig: het marketingdomein in Mailgun en de naam van die mailer (AUCVY_MARKETING_MAILER).
Standaard: alles via de servicemailer.
Antwoord:

**N19. Gegevens van Aucvy op facturen**
Op elke factuur staan naam, adres, KVK-nummer en btw-nummer van Aucvy B.V. Nodig: adres, postcode en plaats, KVK, btw-nummer (AUCVY_COMPANY_*). Het factuurvoorstel zelf (verkoper factureert de kavel, Aucvy de administratiekosten en de succesfee) moet de boekhouder toetsen (zie D6).
Standaard: naam Aucvy B.V., adres leeg.
Antwoord:

**N20. Marktplaats als partner**
Automatisch plaatsen mag alleen als API-partner (voorwaarden art. 7.4) en een link in een gratis advertentie alleen tegen betaling (art. 1.3). Vraag Marktplaats schriftelijk of doorverwijzen naar een lopende veiling is toegestaan. Tot die tijd: deelkit, één advertentie per kavel.
Standaard: deelkit.
Antwoord:

**N21. Drempels en tijden van Ivy (besluit 11 uit de Ivy-briefing)**
Stille uren 22:00 tot 08:00, bundelen van biedingen per 15 minuten, interesse één keer per dag, maximaal 5 gastfoto's van 10 MB, gastgesprekken 30 dagen, gastfoto's 24 uur. Alles staat in de instellingen.
Standaard: zoals genoemd.
Antwoord:

**N22. Hot-regel en tellers**
Hot bij de hoogste 10% van de categorie over 48 uur, met minimaal 50 kijkers en 3 bieders. De jurist leest regel en tekst mee voor publicatie (hoofdbriefing p. 27).
Standaard: zoals genoemd, drempels in de instellingen.
Antwoord:

**N23. Chatmodel en budget van Ivy**
Ivy gebruikt een goedkoper model voor eenvoudige vragen en het sterkere voor advies en foto's (AUCVY_AI_CHAT_MODEL_FAST, AUCVY_AI_MODEL), met het bestaande dagbudget per gast, per account en per omgeving. Wil je andere grenzen?
Standaard: zoals nu in de instellingen.
Antwoord:

**N24. Ivy in gesprek met stem, zoals Izzy**
Het zwevende paneel van Ivy werkt sinds 5 november zoals Izzy bij Eazyonline: antwoord als stroom met de stappen die ze zet, opmaak, stoppen, opnieuw, kopiëren, nieuw gesprek en doorsturen naar een pagina. Voorlezen en inspreken gebruiken nu de spraak van de browser (gratis, geen extra partij, kwaliteit wisselt per browser). Izzy heeft daarnaast een handsfree gesprek met een eigen stem via OpenAI (spraak naar tekst en tekst naar spraak). Wil je dat ook voor Ivy? Dat vraagt een OpenAI-sleutel, kosten per minuut en een extra verwerker in de privacyverklaring.
Standaard: de spraak van de browser, geen OpenAI.
Antwoord:

**N25. Ivy op de echte koppeling in productie**
Het gesprek met Ivy heeft een eigen schakelaar (AUCVY_IVY_CHAT_PROVIDER). Op `fake` geeft ze vaste antwoorden op trefwoorden; op `anthropic` werkt Claude met de tools. Lokaal staat hij nu op `anthropic`. Een beurt kost ongeveer 3 tot 8 cent met het huidige model; het dagbudget per account (nu € 5) is de rem. Mag productie ook op `anthropic`?
Standaard: productie blijft op `fake` tot je ja zegt.
Antwoord:

---

## Nieuwe vragen voor Don (na de bouw van 4 november)

| # | Vraag | Standaard tot het antwoord er is |
| --- | --- | --- |
| D3 | Mailindeling: 37 service, 6 activatie, 3 campagne. Klopt de indeling, en mag activatie zonder uitdrukkelijke ja (afmelden mogelijk) terwijl campagne een ja vraagt? | Zoals voorgesteld; instelbaar |
| D4 | Automatisch bieden via Ivy: het mandaat ("Bevestig: automatisch bieden tot X. Elk bod is bindend...") en wat in de veilingvoorwaarden moet | Gebouwd, tekstversie vastgelegd; fase 5 niet live zonder akkoord |
| D5 | Mag een verkoper de reserve verlagen tijdens de veiling? | Vlag uit; alleen de melding "Jouw beslissing" |
| D6 | Factuurvoorstel: verkoper factureert de kavel aan de koper (opgemaakt door Aucvy namens de verkoper), Aucvy factureert administratiekosten aan de koper en succesfee aan de verkoper; creditnota bij prijscorrectie; margeregeling zonder btw | Zo gebouwd; de boekhouder toetst |
| D7 | Privacyverklaring aanvullen voor chat, foto's en AI-verwerking; AI-melding in het eerste bericht (artikel 50 AI Act) | Melding staat erin; tekst van de verklaring wacht |
| D8 | Bewaartermijnen: gesprekken 12 maanden, gastgesprekken 30 dagen, gastfoto's 24 uur, voorstellen zo lang als order en audit | Zoals voorgesteld |
| D9 | Mag een koper de aankoopfactuur van de verkoper inzien (besluit 9 hoofdbriefing)? | Kavelfactuur zichtbaar voor beide partijen; aankoopfactuur (document) na gunning |
| D10 | Registreren vraagt sinds 9 november een verplicht akkoord op de voorwaarden en de privacyverklaring (zoals in het ontwerp). Moet het moment en de versie van dat akkoord per account worden bewaard? | Het vinkje is verplicht; het akkoord wordt nog niet apart vastgelegd |
