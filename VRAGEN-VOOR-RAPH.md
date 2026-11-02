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
