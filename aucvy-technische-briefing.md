# Aucvy — Technische briefing & wireframes · Laravel-build

> Bouwbriefing voor Boyd (frontend) & Yael (backend) — v1
> Bron: `aucvy-technische-briefing_2.html`

De negen designbestanden laten zien hoe Aucvy voelt. Dit document beschrijft wat eronder moet zitten: datamodel, biedmotor, endpoints, componenten en per scherm een wireframe met de data en states die erbij horen. Geschreven om mee te bouwen, niet om mee te presenteren.

**Rolmarkering:** `[Boyd · frontend]` · `[Yael · backend]` · `[samen]`

---

## Inhoud

**Kader**
1. [Waarom dit document](#1--waarom-dit-document)
2. [Scope & aannames](#2--scope--aannames)
   - 2b. [De twee designgeneraties](#2b--de-twee-designgeneraties)
3. [Stack & architectuur](#3--stack--architectuur)
4. [Repo, conventies, DoD](#4--repo-conventies-en-definition-of-done)

**Backend · Yael**

5. [Datamodel](#5--datamodel-yael)
6. [De biedmotor](#6--de-biedmotor-yael)
7. [Routes & endpoints](#7--routes--endpoints-yael)
8. [Ivy: de AI-laag](#8--ivy-de-ai-laag-yael)
9. [Orders, geld en geschillen](#9--orders-geld-en-geschillen-yael)

**Frontend · Boyd**

10. [Tokens & componentbibliotheek](#10--tokens--componentbibliotheek-boyd)
11. [Realtime in de UI](#11--realtime-in-de-ui-boyd)
12. [Mobiel & toegankelijkheid](#12--mobiel--toegankelijkheid-boyd)

**Schermen**

13. [De schermen](#13--de-schermen) — [Home](#131--home-boyd) · [Ontdek](#132--ontdek-en-zoeken-boyd--yael) · [Kavel + bieden](#133--kavel-en-bieden-samen--dit-is-het-hart) · [Verkoopfunnel](#134--verkoopfunnel-boyd--yael) · [Dashboard](#135--dashboard-boyd--yael) · [Verkoper, zekerheid, account](#136--verkopersprofiel-zekerheid-account-boyd)

**Uitvoering**

14. [Integraties](#14--integraties)
15. [Security & AVG](#15--security--avg)
16. [Testen & QA](#16--testen--qa)
17. [Bouwvolgorde](#17--bouwvolgorde)
18. [Open beslissingen](#18--open-beslissingen)
19. [Wat hierna komt](#19--wat-hierna-komt)

---

## 1 · Waarom dit document

Er ligt een complete visuele demo: acht doorklikbare pagina's, een design system met tokens, en een blauwdruk voor de biedmotor. Wat ontbrak was de vertaalslag naar de codebase — welke tabellen, welke endpoints, welk component hoort bij welk stuk scherm, en in welke volgorde je dat bouwt zonder elkaar te blokkeren.

Dit document is die vertaalslag. Het is bewust specifiek: waar een keuze al gemaakt kan worden, staat hij hier gemaakt, met de reden erbij. Waar hij nog niet gemaakt kan worden, staat hij in hoofdstuk 18 als open beslissing met een deadline. Als je tijdens het bouwen iets tegenkomt dat hier anders staat dan wat werkt: **het document volgt de code, niet andersom** — meld het en we passen het aan.

### Wat je naast dit document nodig hebt

| Bron | Waarvoor |
|---|---|
| `aucvy-design-system.html` | Tokens, componentstates, kleurcontrast. Leidend voor hoofdstuk 10. |
| `aucvy-kavel-v2.html` | Het gedrag van bieden, afslag en koop-direct. Leidend voor hoofdstuk 6 en 13.3. |
| `aucvy-funnel-verkopen.html` | De acht stappen, de compleetheidsscore, waar het account gevraagd wordt. |
| `aucvy-dashboard.html` | AI-team, buyer intent, forecast, growth coach. |
| `aucvy-blauwdruk.html` | Architectuurdenkwerk en datastrategie achter de biedmotor. |
| `aucvy-mobiel-ux-buildlijst.md` | Mobiele correcties die in de build meegenomen moeten worden. |
| Preview v4 (netlify, 12 schermen) | Categorietaxonomie, filterfacetten en de volledige verificatie-eisen. Op het punt van zekerheid conflicteert hij met de designkeuken — zie hoofdstuk 2b. |

---

## 2 · Scope & aannames

### Wat de MVP is

Eén doel: **een echte kavel kan van upload tot uitbetaling doorlopen, met echte biedingen, echt geld en een echte geschilroute.** Alles wat daar niet aan bijdraagt schuift naar hoofdstuk 19.

**In de MVP**

- Registratie, login, verificatie (KVK + identiteit)
- Kavel plaatsen via de funnel, inclusief media en documenten
- Veiling, afslag en koop-direct — alle drie
- Biedmotor met proxy-bieden en verlengregel
- Ontdekken, filteren, zoeken met Ivy
- Orders, betalen, uitbetaling met houdtermijn
- Reviews en de geschilroute
- Dashboard voor koper en verkoper
- Ivy-laag v1: herkenning, advertentietekst, waarde-indicatie, biedadvies

**Niet in de MVP**

- Liquidatieplanner en veilingsimulator
- Inventory scanner en video studio
- Trust index als berekend cijfer (de losse trust-signalen wél)
- Fraud detection, sustainability, commerce graph
- White-label, publieke API, ERP-koppelingen
- Native apps — web push volstaat, PWA-installatie wel
- Meertaligheid — Nederlands, maar wel volledig door `lang/` heen
- Escrow als claim (zie hoofdstuk 9 en 18)

### Aannames die al gemaakt zijn

| Onderwerp | Aanname | Waarom |
|---|---|---|
| Frontend | Inertia 2 + React 19 + TypeScript | De demo's zijn al React. Boyd hoeft niets te hertekenen, alleen te ontvlechten naar componenten. Livewire zou de biedmotor-UI en de Ivy-streams onnodig moeilijk maken. |
| Database | PostgreSQL 16 | `SELECT … FOR UPDATE` gedraagt zich voorspelbaarder onder druk, `jsonb` is nodig voor AI-output en categorie-attributen, en partiële indexen schelen veel op de lopende-kavels-query. |
| Realtime | Laravel Reverb | Zelf te hosten, geen kosten per bericht, en het slotmoment van een veiling is precies het moment waarop je geen externe rate limit wilt raken. |
| Geld | Alles in centen, `bigint`, nooit `float` | Niet onderhandelbaar. |
| Opgeld koper | Blijft 0% in de MVP | Staat zo in alle designs en is een verkoopargument. Het veld bestaat wel in het datamodel, zodat het later aan te zetten is zonder migratie. |

> ⚠️ **Bevestigen vóór sprint 1.** De eerste twee aannames zijn structureel. Als Postgres of Inertia niet kan — hosting, kennis, bestaande afspraken — dan liever nu horen dan in week drie. De rest van dit document blijft geldig; alleen hoofdstuk 10 en 11 veranderen dan.

---

## 2b · De twee designgeneraties

Er bestaan twee versies van dit ontwerp, en ze spreken elkaar op één punt tegen. Dat moet opgelost zijn vóórdat er iets gebouwd wordt, want het verschil zit niet in de vormgeving maar in **wat het platform belooft**.

| | Preview v4 (netlify, 12 schermen) | Designkeuken (de HTML-bestanden) |
|---|---|---|
| Zekerheid | "Aucvy Garantie": escrow met naam en toenaam, geld terug bij niet-zoals-beschreven, controletermijn van 3 werkdagen, gereguleerde betaaldienst, betaallogo's. | Geen escrow-claim. Wat geregeld is staat er, escrow en technische keuring staan expliciet onder "waar we nog aan werken". |
| Cijfers | 18.240 veilingen, € 46,8 mln uitbetaald, 92% sell-through, 4,8★ — met een kleine regel eronder dat het demo-cijfers zijn. | Geen platformcijfers. |
| Assistent | Eén Ivy, met vaardigheden erachter. | Ivy als coördinator van zes zichtbare specialisten. |
| Verificatie | Uitgewerkt: ID, liveness-selfie, borg boven een drempel, KvK, UBO, IBAN-tenaamstelling, Trust Index. | Aanwezig als stap, niet uitgewerkt. |
| Schermen | 12 — inclusief Verkooproute, Advertenties en FAQ. | 8. |

> 🛑 **Dit is een beslissing, geen samenvoeging.** De escrowtekst uit v4 kan pas live als de derdengeldenconstructie en de vergunningvraag geregeld zijn; "je geld terug" is een juridische toezegging, geen copy. De demo-cijfers zijn nu illustratief maar staan als feiten op het scherm — dat is precies het soort claim dat je één keer maakt en daarna niet meer kwijtraakt.
>
> **Advies:** bouw de **mechaniek** uit v4 (houdtermijn, bemiddeling, terugbetaling) en gebruik de **toon** van de designkeuken, tot de constructie er echt staat. Zie hoofdstuk 18.

De rest van v4 is pure winst en is hieronder verwerkt: de categorietaxonomie, de exacte filters en de verificatie-eisen stonden nog als open punt en zijn in v4 al beslist.

### Wat v4 heeft en deze briefing nog niet dekt

| Scherm | Wat het is | Voorstel |
|---|---|---|
| Verkooproute | De uitlegpagina voor verkopers, los van de funnel zelf. | Statische Inertia-pagina, halve dag werk. **In de MVP.** |
| Advertenties | Campagnebeheer — Nova's kanalen, budgetten en voorstellen die op akkoord wachten. | Raakt promotiebudget en dus geld. **Niet in de MVP**; de funnelstap "waar wil je publiceren" volstaat eerst. Ongeveer een sprint als het wél nodig is. |
| FAQ | Losse vragenpagina naast de zekerheidspagina. | Statisch, met een accordeon. **In de MVP.** |

---

## 3 · Stack & architectuur

| Laag | Keuze | Notitie |
|---|---|---|
| Runtime | PHP 8.4, Laravel 12 | Strict types aan in alle nieuwe bestanden. |
| Database | PostgreSQL 16 | Eén primaire, read replica pas nodig na launch. |
| Cache / locks / queue | Redis 7 | Aparte databases per doel: `0` cache, `1` queue, `2` locks. |
| Queues | Horizon | Vier supervisors: `realtime`, `default`, `ai`, `media`. |
| Websockets | Reverb, eigen proces | Echo op de client, fallback naar polling van `/state`. |
| Zoeken | Meilisearch via Scout | Facetten voor categorie, status, prijs, regio, verkoopvorm. |
| Opslag | S3-compatible (R2) + imgproxy | Originelen privé, varianten publiek, documenten altijd via signed URL. |
| Frontend | Inertia 2, React 19, TS, Vite, Tailwind 4 | Tokens uit het design system als CSS-variabelen, Tailwind leest ze uit. |
| Kwaliteit | Pest 3, Larastan 6, Pint, Rector | CI blokkeert de merge, niet de mens. |
| Hosting | Forge of Ploi op Hetzner | Web, worker en Reverb gescheiden; Postgres en Redis managed of op een eigen node. |

### Waarom vier queues

De biedmotor mag nooit achter een AI-taak in de rij staan. Een fotoanalyse van veertig seconden achter een `BidPlaced`-broadcast betekent dat iemand veertig seconden op verouderde informatie biedt. Daarom fysiek gescheiden supervisors met eigen workers:

| Queue | Wat erin gaat | Eis |
|---|---|---|
| `realtime` | Biedevents uitzenden, veilingen sluiten, overboden-notificaties | Latency onder 2s, minstens 4 workers, nooit langlopende jobs |
| `default` | Mail, orders, uitbetalingen, exports | Normale doorlooptijd |
| `ai` | Alle Ivy-taken | Mag traag zijn, aparte timeout van 180s, eigen budgetbewaking |
| `media` | Beeldvarianten, video, checksums, virusscan | Mag traag zijn, hoog geheugen |

### Vorm van de codebase

Geen volledige DDD-structuur — dat is voor dit team en deze fase overkill. Wél de biedmotor als geïsoleerde module, omdat dat het enige onderdeel is waar een fout direct geld kost en waar je later misschien anders wil deployen.

```
app/
  Auctions/            ← de motor. Alleen hier mag een bod ontstaan.
    Actions/            PlaceBid, PlaceAutoBid, AcceptDutchPrice, BuyNow, CloseAuction
    Events/             BidPlaced, AuctionExtended, AuctionClosed, PriceDropped
    Jobs/               CloseAuctionJob, BroadcastAuctionEvent
    Support/            IncrementTable, ProxyResolver, DutchPriceCalculator
    Exceptions/         BidRejected (met reden-enum)
  Ivy/                 ← alle AI. Nooit een LLM-call buiten deze map.
    Agents/             IvyAgent, AtlasAgent, NovaAgent, FinnAgent, FlowAgent, LexAgent
    Contracts/          AiTask, AiResult
    Jobs/               RunAiTask
    Guards/             ConfidencePolicy, BudgetGuard, OutputValidator
  Models/              Eloquent, dun. Geen businesslogica in models.
  Http/
    Controllers/        Per resource, gebruiken Actions
    Requests/           Validatie hoort hier, niet in de controller
    Resources/          JSON-vormen voor de realtime endpoints
  Support/Money/       Bedragen, btw, marge, fees. Deterministisch PHP.
resources/js/
  Pages/                Inertia-pagina's, 1 op 1 met de routes
  Components/           Gedeelde bibliotheek (hoofdstuk 10)
  Features/auction/     useAuction-hook, biedpaneel, afslagpaneel, historie
  Features/ivy/         Launcher, paneel, antwoordkaarten
```

> ✅ **Eén regel die veel ellende scheelt.** Een bod ontstaat uitsluitend via `App\Auctions\Actions\PlaceBid`. Niet in een controller, niet in een observer, niet in een seeder, niet in Tinker. Als je een tweede plek nodig denkt te hebben, is het antwoord een extra Action die intern `PlaceBid` aanroept.

---

## 4 · Repo, conventies en definition of done

### Werkafspraken

- **Branches:** `main` is altijd deploybaar. Feature-branches heten `feat/kavel-biedpaneel`, fixes `fix/…`. Squash-merge.
- **PR's blijven klein.** Boven de 400 gewijzigde regels wordt reviewen raden. Liever drie PR's dan één.
- **Taal:** code, tabellen, kolommen en variabelen in het Engels; gebruikersteksten in het Nederlands via `lang/nl/`. Dus `Lot` in de code, "kavel" op het scherm.
- **Migraties zijn onveranderlijk** zodra ze op staging staan. Corrigeren doe je met een nieuwe migratie.
- **Geen bedragen als float, nergens.** Kolomnaam eindigt altijd op `_cents`, zodat een fout meteen opvalt.

### Definition of done

Een taak is af als dit allemaal klopt — niet als het werkt op de machine van degene die het bouwde.

1. Feature-test in Pest die de bedoeling vastlegt, niet de implementatie.
2. Larastan en Pint groen; CI groen.
3. Bij UI: getest op 390px én desktop, focus zichtbaar op alles wat bedienbaar is, axe-core zonder overtredingen.
4. Bij UI: lege staat, laadstaat, foutstaat en succesvolle staat bestaan alle vier. Een scherm zonder lege staat is niet af.
5. Bij geld of biedingen: een test die de faalroute bewijst, niet alleen de gelukkige.
6. Zichtbaar op staging, en de ander heeft er één keer doorheen geklikt.

---

## 5 · Datamodel [Yael]

Onderstaande tabellen zijn de MVP. Kolommen die je later nodig hebt maar nu nog niet vult staan er bewust al in, zodat je geen migratie op een draaiende veiling hoeft te doen. Alles in centen, alles met `created_at`/`updated_at`, alle vreemde sleutels met een expliciete `on delete`-regel.

### Samenhang in het kort

```
company 1─n user
user ─n lot ──1 auction ──n bid
             │           └n auto_bid
             │           └n auction_event   (append-only, met sequence)
             ├n lot_media
             ├n lot_document
             └1 order ──1 payment
                       ├1 payout
                       ├n dispute
                       └1 review

ai_run      polymorf aan lot / auction / user   (elke AI-uitkomst, met confidence)
ai_insight  per user                            (de signalen op het dashboard)
```

### `lots`

| Kolom | Type | Toelichting |
|---|---|---|
| `id` | bigint | |
| `public_id` | string, uniek | Kavelnummer dat de gebruiker ziet, bv. `8842`. Nooit het autonummer tonen. |
| `seller_id`, `company_id` | fk | Company is nullable — particulieren mogen ook verkopen. |
| `category_id`, `location_id` | fk | |
| `title`, `slug`, `description` | string / text | Slug bevat `public_id` zodat titelwijziging de URL niet breekt. |
| `brand`, `model`, `year`, `quantity` | string / int | Vullen kan door Ivy of door de verkoper; herkomst staat in `attributes`. |
| `condition` | enum | `new · like_new · good · used · defective` |
| `attributes` | jsonb | Categoriespecifiek (urenstand, typeplaatje, vermogen). Per veld `{value, source: ai\|seller, confidence}`. |
| `status` | enum | `draft · pending_review · scheduled · live · closing · sold · unsold · cancelled` |
| `sale_type` | enum | `auction · dutch · buy_now` |
| `vat_regime` | enum | `margin · vat_21` — bepaalt de kostenregel op de kavelpagina. |
| `completeness_score` | smallint | 0–100, berekend in PHP (hoofdstuk 8), niet door de LLM. |
| `value_min_cents`, `value_max_cents`, `value_confidence` | bigint / enum | Ivy-waarderange. `low · medium · high`. Altijd samen tonen of niet tonen. |
| `pickup_only`, `transport_from_cents` | bool / bigint | Ontbreekt transportinfo, dan is dat een dashboardsignaal (Flow). |
| `published_at`, `deleted_at` | timestamp | Soft delete verplicht — een verwijderde kavel met biedhistorie moet reconstrueerbaar blijven. |

**Indexen:** `(status, published_at)` partieel op `status = 'live'`, plus `(category_id, status)` en een GIN-index op `attributes`.

### Categorieën — de taxonomie staat al vast

Preview v4 heeft deze beslissing al gemaakt: acht hoofdcategorieën met subcategorieën. Overnemen als seeder, dan is de funnel én de filterkant meteen gedekt.

| Hoofdcategorie | Subcategorieën |
|---|---|
| Machines & techniek | Graafmachines · heftrucks · metaalbewerking · houtbewerking · compressoren & pompen |
| Horeca & keuken | Koeling & vriescellen · complete keukens · kookapparatuur · horecameubilair |
| Kantoor & inventaris | Bureaustoelen · bureaus & kasten · IT & elektronica · complete inventaris |
| Voertuigen & transport | Bestelwagens · personenauto's · aanhangers & opleggers |
| Bouw & materieel | Steigers · gereedschap · grondverzet |
| Winkel & voorraad | Kleding & mode · restpartijen · displays & inrichting |
| Boten & recreatie | Zeil- & motorjachten · sloepen · campers & caravans |
| Elektronica | Camera's & optiek · computers · audio & video |

Per subcategorie hoort een `attributes_schema`: bij graafmachines urenstand en bouwjaar, bij keukens merk en afmetingen, bij voertuigen kenteken en kilometerstand. **Begin met drie categorieën volledig uitgewerkt en de rest generiek** — een half schema is erger dan geen schema, want het suggereert volledigheid die er niet is.

### `auctions`

Eén rij per kavel met een verkooproute. Deze rij is het object waarop gelockt wordt; hij is bewust smal zodat de lock kort duurt.

| Kolom | Type | Toelichting |
|---|---|---|
| `lot_id` | fk, uniek | |
| `starts_at`, `ends_at`, `original_ends_at` | timestamptz | `ends_at` schuift bij verlenging; `original_ends_at` nooit — nodig voor rapportage en voor "3× verlengd". |
| `start_price_cents`, `reserve_price_cents` | bigint | Reserve is nooit publiek. Wel publiek: of hij gehaald is. |
| `current_price_cents` | bigint | Afgeleide waarde, maar opgeslagen — anders kost elke lijstweergave een aggregatie. |
| `bid_count`, `bidder_count` | int | Idem: bijhouden in dezelfde transactie als het bod. |
| `increment_table` | jsonb | Staffel: tot €500 stap €10, tot €5.000 stap €50, daarboven €250. Per veiling vastgelegd, niet uit config — anders verandert de staffel met terugwerkende kracht. |
| `anti_snipe_window_s`, `anti_snipe_extend_s` | int | Standaard 30 en 60. Per veiling opgeslagen om dezelfde reden. |
| `extension_count` | int | Toont "2× verlengd door biedingen in de slotfase". |
| `buy_now_price_cents`, `buy_now_active` | bigint / bool | Vervalt zodra er boven de vaste prijs geboden is. Dat is een transitie, geen berekening — leg hem vast. |
| `dutch_start_cents`, `dutch_floor_cents`, `dutch_step_cents`, `dutch_interval_s` | bigint / int | De afslag. Geen tikkende schrijfacties: de prijs is een functie van de tijd. |
| `last_sequence` | bigint | Teller voor `auction_events`. Verhoogt binnen de lock. **Dit is wat reconnects redt.** |
| `winner_id`, `winning_bid_id`, `closed_at` | fk / timestamp | Alleen gevuld door `CloseAuction`. |

### `bids`, `auto_bids`, `auction_events`

| Tabel | Kolommen | Regels |
|---|---|---|
| `bids` | `auction_id, user_id, amount_cents, type(manual\|proxy\|buy_now\|dutch), status(winning\|outbid\|rejected), sequence, idempotency_key, placed_at, ip, user_agent` | Uniek op `idempotency_key`. Uniek op `(auction_id, sequence)`. Nooit updaten behalve `status`. Nooit verwijderen. |
| `auto_bids` | `auction_id, user_id, max_amount_cents, active, cancelled_at` | Uniek op `(auction_id, user_id)` waar `active`. Verhogen mag, verlagen niet — dat is een nieuw maximum onder een bestaand bod en dat kan niet. |
| `auction_events` | `auction_id, sequence, type, payload jsonb, created_at` | Append-only. Types: `bid_placed, outbid, extended, price_dropped, buy_now_disabled, closed, cancelled`. Dit is de bron voor replay én voor het auditlog. |

> ⚠️ **Waarom een sequence en geen timestamp.** Twee events in dezelfde milliseconde zijn niet te ordenen op tijd. De client die na een netwerkdip terugkomt vraagt "alles na 431" en moet een gat kunnen zien. Met timestamps kan dat niet betrouwbaar.

### Overige tabellen in het kort

| Tabel | Kern |
|---|---|
| `users` | `type(private\|business), kyc_status, kyc_verified_at, can_bid(bool, afgeleid), notification_prefs jsonb, display_name` — publiek tonen we "R. Jansen", nooit de volledige naam. |
| `companies` | `kvk_number, vat_number, verified_at, plan(start\|pro\|enterprise), fee_percentage, payout_iban_last4` |
| `lot_media` | `type(image\|video\|tour360), path, variants jsonb, width, height, position, alt, checksum` — checksum nu al vullen; dat is later je dubbele-foto-detectie. |
| `lot_documents` | `kind(invoice\|warranty\|ownership\|manual\|other), path, ai_checked_at, ai_findings jsonb` — altijd achter een signed URL, nooit publiek. |
| `orders` | `lot_id, auction_id, buyer_id, seller_id, hammer_cents, buyer_premium_cents(0), transport_cents, vat_cents, total_cents, status, payment_deadline_at` |
| `payments` / `payouts` | Provider-id, status, webhookpayload; payout met `hold_until` — de 48-uursmeldtermijn uit de zekerheidspagina is een kolom, geen belofte. |
| `disputes` | `order_id, reported_by, reason, evidence jsonb, status, seller_responded_at, sla_due_at, resolution, handled_by` |
| `reviews` | `order_id(uniek), rating 1–5, body, seller_reply, published_at` — alleen aan te maken bij een order met status `completed`. Verwijderen kan niet, ook niet op verzoek. |
| `viewings` | Bezichtigingen: `lot_id, user_id, slot_at, status`. Adres pas per mail ná bevestiging. |
| `saved_searches` | `query jsonb, interpreted jsonb, channel(email\|push), last_run_at` — dit is Ivy's "ik bewaak de markt voor je". |
| `ai_runs` | `subject_type, subject_id, agent, task, model, input jsonb, output jsonb, confidence, tokens_in/out, cost_cents, latency_ms, status` |
| `ai_insights` | `user_id, agent, type, title, body, action_route, severity, valid_until, dismissed_at` — de kaarten op het dashboard. |
| `audit_logs` | `actor_id, action, subject_type, subject_id, before jsonb, after jsonb, ip`. Append-only. |

> ✅ **Sla vanaf dag één op wat je later niet kunt reconstrueren.** Aantal bieders, biedverloop over tijd, welk kanaal de bezoeker bracht, compleetheidsscore bij publicatie, eindprijs, of er een geschil volgde. Dit is de dataset waarmee Ivy's waardebepaling over een jaar écht wordt. Je hebt hem niet meer als je nu vergeet te loggen.

---

## 6 · De biedmotor [Yael]

Dit is het enige onderdeel waar een bug direct geld kost en vertrouwen breekt. **Bouw het eerst, bouw het met tests vóór de UI er is, en laat het niet door twee mensen tegelijk aanraken.**

### De vier valkuilen en hun oplossing

| Valkuil | Wat er misgaat | Oplossing |
|---|---|---|
| Race condition | Twee biedingen van €9.000 komen tegelijk binnen, lezen allebei "huidig €8.900" en winnen allebei. | Rij-lock op `auctions` met `lockForUpdate()`, in één transactie. Nooit optimistisch op het schrijfpad. |
| Sniping | Bod op 0,5 seconde voor sluiting; niemand kan reageren, verkopers krijgen structureel minder. | Bod binnen 30s van het einde verlengt met 60s. Onbeperkt herhaalbaar, teller zichtbaar in de UI. |
| Proxy-bieden | Twee automatische maxima tegen elkaar: naïef word je een oneindige lus of wint de verkeerde. | Uitkomst in één keer berekenen: hoogste maximum wint, prijs is tweede maximum + één stap, gecapt op het hoogste maximum. |
| Verbinding weg | Client mist events, toont een oude stand, iemand biedt op verkeerde informatie. | Oplopende `sequence` per veiling. Bij reconnect alles ophalen vanaf het laatste nummer; bij een gat volledige herlaad vóór het biedpaneel weer actief wordt. |

### `PlaceBid` — de enige plek waar een bod ontstaat

```php
public function handle(Auction $auction, User $user, int $amountCents, string $idempotencyKey): Bid
{
    // 1. Idempotentie vóór de lock: dubbele submit of retry geeft hetzelfde bod terug.
    if ($existing = Bid::firstWhere('idempotency_key', $idempotencyKey)) return $existing;

    return DB::transaction(function () use ($auction, $user, $amountCents, $idempotencyKey) {

        $auction = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();

        // 2. Poortwachters — elk faalgeval een eigen reden-enum, want de UI toont ze los.
        $this->guardOpen($auction);              // status live, now < ends_at
        $this->guardNotSeller($auction, $user);
        $this->guardVerified($user);             // kyc_status = verified
        $this->guardIncrement($auction, $amountCents);

        // 3. Proxy: bepaal in één keer wie leidt en tegen welke prijs.
        $outcome = app(ProxyResolver::class)->resolve($auction, $user, $amountCents);

        // 4. Anti-sniping vóór het schrijven, zodat de nieuwe eindtijd in hetzelfde event zit.
        if ($auction->ends_at->diffInSeconds(now()) <= $auction->anti_snipe_window_s) {
            $auction->ends_at = $auction->ends_at->addSeconds($auction->anti_snipe_extend_s);
            $auction->extension_count++;
        }

        // 5. Schrijven: bod, stand, teller, event — alles binnen dezelfde lock.
        $bid = $this->write($auction, $outcome, $idempotencyKey);
        $this->event($auction, 'bid_placed', $outcome->toPayload());

        // 6. Uitzenden pas ná commit. Anders zendt hij een stand uit die nog kan terugdraaien.
        DB::afterCommit(fn () => BidPlaced::dispatch($bid));

        return $bid;
    }, attempts: 3);
}
```

> 🛑 **Drie dingen die hier fout gaan als je ze anders doet.** Uitzenden binnen de transactie stuurt standen rond die nog kunnen terugrollen. De lock op `bids` in plaats van op `auctions` lost niets op, want twee nieuwe rijen blokkeren elkaar niet. En een `where('amount', '>', …)`-check buiten de transactie is geen bescherming, alleen een kleinere kans.

### Proxy-bieden, uitgeschreven

Iedere deelnemer heeft óf een handmatig bod, óf een maximum waar Ivy binnen meestapt. De uitkomst bereken je niet stap voor stap maar in één keer:

1. Verzamel alle actieve maxima plus het nieuwe bod.
2. De hoogste is de leidende bieder. Gelijkspel? De vroegste inzending wint — vandaar dat `placed_at` en `sequence` ertoe doen.
3. De prijs wordt het op één na hoogste maximum plus één biedstap, nooit hoger dan het hoogste maximum zelf.
4. Is er maar één deelnemer, dan is de prijs de startprijs of het minimum.
5. Schrijf één `bid`-rij per prijswijziging, met `type = proxy` voor automatisch gezette biedingen — de biedhistorie in de UI toont "· automatisch" en dat moet kloppen.

### Sluiten zonder gaten

```php
// Bij publicatie én bij elke verlenging opnieuw ingepland.
CloseAuctionJob::dispatch($auction)->delay($auction->ends_at)->onQueue('realtime');

// De job checkt zelf of hij nog actueel is — de eindtijd kan zijn opgeschoven.
public function handle(): void
{
    $auction = Auction::whereKey($this->id)->lockForUpdate()->first();
    if ($auction->ends_at->isFuture()) {           // verlengd sinds inplannen
        static::dispatch($auction)->delay($auction->ends_at);
        return;
    }
    // winnaar vastleggen, order aanmaken, event schrijven, beide partijen mailen
}
```

Daarnaast een vangnet: `php artisan auctions:close-overdue` elke minuut via de scheduler, voor veilingen die door een herstart of een verloren job zijn blijven hangen. Die vindt normaal niets. Als hij structureel wél iets vindt, is dat een incident, geen routine — log het als zodanig.

### Afslag en koop-direct

**Afslag (Nederlandse veiling)**
De prijs is een pure functie van de tijd: `max(floor, start − step × floor((now − started_at) / interval))`. Geen scheduler die elke twintig seconden schrijft. De client telt zelf af met dezelfde formule; de server rekent bij acceptatie opnieuw en dát bedrag geldt. Eerste klik wint — afgedwongen door dezelfde rij-lock plus een unieke index op `winner_id`.

**Koop-direct**
Zelfde lock. Zodra er boven `buy_now_price_cents` geboden wordt, zet je `buy_now_active = false` en schrijf je het event `buy_now_disabled`, zodat elke open client de knop ziet verdwijnen mét uitleg in plaats van een fout bij het klikken.

**Bevestigingsstap**
Uit de mobiele buildlijst: **geen schuifknop**. Koop-direct en afslag-accepteren gaan via een knop plus een expliciete bevestiging. Per ongeluk €13.750 uitgeven mag niet één tik kosten.

### Rate limiting en misbruik

- Maximaal 10 biedpogingen per gebruiker per veiling per 10 seconden (`RateLimiter`, Redis).
- Maximaal 3 gelijktijdige websocket-verbindingen per gebruiker per veiling.
- Eigen kavel bieden is geblokkeerd, net als accounts van hetzelfde bedrijf — shill bidding is een reputatierisico dat je niet achteraf repareert.
- Elk bod legt `ip` en `user_agent` vast. Niet om te tonen, wel om een patroon te kunnen bewijzen.

### Wat de biedmotor bewezen moet hebben vóór hij live gaat

| Test | Verwachting |
|---|---|
| 500 gelijktijdige bieders op één kavel (k6 of Artillery) | p95 accepteren onder 300ms, geen verloren bod, geen dubbele winnaar |
| Twee identieke biedingen op dezelfde milliseconde | Eén wint deterministisch, de ander krijgt `BidRejected::tooLow` |
| Proxy tegen proxy | Hoogste maximum wint tegen tweede maximum + één stap |
| Bod op t−5s, herhaald | Elke keer 60s verlenging, teller loopt op, sluiting gebeurt pas als het echt stil is |
| Client 30s offline, dan terug | Replay vanaf `sequence` herstelt exact dezelfde stand als een volledige herlaad |
| Reserve niet gehaald | Status `unsold`, geen order, verkoper krijgt de kavel terug |

---

## 7 · Routes & endpoints [Yael]

### Paginaroutes (Inertia)

| URL | Inertia-pagina | Kernprops |
|---|---|---|
| `/` | `Home` | Uitgelichte kavels, live tellers, categorieën |
| `/veilingen` | `Lots/Index` | Gepagineerde kavels, facetten, actieve filters uit de querystring |
| `/veilingen/{category:slug}` | `Lots/Index` | Zelfde pagina, categorie voorgeselecteerd |
| `/kavel/{lot:slug}` | `Lots/Show` | Kavel, veilingstand, media, documenten, verkoper, vergelijkbare kavels |
| `/zoeken` | `Search/Ivy` | Vraag, interpretatie, matches met uitleg |
| `/verkopen` | `Sell/Funnel` | Concept-id, huidige stap, compleetheidsscore |
| `/verkoper/{company:slug}` | `Sellers/Show` | Profiel, historie, reviews inclusief de kritische |
| `/zekerheid` | `Trust/Index` | Statisch, met de geschilroute en de "waar we nog aan werken"-lijst |
| `/dashboard` | `Dashboard/Index` | Signalen, teamfilter, intent, forecast, taken |
| `/dashboard/kavels` · `/biedingen` · `/orders` · `/uitbetalingen` · `/geschillen` | `Dashboard/*` | |
| `/account/verificatie` | `Account/Verification` | Status per stap, wat nog nodig is vóór het eerste bod |

### Realtime- en actie-endpoints

Deze staan in `routes/api.php` achter sessieauth (`web`-middleware, geen tokens in de MVP). Ze geven JSON terug, geen Inertia-response — het biedpaneel mag niet de hele pagina verversen.

| Methode & pad | Doet | Let op |
|---|---|---|
| `POST /api/auctions/{auction}/bids` | Plaatst een bod | Verplicht `Idempotency-Key`-header. Retourneert de nieuwe stand plus `sequence`. |
| `POST /api/auctions/{auction}/auto-bid` | Zet of verhoogt een maximum | Verlagen geeft 422 met uitleg. |
| `DELETE /api/auctions/{auction}/auto-bid` | Haalt het maximum weg | Het reeds geplaatste bod blijft staan; dat moet de UI ook zeggen. |
| `POST /api/auctions/{auction}/buy-now` | Koopt direct | Twee stappen: `?confirm=false` geeft de kostenopbouw terug, `true` voert uit. |
| `POST /api/auctions/{auction}/dutch-accept` | Mijnt af | Body bevat de prijs die de client zág; wijkt die meer dan één stap af, dan 409 met de actuele prijs. |
| `GET /api/auctions/{auction}/state` | Volledige stand | Pollingfallback en herstel na een gat. Cache 1s. |
| `GET /api/auctions/{auction}/events?after={seq}` | Replay | Maximaal 200 events; meer betekent "herlaad volledig". |
| `POST /api/lots/{lot}/watch` · `DELETE` | Volglijst | |
| `POST /api/lots/{lot}/viewings` | Bezichtiging plannen | Adres alleen per mail na bevestiging. |
| `POST /api/shipping/quote` | Transportindicatie | Postcode in, prijs uit; 24 uur gecached per kavel-postcodecombinatie. |
| `POST /api/drafts` · `PATCH /api/drafts/{draft}` | Funnelconcept | Werkt zonder account. Concept hangt aan een cookie-token, wordt bij registratie overgezet. |
| `POST /api/drafts/{draft}/media` | Upload | Direct naar S3 met een presigned URL; de server krijgt alleen de sleutel. |
| `POST /api/ivy/tasks` | Start een Ivy-taak | Retourneert een `run_id`. Nooit synchroon wachten. |
| `GET /api/ivy/tasks/{run}` | Status en uitkomst | Of via broadcast op het gebruikerskanaal. |

### Broadcastkanalen

| Kanaal | Type | Events |
|---|---|---|
| `auction.{id}` | Publiek | `bid.placed`, `auction.extended`, `price.dropped`, `buynow.disabled`, `auction.closed` |
| `presence-auction.{id}` | Presence | Kijkersaantal ("34 kijkers"). Alleen aantallen uitzenden, geen namen. |
| `private-user.{id}` | Privé | `outbid`, `won`, `lost`, `ivy.task.completed`, `order.updated` |

> ⚠️ **Payload-hygiëne.** Op het publieke kanaal gaat nooit een volledige naam, e-mail of maximumbod. Wel: `{sequence, amount_cents, bidder_label: "R. Jansen", type, ends_at, bid_count, bidder_count}`. Een maximumbod dat lekt, is een gebruiker die geld verliest.

---

## 8 · Ivy: de AI-laag [Yael]

Ivy is naar buiten één assistent en achter de schermen zes specialisten. Technisch is dat één abstractie met zes implementaties, en het belangrijkste ontwerpbesluit is negatief: **de LLM raakt nooit geld aan.**

### De harde scheiding

| Wel door het model | Nooit door het model |
|---|---|
| Object, merk en conditie herkennen uit foto's | Bedragen optellen, btw berekenen, fees toepassen |
| Advertentietitel, beschrijving en specificaties schrijven | De eindprijs of het totaal dat een koper betaalt |
| Een zoekvraag in gewone taal structureren naar filters | De definitieve waarderange (model levert vergelijkbare objecten; de range komt uit een deterministische berekening) |
| Uitleggen waarom een match past | Beslissen of een bod geplaatst wordt |
| Aandachtspunten in documenten en foto's signaleren | Vaststellen dát iets frauduleus is |

De kostenopbouw op de kavelpagina — bod, transport, btw-regeling, opgeld — komt volledig uit `App\Support\Money`. Als een gebruiker Ivy vraagt "wat kost dit totaal?", roept de agent die berekening aan en verwoordt de uitkomst. Hij rekent hem niet zelf uit.

### Vorm van een taak

```php
interface AiTask {
    public function agent(): Agent;              // ivy | atlas | nova | finn | flow | lex
    public function subject(): Model;
    public function input(): array;
    public function schema(): array;             // JSON-schema; output wordt hiertegen gevalideerd
    public function cacheKey(): ?string;
}

// Elke taak loopt via de queue en landt in ai_runs. Geen uitzonderingen.
RunAiTask::dispatch(new RecogniseLot($draft))->onQueue('ai');
```

**Alles wordt gelogd**
Elke run schrijft `input`, `output`, `confidence`, model, tokens, kosten en doorlooptijd naar `ai_runs`. Zonder dat log kun je later niet uitleggen waarom Ivy iets zei, en dat ga je een keer moeten kunnen.

**Uitvoer wordt gevalideerd, niet vertrouwd**
Elk antwoord tegen een JSON-schema. Faalt het, dan één retry met een strenger prompt, daarna een nette fout. Ongevalideerde modeluitvoer komt nooit op een scherm.

**Zekerheid is verplicht zichtbaar**
`ConfidencePolicy` vertaalt een score naar wat de UI mag tonen. Laag betekent: brede range, expliciet "lage zekerheid", advies om te bezichtigen. Bij `low` mag er geen enkel exact bedrag als advies uit — dat is een productregel, geen stijlkeuze.

**Budget en noodrem**
Per gebruiker een dagbudget in centen, per omgeving een totaalbudget, plus een config-vlag die de hele AI-laag uitzet. Bij uitstaan blijft alles werken: de funnel is handmatig invulbaar, de zoekpagina valt terug op gewone filters. **Ivy mag nooit een blokkade zijn.**

**Cache**
Op hash van input plus promptversie. Dezelfde foto's twee keer analyseren kost niets extra en scheelt de gebruiker wachttijd.

### De taken in de MVP

| Taak | Agent | Waar in de UI |
|---|---|---|
| `RecogniseLot` | Ivy | Funnel stap 2 — "ik herken je object, met zekerheid per onderdeel" |
| `DraftListing` | Ivy | Funnel stap 3 — titel, beschrijving, specificaties |
| `EstimateValue` | Atlas | Waarderange op kavel en funnel; model levert vergelijkingen, PHP maakt de range |
| `CheckDocuments` | Lex | "12 documenten gecontroleerd op volledigheid", plus wat ontbreekt |
| `InterpretSearch` | Ivy | Zoekpagina — corrigeerbare interpretatie vóór de resultaten |
| `ExplainMatch` | Ivy | Bij elke match: waarom hij past |
| `BidAdvice` | Atlas + Finn | Kavelpagina — verstandig bod en maximumadvies, altijd met "jij beslist" |
| `ChannelCopy` | Nova | Funnel stap 6 — per kanaal een eigen uiting |
| `WeeklyInsights` | Alle zes | Dashboard — de wekelijkse bevindingen, geschreven naar `ai_insights` |

> 🛑 **Wat er nooit uit mag komen.** Geen garantie, geen belofte over opbrengst, geen "gegarandeerd", geen bedrag zonder range bij lage zekerheid, en geen claim over escrow of technische keuring — die diensten bestaan nog niet. Zet dit in `OutputValidator` als hard filter op verboden formuleringen, niet alleen in het prompt. **Prompts drijven af; validators niet.**

---

## 9 · Orders, geld en geschillen [Yael]

### Van hamerslag naar uitbetaling

```
veiling sluit → order aangemaakt (status awaiting_payment, deadline +48u)
  → koper betaalt (iDEAL/SEPA) → paid
     → transport of ophalen geregeld → ready_for_pickup / shipped
        → koper bevestigt levering (of automatisch na 7 dagen) → delivered
           → meldtermijn 48 uur loopt
              → geen melding → completed → payout vrijgegeven
              → wel melding → disputed → uitbetaling aangehouden
```

De houdtermijn is een kolom (`payouts.hold_until`) en een scheduled job die vrijgeeft, geen handmatige handeling. De zekerheidspagina belooft die 48 uur; het systeem moet hem afdwingen.

### Kostenopbouw — één bron van waarheid

Het totaal dat een koper ziet vóór het bevestigen, het bedrag op de order en het bedrag in de betaalprovider komen alle drie uit dezelfde klasse. Als ze uiteenlopen is dat per definitie een bug, niet een afronding.

```
Money::forOrder($lot, $bidCents, $transportCents)
  hammer         het winnende bod
  buyer_premium  0 in de MVP — het veld bestaat, de waarde is nul
  transport      0 bij zelf ophalen
  vat            afhankelijk van lots.vat_regime: marge = geen verrekenbare btw
  ────────────
  total
```

### Borg bij het bieden

Preview v4 introduceert iets dat in de andere designs ontbreekt en dat je niet moet overslaan: **boven een drempelbedrag vraagt het platform een reservering of borg vóórdat iemand mag bieden.** Dat is de enige echte bescherming tegen een winnende bieder die niet betaalt, en het is een backendfeature met gevolgen voor de biedmotor.

**Hoe het werkt**
Bij een eerste bod boven de drempel (voorstel: € 2.500) doet Mollie een pre-autorisatie of een kleine borgbetaling. Slaagt die niet, dan wordt het bod geweigerd met een eigen reden-enum — niet met een algemene fout.

**Waar het raakt**
`PlaceBid` krijgt een extra poortwachter `guardDeposit()`, naast `guardVerified()`. Een tabel `deposits` met `user_id, auction_id, amount_cents, provider_id, status, released_at`. Vrijgeven gebeurt automatisch bij verliezen, en bij winnen wordt de borg verrekend met de order.

**Wat de UI moet zeggen**
Vooraf, op de kavelpagina, niet pas bij het klikken: "voor deze kavel vragen we een borg van € X, die je terugkrijgt als je niet wint". Een borg die als verrassing komt, kost je de bieder.

### Geschillen

De route uit de zekerheidspagina, met de termijnen die daar beloofd worden, als statusmachine: melding binnen 48 uur na levering → verkoper mag reageren (72 uur) → medewerker beoordeelt → uitkomst (alsnog leveren, prijscorrectie of terugdraaien) met motivering. Elke overgang schrijft naar `audit_logs` en stuurt beide partijen een bericht. De uitbetaling staat de hele route stil.

> ⚠️ **Escrow: nog niet claimen.** De designs vermijden het woord bewust en noemen het onder "waar we nog aan werken". Bouw de houdtermijn en de geschilroute, maar zet nergens "escrow" of "betaalgarantie" in de UI totdat de derdengeldenconstructie juridisch geregeld is. Zie hoofdstuk 18.

---

## 10 · Tokens & componentbibliotheek [Boyd]

Het design system bestaat al en is getoetst op contrast. Neem het over als CSS-variabelen en laat Tailwind ze uitlezen, in plaats van de waarden te herschrijven in de Tailwind-config. Eén bron, en een kleurwijziging blijft daarmee één regel.

### Tokens overnemen

```css
/* resources/css/tokens.css — letterlijk uit aucvy-design-system.html */
:root{
  --aj-bg:#f7f8fb;        --aj-surface-solid:#fff;   --aj-text:#0e1526;
  --aj-muted:#5c6474;     --aj-blue:#2f6bff;         --aj-blue-text:#1e56e0;
  --aj-violet:#7c3aed;    --aj-navy:#0c1326;         --aj-success:#1c8a5b;
  --aj-success-text:#177249; --aj-warn:#b4690e;      --aj-warn-text:#8f5209;
  --aj-error:#c0392b;     --aj-border:#0e152614;     --aj-border-strong:#0e152624;
  --aj-radius-sm:12px; --aj-radius-s:16px; --aj-radius-m:20px; --aj-radius-l:24px;
  --aj-cta-h:44px;
  --aj-motion-micro:.2s;  --aj-motion-main:.7s;
  --aj-ease-out:cubic-bezier(.16,1,.3,1);
  --aj-focus-ring:0 0 0 2px var(--aj-bg),0 0 0 4px var(--aj-blue);
}
```

> ✅ **Twee tokens die niet-onderhandelbaar zijn.** `--aj-cta-h: 44px` is de minimale tikoppervlakte en geldt overal, ook in filterchips. En `--aj-focus-ring` hoort op élk bedienbaar element; de demo's halen nul axe-overtredingen en dat moet zo blijven.

### Kleurgebruik

- Neutralen dragen zo'n 70% van het vlak, navy en donkere panelen 20%, blauw en violet samen de resterende 10%.
- Blauw uitsluitend voor de primaire actie, links en accenten. Niet voor decoratie.
- Groen betekent "veilig, verkocht, akkoord". Amber betekent "let op, laatste kans". Rood is voorbehouden aan livestatus en fouten — nergens anders.
- De zes teamkleuren (Ivy violet, Atlas blauw, Nova magenta, Finn groen, Flow cyaan, Lex indigo) zijn getoetst op minimaal 5,4:1 als tekst op licht. Niet zelf bijstellen.

### Componenten die je eerst bouwt

Alles hieronder komt uit de demo's. Bouw ze als losse componenten mét alle states, vóór je de eerste pagina samenstelt — anders bouw je ze vier keer half.

| Component | States die moeten bestaan | Gebruikt op |
|---|---|---|
| `Button` | primair, secundair, tekst, laden, uitgeschakeld met reden | overal |
| `LotCard` | live, laatste kans, gesloten, verkocht, skeleton | home, ontdek, dashboard, vergelijkbaar |
| `CountdownTimer` | normaal, slotfase, verlengd, gesloten; `tabular-nums` | kavelkaart, kavelpagina, dashboard |
| `BidPanel` | kan bieden, hoogste bieder, overboden, slotfase, niet geverifieerd, gesloten, gewonnen, verloren | kavelpagina |
| `DutchPanel` | dalend, binnen waarderange, bodem bereikt, afgemijnd door jou, afgemijnd door ander | kavelpagina |
| `BuyNowPanel` | beschikbaar, bevestigen, vervallen (met uitleg), gekocht | kavelpagina |
| `BidHistory` | leeg, live aanvullend, automatisch bod gemarkeerd | kavelpagina |
| `CostBreakdown` | indicatie, definitief, transport onbekend | kavel, koop-direct, order |
| `ConfidenceBadge` | laag, midden, hoog — met bron erbij | overal waar Ivy iets schat |
| `IvyLauncher` / `IvyPanel` | ingeklapt, open, typend, antwoord, fout, uitgeschakeld | alle pagina's |
| `CompletenessMeter` | 0–100 met vier drempelteksten | funnel |
| `MediaGallery` | foto, video, 360°, laden, ontbrekend beeld | kavelpagina |
| `InsightCard` | per specialist gekleurd, met één actie | dashboard |
| `EmptyState` | met precies één voorgestelde vervolgstap | overal |
| `BottomSheet` | mobiele variant van modal, met sluitgebaar én sluitknop | filters, bevestigingen |

### Paginastructuur

```
resources/js/
  Layouts/PublicLayout.tsx      header, mega-menu, footer, Ivy-launcher
  Layouts/AppLayout.tsx         ingelogd: zijnavigatie, notificaties
  Pages/Home.tsx
  Pages/Lots/{Index,Show}.tsx
  Pages/Sell/Funnel.tsx         stappen als losse componenten, één pagina
  Pages/Dashboard/*.tsx
  Features/auction/useAuction.ts
  Features/ivy/useIvyTask.ts
```

---

## 11 · Realtime in de UI [Boyd]

Eén hook bezit de veilingstand. Componenten lezen eruit, niemand houdt een eigen kopie bij. Dat voorkomt de klassieke fout waarbij het biedpaneel iets anders toont dan de teller ernaast.

```ts
const {
  price, endsAt, bidCount, bidderCount, extensions,
  status,        // live | closing | closed | reconnecting | stale
  myState,       // idle | highest | outbid | won | lost
  placeBid, setMaxBid, buyNow, acceptDutch,
} = useAuction(auctionId, initialState)   // initialState komt uit de Inertia-props
```

**Optimistisch, maar niet met geld**
Je eigen bod verschijnt direct als "wordt verwerkt". De **getoonde huidige prijs** verandert pas als de server bevestigt. Een optimistische prijs is een prijs waarop iemand een verkeerde beslissing neemt.

**Gaten detecteren**
Elk event draagt een `sequence`. Komt er 434 binnen terwijl je 431 had, dan haal je `/events?after=431` op. Lukt dat niet, dan gaat de status naar `stale`: het biedpaneel wordt geblokkeerd met de melding dat de stand ververst wordt, en je herlaadt `/state`. Blokkeren voelt streng en is precies goed.

**Verbinding kwijt**
Reverb weg → automatisch herverbinden → bij drie mislukte pogingen omschakelen naar polling van `/state` elke 3 seconden, met een zichtbare melding. Nooit stilzwijgend een bevroren scherm.

**De klok**
Reken met een offset tussen server- en clienttijd, bepaald bij het laden. Verschil van tien seconden op een telefoon is normaal; een aftelling die daardoor fout staat, is dat niet. Bij een verlenging animeer je de nieuwe eindtijd zichtbaar en zeg je in tekst wat er gebeurde.

**Aankondigen zonder te schreeuwen**
Prijswijzigingen in een `aria-live="polite"`-gebied, maar afgeknepen tot maximaal één melding per drie seconden. Elke bieding voorlezen maakt de pagina onbruikbaar met een schermlezer.

---

## 12 · Mobiel & toegankelijkheid [Boyd]

De mobiele buildlijst uit het project bevat correcties die in de echte build thuishoren.

| Scherm | Wat er moet gebeuren |
|---|---|
| Kavel | Foto's bovenaan op mobiel, dan pas prijs en bod. Je moet eerst zien wát je koopt. |
| Kavel | Toon standaard alleen de actieve verkoopvorm. Drie tabs plus biedpaneel plus koop-direct tegelijk is keuzestress op 390px. |
| Kavel | Geen schuifknop om te kopen. Knop plus bevestigingsstap. |
| Kavel | Bod-teller polijsten: vaste breedte en `tabular-nums`, zodat oude en nieuwe waarde niet overlappen tijdens de animatie. |
| Ontdek | Categoriechips horizontaal scrollend; overige filters achter één knop in een bottom sheet. Nu vult het filterblok het hele eerste scherm. |
| Ivy | Launcher altijd bóven de sticky onderbalk, en de chat start ingeklapt. |
| Alle | iOS safe-area op de sticky balk. Hamburgermenu ook op de contentpagina's. |
| Alle | Echte foto's met vaste beeldverhouding en lazy-load, om layout shift te voorkomen. |

### Toegankelijkheidsvloer

- Nul axe-core-overtredingen op WCAG 2.1 A en AA, per pagina, in CI.
- Skip-link bovenaan elke pagina; zichtbare focus op alles wat bedienbaar is.
- Alles wat vanzelf beweegt heeft een zichtbare pauzeknop. Bij `prefers-reduced-motion` staat het stil én volledig in beeld — pauzeren mag nooit inhoud wegnemen.
- Tikoppervlak minimaal 44×44px.
- De EU Accessibility Act maakt dit een verplichting, geen ambitie.

---

## 13 · De schermen

Per scherm: het wireframe met de blokken zoals ze in de designs staan, en ernaast wat elk blok nodig heeft — data, states en endpoints. De wireframes zijn indelingen, geen visueel ontwerp; dat staat in de HTML-demo's en die zijn leidend voor uiterlijk.

---

### 13.1 · Home [Boyd]

**Wireframe · desktop**

```
┌ Aucvy │ Veilingen · Categorieën · Hoe het werkt · Zakelijk │ Zoeken │ [Kavel plaatsen] │ Inloggen ┐
│                                                                                                  │
│ ██ HERO (donker) ████████████████████████████████████████████████████████████████████████████    │
│    kicker · Het AI-commerceplatform                                                              │
│    "Upload. Ivy regelt de rest."                                                                 │
│    [ Wat wil je vandaag verhandelen?              ] [ Vraag aan Ivy ]                            │
│    · Gratis plaatsen  · Geen opgeld voor kopers  · Alleen betalen bij resultaat                  │
│                                                                                                  │
│ Live op Aucvy — de handel van dit moment                                    [ Alles bekijken ]   │
│ ┌ foto ┐ ┌ foto ┐ ┌ foto ┐ ┌ foto ┐                                                              │
│ │ titel│ │ titel│ │ titel│ │ titel│                                                              │
│ │nog 18│ │ live │ │afslag│ │koop  │                                                              │
│ │ min  │ │      │ │      │ │direct│                                                              │
│ │€9.400│ │€2.150│ │€13.750││ €780 │                                                              │
│                                                                                                  │
│ Verkopersreis — 6 stappen, speelt automatisch af                              [ ⏸ pauze ]        │
│ [1 · upload] [2 · Ivy herkent] [3 · advertentie]                                                 │
│ [Veiling]    [Afslag]          [Koop direct]                                                     │
│ [Tarieven 7,5% / 3,5% / 2,5%] [Zekerheid] [Zakelijk verkopen]                                    │
│ ██ footer ██                                                                                     │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

**Wireframe · mobiel**

```
┌ Aucvy                    ☰ ┐
│ ██ Upload. Ivy regelt de rest.
│    [ Wat wil je verhandelen? ]
│ ┌ uitgelichte kavel (foto) ┐
│ [nog 18 min]  [€ 9.400]
│ [    Kavel plaatsen (CTA)  ]
│ chips: categorieën →
│                     ( Ivy )
└─────────────────────────────┘
```

**Specificatie**

- **Data uit de controller** — Zes uitgelichte live kavels (aflopend op eindtijd, minimaal één per verkoopvorm), categorieën met aantallen, en de tariefregels. Alles gecached — 60 seconden, met de eindtijden client-side geteld.
- **Waar het echt wordt** — De tellers op de kaarten lopen lokaal en synchroniseren elke 60s met `/api/auctions/state`. **Geen websocket op de home**; dat is honderd verbindingen voor niets.
- **Reisblokken** — Spelen automatisch af, met een zichtbare pauzeknop. Bij `prefers-reduced-motion` staat alles stil en zijn álle stappen tegelijk zichtbaar.
- **Zoekblok** — Eén tekstveld in gewone taal. Enter gaat naar `/zoeken?q=…`; de interpretatie gebeurt daar, niet hier.
- **Mobiel** — De "ik wil kopen / verkopen"-keuze mag niet achter de sticky onderbalk verdwijnen.
- **Meten** — Vanaf dag één vastleggen welk blok de klik naar een kavel opleverde. Dat is later je enige manier om te weten wat de home doet.

---

### 13.2 · Ontdek en zoeken [Boyd] [Yael]

**Wireframe · desktop**

```
┌ Aucvy │ zoekbalk                                          │ [Kavel plaatsen] ┐
│ ┌ Filters ────┐ ┌──────────────────────────────────────────────────────────┐ │
│ │ Categorie   │ │ 1.240 kavels · 4 filters actief          [ Sorteer ▾ ]   │ │
│ │  ▁▁▁▁       │ │ ┌ foto ┐  ┌ foto ┐  ┌ foto ┐                             │ │
│ │  ▁▁▁        │ │ │titel │  │titel │  │titel │                             │ │
│ │  ▁▁▁▁       │ │ │18 min│  │2 dgn │  │afslag│                             │ │
│ │ Status      │ │ │€9.400│  │€2.150│  │€13.750                             │ │
│ │ live/nieuw/ │ │ │12% on│  │7 bie-│  │daalt │                             │ │
│ │ laatste kans│ │ │der wa│  │ders  │  │/20s  │                             │ │
│ │ Prijsbereik │ │ ┌ foto ┐  ┌ foto ┐  ┌ foto ┐                             │ │
│ │ Regio/afst. │ │                                                          │ │
│ │ Verkoopvorm │ │ [               Meer laden               ]               │ │
│ │[Filters wis]│ └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Lege staat — Ivy verbreedt**

```
┌───────────────────────────────────────────────────────────────────┐
│ Geen kavels gevonden voor deze combinatie                         │
│ Ivy: "Ik vond niets binnen 25 km onder € 4.000. Zonder de         │
│       afstandsfilter zijn het er 12 — zal ik die tonen?"          │
│ [ Toon die 12 ]                        [ Zet een alert ]          │
└───────────────────────────────────────────────────────────────────┘
```

**Specificatie**

- **Filters staan in de URL** — Elke filter is een querystringparameter. Deelbaar, terugknop werkt, en de servercache kan erop werken. Inertia partial reload bij filterwijziging — nooit de hele pagina.
- **Zoeken** — Meilisearch via Scout. Facettellingen komen mee zodat een filter die nul oplevert al uitgeschakeld te tonen is.
- **De facetten liggen vast (uit preview v4)**
  - Snelfilters bovenaan: `alles · live · nieuw · laatste kans · onder marktwaarde · faillissementen`
  - Filterpaneel: verkoopvorm (veiling / afslag / koop direct), conditie (zo goed als nieuw / gebruikt-goed / voor onderdelen), provincie, prijsbereik op het huidige bod, en twee schakelaars — *kijkdag mogelijk* en *transport beschikbaar*. Elk met een aantal erachter.
  - Sorteringen: `relevantie · sluit eerst · prijs op · prijs af · meeste biedingen`
- **Zes sorteringen** — Eindigt binnenkort · nieuwste · prijs laag/hoog · meeste bieders · grootste verschil met waarde-indicatie.
- **"% onder waarde-indicatie"** — Alleen tonen bij `value_confidence ≥ medium`. Bij lage zekerheid verdwijnt het label — de indicatie is dan niet scherp genoeg om als koopargument te dienen.
- **Lege staat** — Nooit een doodlopende pagina. Ivy stelt één concrete verbreding voor plus de mogelijkheid een alert te zetten. Dat is de `saved_searches`-tabel, geen registratiemuur.
- **Mobiel** — Categoriechips horizontaal scrollend, alle andere filters achter één knop in een bottom sheet met een teller ("4 filters").
- **Zoekpagina met Ivy** — `/zoeken` is dezelfde resultatenlijst met een extra blok bovenaan: de geïnterpreteerde zoekvraag als corrigeerbare chips. Corrigeren past de filters aan; er wordt **geen nieuwe LLM-call** gedaan voor een correctie.

---

### 13.3 · Kavel en bieden [samen — dit is het hart]

Het drukste scherm en het scherm waar geld verdiend wordt. Boyd bouwt de panelen tegen een gemockte `useAuction`; Yael bouwt de motor tegen tests. Ze komen samen zodra beide kanten hun eigen kant bewezen hebben.

**Wireframe · desktop**

```
┌ Aucvy │ Keukens & interieur / kavel 8842 ──────────────────────────────────────────┐
│ ┌───────────────────────────────────────────┐  ┌─ zijkolom (max 230px) ──────────┐ │
│ │ hoofdfoto · 14 beelden · video · 360°     │  │ ██ Huidig bod                   │ │
│ │ [1][2][3][4][+10]                         │  │ ██ € 12.850                     │ │
│ │ Complete designkeuken met Siemens-app.    │  │ ██ 9 bieders · 2× verlengd      │ │
│ │ [Verkoper geverifieerd][Utrecht — bezicht-│  │ ██ [Slotfase — nog 00:47]       │ │
│ │  iging mogelijk][Transport vanaf € 340]   │  │ ██ [ € 13.100 (minimum)      ]  │ │
│ │ ┌ Ivy insight ────────────────────────┐   │  │ ██ [    Bied € 13.100        ]  │ │
│ │ │ Marktwaarde € 15.000 – € 19.500     │   │  │ ██ [Automatisch meestappen…] │  │ │
│ │ │ (indicatie · zekerheid midden)      │   │  ├─────────────────────────────────┤ │
│ │ │ Documentatie 94% compleet           │   │  │ Kosten vóór je biedt            │ │
│ │ │ [Verstandig bod?][Totaal?][Vergelijk]│  │  │ bod + transport €340 + marge-btw│ │
│ │ └─────────────────────────────────────┘   │  ├─────────────────────────────────┤ │
│ │ [Omschrijving][Specificaties]             │  │ ✔ Je bent de hoogste bieder     │ │
│ │ [Documenten (4)][Transport & ophalen]     │  ├─────────────────────────────────┤ │
│ │ conditie & gebreken — eerlijk vermeld,    │  │ Biedhistorie — live             │ │
│ │ met verwijzing naar foto 8 en 9           │  │ € 12.850 · R. Jansen            │ │
│ │ Verkoperskaart · 4,8 · 127 verkopen ·     │  │ € 12.600 · M. de Vries · autom. │ │
│ │ lid sinds 2023                 [Profiel →]│  │ € 12.350 · K. Bakker            │ │
│ │ Vergelijkbare kavels — 4 kaarten          │  ├─────────────────────────────────┤ │
│ └───────────────────────────────────────────┘  │ [Bezichtiging plannen]          │ │
│                                                │ [Op volglijst]                  │ │
│                                                └─────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────────────┘
```

**Wireframe · mobiel — foto's eerst**

```
┌───────────────────────────┐
│ ┌ hoofdfoto ────────────┐ │
│ [2][3][+12]               │
│ Complete designkeuken…    │
│ ██ Huidig bod             │
│ ██ € 12.850               │
│ ██ [Slotfase — 00:47]     │
│ Ivy: 12% onder marktwaarde│
│ Omschrijving ▾            │
│ Documenten (4) ▾          │
│ Transport & ophalen ▾     │
│                    ( Ivy )│
│ ██ sticky: Bied € 13.100  │
└───────────────────────────┘
```

**Specificatie**

- **Eerste render** — De volledige stand komt als Inertia-prop mee. De pagina is dus meteen correct zonder websocket; die neemt het daarna over. Belangrijk voor SEO en voor de eerste seconde op mobiel.
- **Eén verkoopvorm tegelijk** — Op mobiel toon je uitsluitend het paneel van de actieve verkoopvorm. De uitleg over de drie vormen zit achter "Hoe werkt dit?", niet permanent in beeld.
- **Biedstates die moeten bestaan** — kan bieden · hoogste bieder · overboden · slotfase (met verlengwaarschuwing) · niet geverifieerd (met de route naar verificatie) · eigen kavel · gesloten · gewonnen · verloren. Elk met eigen tekst; geen generieke fout.
- **Verlenging tonen** — Bij een verlenging: klok springt zichtbaar, teller "2× verlengd" loopt op, en er verschijnt één regel tekst die uitlegt waaróm. Dit is een verkoopargument — verstop het niet.
- **Kosten vóór het bieden** — Bod, transport, btw-regeling, opgeld (nul). Uit `Money::forOrder`, ook hier. **Niet client-side narekenen.**
- **Ivy op deze pagina** — Drie vaste vragen plus vrij invoerveld. Antwoorden komen via `private-user.{id}` binnen, met een zichtbaar denkmoment. Elk advies draagt zijn zekerheid en eindigt bij "jij beslist".
- **Documenten** — Altijd via signed URL, nooit publiek. Toon per document of Ivy hem op volledigheid gecontroleerd heeft, plus wat er ontbrak.
- **Livestream en 360°** — In de MVP optioneel per kavel. Ontbreekt hij, dan verdwijnt de tab — geen lege tab tonen.

> ⚠️ **Volgorde van bouwen.** Eerst de gewone veiling helemaal af, inclusief alle states. Pas daarna afslag, pas daarna koop-direct. **Drie halve verkoopvormen is erger dan één hele.**

---

### 13.4 · Verkoopfunnel [Boyd] [Yael]

**Wireframe · desktop**

```
[1 Wat][2 Aanleveren][3 Analyse][4 Advertentie][5 Vorm][6 Promotie][7 Kosten][8 Live]
┌────────────────────────────────────────────┐ ┌─ zijkolom (190px) ────────────┐
│ ██ Wat wil je verkopen?                    │ │ Compleetheid                  │
│ ██ Kies wat het dichtst in de buurt komt — │ │ ▓▓▓▓▓▓▓▓░░ 72%                │
│ ██ of vertel het in één zin.               │ │ nog één element en je kavel   │
│ ██ [één object][meerdere][hele inventaris] │ │ hoort bij de best gedocument. │
│ ██ [ "Ik stop met mijn restaurant en wil   │ ├───────────────────────────────┤
│ ██   alles verkopen."                    ] │ │ Ivy: "Vertel me wat je wilt   │
│ ██ [🎤 spreek in][   Stuur naar Ivy      ] │ │ verkopen — met een kaart of   │
│ ██ Geen account nodig — je ziet eerst wat  │ │ in je eigen woorden."         │
│ ██ ik voor je maak.                        │ ├───────────────────────────────┤
├────────────────────────────────────────────┤ │ Hoe lang duurt dit?           │
│ Voeg toe wat je hebt                       │ │ Moet ik een account maken?    │
│ [foto][foto][document][+ toevoegen]        │ │ Wat als ik het fout invul?    │
├────────────────────────────────────────────┤ └───────────────────────────────┘
│ Resultaat — zekerheid per onderdeel        │
│ [merk · hoog][bouwjaar · midden][conditie · laag]
└────────────────────────────────────────────┘
[← terug]                    [later verder] [Volgende]
```

**Specificatie**

- **Concept eerst, account later** — Stap 1 t/m 4 werken zonder account. Het concept hangt aan een getekend cookie-token en wordt bij registratie overgezet naar de gebruiker. Pas bij opslaan of publiceren komt de accountvraag. Dit is een expliciete productkeuze — **bouw er geen muur voor.**
- **Autosave** — Elke wijziging `PATCH /api/drafts/{draft}`, afgeknepen tot één call per 800ms. Terugknop werkt, elke stap is omkeerbaar, "later verder" stuurt een link per mail.
- **Compleetheidsscore** — Berekend in PHP uit tellbare feiten: aantal foto's, video aanwezig, documenten per soort, ingevulde specificaties, transportinformatie. **Niet door de LLM** — een score die per aanroep varieert is geen score. Vier drempelteksten: start, goed, sterk, compleet.
- **Uploads** — Direct naar S3 met presigned URL, met voortgang per bestand. Server ontvangt de sleutel en zet varianten en checksum in de queue `media`.
- **Analyse als achtergrondtaak** — Stap 3 start `RecogniseLot` en toont een echt wachtscherm met wat er gebeurt. Duurt het langer dan 20 seconden, dan mag de gebruiker doorlopen en komt het resultaat later binnen. Nooit een spinner zonder uitweg.
- **Kosten vóór publicatie** — Stap 7 toont het volledige plaatje: eenmalige promotiekosten en de succesfee volgens het plan. Uit dezelfde `Money`-klasse. Publiceren kan niet zonder dat dit getoond is.
- **Inkorten mag** — Toon een tijdsindicatie, laat Ivy invullen wat ze kan en sta overslaan van optionele stappen toe. Acht stappen is het maximum, niet het doel.

---

### 13.5 · Dashboard [Boyd] [Yael]

**Wireframe · desktop**

```
┌ Aucvy │ Verkopen · Kopen — toggle │ 🔔 3 │ account ─────────────────────────────┐
│ Jouw AI-team                                                                    │
│ [Ivy · coördineert][Atlas · markt][Nova · marketing]                            │
│ [Finn · geld][Flow · logistiek][Lex · compliance]                               │
│ Klik een specialist om alleen zijn punten te zien.                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│ ✔ Dit heeft het team al gedaan — zonder dat je iets hoefde te doen              │
│ [6 advertentievarianten op 4 kanalen] [12 documenten gecontroleerd]             │
│ [1.240 kavels langsgelopen]           [Transportprijzen bij 3 kavels opgehaald] │
├─────────────────────────────────────────────────────────────────────────────────┤
│ Vraagt vandaag je aandacht — 4 punten                                           │
│ [Flow] Eén kavel mist transportinformatie — kopers haken af.      [Aanvullen]   │
│ [Nova] Drie campagnes leveren nu de meeste bieders op — verlengen? [Bekijken]   │
│ [Finn] € 18.400 staat klaar voor uitbetaling.                     [Vrijgeven]   │
│ [Lex]  Eigendomsbewijs ontbreekt bij de horeca-koeling.           [Uploaden]    │
├──────────────────────────────────┬──────────────────────────────────────────────┤
│ Koperintentie deze week          │ Wat doet de vraag straks?                    │
│ 427 bezoekers                    │ ┌ 8 gemeten weken · 3 voorspeld ───────────┐ │
│ 128 serieuze kopers  ▓▓▓▓▓▓▓▓    │ │ met markering waar meten stopt           │ │
│ 18 hoge intentie     ▓▓▓▓▓       │ └──────────────────────────────────────────┘ │
│ 4 zakelijke accounts ▓▓▓         │ Advies: wacht met publiceren tot eind aug.   │
│ gedragssignalen, geen persoonsg. │                                              │
├──────────────────────────────────┴──────────────────────────────────────────────┤
│ Wat Ivy deze week zag                                                           │
│ [Machinecategorie blijft achter — 62% van verwachte eindprijs]                  │
│ [Dinsdag vóór 10:00 publiceren geeft 31% meer bieders]                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│ [Mijn kavels][Mijn biedingen][Orders][Uitbetalingen]                            │
└─────────────────────────────────────────────────────────────────────────────────┘
```

**Specificatie**

- **Geen cijferoverzicht, een werkplan** — De volgorde van de blokken is het ontwerp: eerst wat al gedaan is, dan wat jouw akkoord nodig heeft, dan pas de cijfers. Draai die volgorde niet om — het verschil tussen "hier is data" en "hier is werk dat af is" zit precies daarin.
- **Signalen komen uit de database** — `ai_insights`, gevuld door een wekelijkse job plus gebeurtenisgedreven signalen (transport ontbreekt, document ontbreekt, uitbetaling klaar). **De pagina doet geen AI-calls; hij leest.**
- **Elk signaal draagt een naam** — De specialist die het opmerkte staat erbij, met zijn kleur. Dat maakt een advies navolgbaar in plaats van magisch. Filteren op één specialist filtert de lijst, meer niet.
- **Eén actie per signaal** — Geen kaart zonder knop. Kun je geen concrete handeling bedenken, dan is het geen signaal maar een cijfer, en hoort het lager op de pagina.
- **Voorspelling blijft een bandbreedte** — De omzetprognose en de vraagvoorspelling zijn ranges met een zichtbare grens tussen meten en schatten. Nooit één getal.
- **Koper of verkoper** — Eén dashboard met een toggle. De blokken verschillen, het raamwerk niet. Nieuwe gebruikers zien in plaats van lege blokken drie startstappen.
- **Intentietrechter** — Afgeleid uit paginagebeurtenissen (bekeken, documenten geopend, volglijst, bezichtiging, bod). Geen persoonsgegevens tonen, alleen aantallen — dat staat er ook letterlijk bij en dat moet waar blijven.

---

### 13.6 · Verkopersprofiel, zekerheid, account [Boyd]

**Wireframe · verkopersprofiel**

```
┌ [logo] Keukenhuis Utrecht BV                              [Stel een vraag] ┐
│        [Geverifieerd][4,8 · 127 verkopen][lid sinds 2023]                  │
│ [98% op tijd][<3u reactie][0 geschillen][12 mnd actief]                    │
│ Beoordelingen — alle sterren                                               │
│  5★ ▓▓▓▓▓▓▓▓▓▓ 98                                                          │
│  4★ ▓▓▓▓ 21                                                                │
│  3★ ▓▓ 6                                                                   │
│  2★ ▓ 2                                                                    │
│ "Ophalen liep een week uit door miscommunicatie."                          │
│   Reactie verkoper: "Terecht punt — vaste ophaalmomenten ingevoerd."       │
│ Alleen kopers die de kavel afnamen kunnen beoordelen.                      │
│ Reviews worden niet verwijderd op verzoek.                                 │
│ [kavel][kavel][kavel]                                                      │
└────────────────────────────────────────────────────────────────────────────┘
```

**Wireframe · zekerheid & geschillen**

```
┌ Bij elke kavel geregeld ───────────────────────────────────┐
│ [Verkoper gecontroleerd]  [Kosten vóór het bieden]         │
│ [Biedhistorie vastgelegd] [Bezichtiging mogelijk]          │
│ Als het misgaat — met termijnen                            │
│ 1 · Je meldt het binnen 48 uur · uitbetaling aangehouden   │
│ 2 · Verkoper reageert met eigen bewijs                     │
│ 3 · Een medewerker beoordeelt — geen algoritme             │
│ 4 · Uitkomst met motivering                                │
│ ⚠ Waar we nog aan werken — escrow, technische keuring,     │
│   verzekerd transport. Staat hier pas als het er echt is.  │
│ Veelgestelde vragen — accordeon                            │
└────────────────────────────────────────────────────────────┘
```

**Wireframe · verificatie vóór het eerste bod**

```
✓  E-mail bevestigd
✓  Telefoonnummer
→  Identiteit — duurt twee minuten            [ Starten ]
□  KVK (alleen zakelijk)
Nodig vóór je eerste bod, niet vóór het rondkijken.
```

**Specificatie**

- **Reviews zijn niet cosmetisch** — Toon de verdeling over álle sterren, laat kritische reviews staan en geef de verkoper een reactieveld. Alleen orders met status `completed` geven recht op een review. Dit is het verschil met een anoniem platform en het is een technische regel, geen redactionele.
- **De zekerheidspagina is een belofte met deadlines** — Wat er staat, moet het systeem afdwingen: de 48-uursmeldtermijn, de aanhouding van de uitbetaling, de menselijke beoordeling. Verandert de tekst, dan verandert de code mee — en andersom.
- **Verificatie blokkeert alleen bieden** — Rondkijken, zoeken, volgen en een concept maken kan zonder. Het eerste bod niet. Toon dat vooraf op de kavelpagina, niet pas als de knop faalt.
- **Wat verificatie precies inhoudt (uit preview v4)**
  - **Kopers:** account met e-mail → geldig identiteitsbewijs plus een korte liveness-selfie → betaalmethode toevoegen, en boven de drempel een reservering of borg → geverifieerd-badge, bieden vrij.
  - **Verkopers:** KvK-koppeling met controle op de tenaamstelling → identiteit van de tekenbevoegde en waar nodig de UBO → IBAN op naam van het bedrijf → geverifieerd-merk op het profiel.
- **Wat je bewaart en wat niet** — Alleen het resultaat en de datum, nooit het documentbeeld of de selfie — die blijven bij de verificatiepartner. Dat staat ook zo in de tekst op de v4-pagina, dus het is inmiddels een belofte aan de gebruiker.
- **Waarom dit geen optionele stap is** — Geen nepbiedingen, geen wanbetaling, en KYC/AML is voor een handelsplatform hoe dan ook verplicht. **Bouw de gate hard in `PlaceBid`, niet alleen in de UI.**

---

## 14 · Integraties

| Doel | Voorstel | Aandachtspunt |
|---|---|---|
| Betalen | Mollie (iDEAL, SEPA, Bancontact) | Webhooks idempotent verwerken; betaalstatus is nooit af te leiden uit de redirect. |
| Uitbetalen | SEPA-batch of Mollie Connect | Afhankelijk van de derdengeldenkeuze uit hoofdstuk 18. Bouw `payouts` providervrij. |
| Bedrijfsverificatie | KVK-API | Naam en nummer koppelen, resultaat opslaan met datum — niet elke keer opnieuw bevragen. |
| Identiteit | iDIN of een KYC-provider | Alleen het resultaat bewaren, nooit het documentbeeld. |
| Transport | Brenger / Wuunder / Transmission | Prijs is een indicatie met geldigheidsduur; cache per kavel-postcodecombinatie. |
| Mail | Postmark of SES | Transactioneel gescheiden van marketing, anders sleep je je bezorgbaarheid mee omlaag. |
| Push | Web push (VAPID) | "Je bent overboden" moet binnen twee seconden op het scherm staan. Meet dat als productmetriek. |
| AI | Multimodale API voor herkenning | Provider achter een eigen interface, zodat wisselen één klasse is. |
| Foutmeldingen | Sentry | Aparte alert op alles wat uit `App\Auctions` komt. |

> ⚠️ **E-mail is te laat voor "overboden".** Als het bericht per mail komt, is de veiling voorbij tegen de tijd dat iemand het leest. Web push in de MVP, en reken erop dat een echte app hierna hoog op de lijst komt.

---

## 15 · Security & AVG

- **Autorisatie via Policies**, per model, met tests. Een kavel bewerken, een document zien, een order openen — alles langs een policy, nooit via een impliciete `where user_id`.
- **Documenten en identiteitsbestanden** op een private disk, uitsluitend via tijdelijk getekende URL's van maximaal vijf minuten.
- **Geen persoonsgegevens in URL's** of in querystrings, ook niet in filters.
- **Publieke weergave pseudonimiseert**: "R. Jansen", nooit de volledige naam en nooit een e-mailadres. Maximumbiedingen zijn nooit zichtbaar, ook niet voor de verkoper.
- **Auditlog** op alles wat geld, status of eigendom raakt: wie, wat, wanneer, oude en nieuwe waarde. Append-only, niet muteerbaar vanuit de applicatie.
- **Bewaartermijnen**: biedingen en orders zeven jaar (fiscaal), analytics-events dertien maanden, AI-runs twaalf maanden. Vastleggen in een verwijderjob, niet in een document.
- **Recht op inzage en verwijdering**: export als job, verwijdering als anonimisering — een order met een geschil mag niet verdwijnen, de persoon erachter wel losgekoppeld worden.
- **2FA verplicht** voor accounts met een gekoppelde uitbetaling.
- **Uploads** scannen en het MIME-type serverside bepalen, niet uit de bestandsnaam.
- **Rate limiting** op login, registratie, bieden en alle Ivy-endpoints.

---

## 16 · Testen & QA

**Backend · Pest**

- De volledige testtabel uit hoofdstuk 6, inclusief de faalroutes.
- Order- en uitbetaalstatusmachine: elke overgang, ook de verboden.
- Money-klasse met een reeks vaste rekenvoorbeelden per btw-regime.
- Policies: elke policy heeft een test die bewijst dat de verkeerde gebruiker níets ziet.
- Ivy: schemavalidatie, verboden formuleringen, gedrag als de provider faalt.

**Frontend & keten**

- Playwright op de drie routes die geld raken: bieden, koop-direct, afslag.
- Reconnect-scenario: verbinding hard verbreken, terugkomen, stand moet kloppen.
- axe-core per pagina in CI, nul overtredingen, build faalt anders.
- Lighthouse-budget op de kavelpagina: LCP onder 2,5s op 4G-simulatie.
- Handmatig op een echte telefoon vóór elke release — 390px is geen devtools-breedte maar een duim.

> 🛑 **Eén test die niet mag ontbreken.** De loadtest met 500 gelijktijdige bieders op één kavel, **vóór** de launch en niet erna. De laatste minuut van een populaire veiling is een piek van honderden events per seconde op één rij. Als dat daar breekt, breekt het precies op het moment dat er geld op tafel ligt.

---

## 17 · Bouwvolgorde

Sprints van twee weken. De volgorde is gekozen zodat Boyd en Yael zelden op elkaar wachten. Schattingen zijn voor twee mensen die hier het grootste deel van hun tijd aan besteden.

| Sprint | Yael · backend | Boyd · frontend | Klaar als |
|---|---|---|---|
| **0 · Fundament** (1 week) | Repo, CI, Postgres, Redis, Horizon, Reverb draaiend. Auth en registratie. Basismigraties: users, companies, categories, lots. | Tokens naar CSS-variabelen, Tailwind-koppeling, layouts, en de componentbibliotheek uit hoofdstuk 10 als losse componenten met states. | Een lege pagina in de huisstijl staat op staging, met werkende login. |
| **1 · Biedmotor** (2 weken) | `PlaceBid`, proxy, anti-sniping, events met sequence, sluiting, broadcasting. Volledige testsuite. **Niets anders deze sprint.** | Kavelpagina met gemockte `useAuction`: alle biedstates, galerij, kosten, biedhistorie, mobiele indeling. | Yaels tests zijn groen en Boyds pagina toont elke state zonder server. |
| **2 · Koppelen** (1 week) | Endpoints, kanalen, autorisatie, rate limits. Kavel-controller met volledige eerste render. | Mock vervangen door de echte hook: Echo, sequence-gaten, herverbinden, klokoffset. | Twee browsers, één kavel, échte biedingen die elkaar binnen twee seconden zien. Loadtest gedraaid. |
| **3 · Ontdekken** (2 weken) | Meilisearch, facetten, sorteringen, volglijst, opgeslagen zoekopdrachten en alerts. | Ontdekpagina, filters in de URL, mobiele bottom sheet, lege staten, kavelkaarten. | Een koper vindt zonder hulp een kavel en zet er een alert op. |
| **4 · Verkopen** (2,5 weken) | Concepten zonder account, uploads met presigned URL's, mediaverwerking, compleetheidsscore, publicatie. | De acht stappen, autosave, uploadvoortgang, compleetheidsmeter, Ivy-paneel ernaast. | Een verkoper plaatst een echte kavel die live gaat, zonder dat iemand meekijkt. |
| **5 · Ivy v1** (2 weken, deels parallel) | De AI-laag uit hoofdstuk 8: agents, jobs, `ai_runs`, guards, budget, cache. Zeven taken. | Launcher en paneel op alle pagina's, antwoordkaarten met zekerheid, denkstaten, foutstaten, uitgeschakelde staat. | Ivy werkt in de funnel en op de kavel — en met de noodrem om werkt alles nog steeds. |
| **6 · Geld** (2 weken) | Orders, Mollie, webhooks, uitbetalingen met houdtermijn, `Money` met tests, facturen. | Orderpagina's, betaalroute, kostenopbouw, bevestigingsstappen, statusberichten. | Een veiling loopt van hamerslag tot uitbetaalde verkoper in staging. |
| **7 · Dashboard** (2 weken) | `ai_insights`, wekelijkse job, intentgebeurtenissen, forecastberekening. | Alle dashboardblokken, teamfilter, grafieken, koper/verkoper-toggle, lege staten voor nieuwe gebruikers. | Een verkoper opent het dashboard en ziet werk, geen cijfers. |
| **8 · Vertrouwen** (1,5 week) | Reviews met ordercontrole, verkopersstatistieken, geschilstatusmachine met termijnen, verificatieroutes. | Verkopersprofiel, reviewweergave, zekerheidspagina, verificatiescherm, geschilmelding. | De volledige geschilroute is doorlopen met een testorder. |
| **9 · Scherpstellen** (2 weken) | Loadtest opnieuw, indexen, cache, Sentry-alerts, back-up en herstel geoefend, bewaartermijnen. | axe-audit alle pagina's, Lighthouse, echte foto's, mobiele buildlijst afgevinkt, alle lege en foutstaten nagelopen. | Nul axe-overtredingen, loadtest binnen de norm, herstel uit back-up bewezen. |

**Ongeveer zestien weken tot een platform dat een echte veiling aankan**, plus de tijd die je kwijt bent aan wat we nu nog niet weten. Reken op achttien.

### Waar jullie wél op elkaar wachten

- **Sprint 2** kan pas als sprint 1 aan beide kanten af is. Plan die week samen, niet los.
- **De `Money`-klasse** is nodig vóór de kostenopbouw op de kavelpagina definitief kan. Yael levert hem in sprint 1 al op, ook al gebruikt niemand hem dan.
- **Categorieën en hun attributenschema** blokkeren zowel de funnel als de filters. Kies ze in sprint 0, ook al is de lijst nog kort.
- **Ivy is nooit blokkerend.** Als sprint 5 uitloopt, gaat de rest gewoon door — dat is precies waarom de noodrem bestaat.

---

## 18 · Open beslissingen

Geordend op hoe duur het wordt om ze laat te nemen.

| Beslissing | Waarom het nu moet | Uiterlijk |
|---|---|---|
| PostgreSQL of MySQL | Bepaalt lockgedrag, jsonb en indexstrategie. Later wisselen betekent de biedmotor opnieuw testen. | Vóór sprint 0 |
| Inertia+React of Livewire | Bepaalt de hele frontendstructuur en of de demo's herbruikbaar zijn. | Vóór sprint 0 |
| **Welke zekerheidsbelofte geldt** | Preview v4 belooft escrow, geld terug en een controletermijn van 3 werkdagen; de designkeuken belooft niets van dat alles. Dit bepaalt de uitbetaalarchitectuur, de vergunningvraag en de complete copy van de zekerheidspagina. Zolang dit openstaat, bouw je op zand. | **Nu — vóór sprint 0** |
| Derdengelden / escrow, praktisch | Zodra de belofte vaststaat: welke gereguleerde partij, welke constructie, en welke vergunning je daarvoor nodig hebt. | Vóór sprint 6 |
| Demo-cijfers op de zekerheidspagina | 18.240 veilingen en € 46,8 mln uitbetaald staan als feiten op het scherm. Bij launch zijn ze nul. Vervangen door iets wat meegroeit, of weglaten. | Vóór sprint 8 |
| Borgdrempel bij bieden | Boven welk bedrag vraag je een reservering, en hoe hoog. Raakt de biedmotor en de conversie tegelijk. | Vóór sprint 6 |
| Advertentiebeheer in de MVP? | Preview v4 heeft er een scherm voor. Kost ongeveer een sprint en raakt promotiebudget, dus geld. | Vóór sprint 4 |
| Btw en margeregeling | Per categorie en per verkopertype anders. Fout in de `Money`-klasse is fout op elke factuur. | Vóór sprint 6 — met de accountant |
| ~~Categorietaxonomie~~ — **beslist** | Staat in preview v4: acht hoofdcategorieën met subcategorieën, overgenomen in hoofdstuk 5. Wat nog open is: de attributenschema's per subcategorie. | Sprint 0 (alleen de schema's) |
| Afslag in de MVP? | Kost ongeveer een halve sprint extra. Als er in de eerste maanden geen verkoper om vraagt, is het uitstelbaar. | Vóór sprint 2 |
| Reverb zelf hosten of Pusher | Reverb is goedkoper en zonder limiet; Pusher is minder beheer. Beide werken met dezelfde clientcode. | Sprint 1 |
| Wie levert de fotografie | De demo's gebruiken stockbeeld. Echte foto's zijn geen detail — ze bepalen de opbrengst. | Vóór de eerste echte kavel |
| Eerste categorie voor de launch | De blauwdruk adviseert bedrijfsinventarissen en faillissementen: gemotiveerde verkopers, hoog volume per verkoper, professionele kopers. | Vóór sprint 9 |

---

## 19 · Wat hierna komt

Uit de AI-commerce-roadmap, op volgorde van waarde. Bouw hier niets van in de MVP, maar **laat het datamodel het niet uitsluiten** — dat is de enige eis die nu geldt.

| Feature | Wat het is | Wat de MVP nu al moet toestaan |
|---|---|---|
| Liquidatieplanner | Begint niet bij "wat wil je verkopen" maar bij "wat wil je bereiken" — bedrijf sluiten, showroom leegmaken — en bouwt het hele uitvoeringsplan. | Kavels moeten aan een overkoepelend project kunnen hangen. Nu al een nullable `project_id` op `lots`. |
| Veilingsimulator | Schuiven aan reserveprijs, looptijd, publicatiemoment en budget, met directe terugkoppeling op verwachte opbrengst. | Alle uitkomstsignalen per kavel loggen: bieders, verloop, kanaal, eindprijs. |
| Trust Index | Eén dynamisch cijfer uit documentkwaliteit, leverprestatie, responstijd en geschilhistorie. | De losse signalen nu al meten, ook al toon je ze los. |
| Inventory Scanner | Een heel magazijn scannen met de telefoon; herkenning wordt direct structurele inventaris. | Media-checksums en attributen met herkomst per veld. |
| Video Studio | Uit foto's automatisch reels en advertentievideo's per kanaal. | Originele beelden in volledige resolutie bewaren, niet alleen varianten. |
| Fraud Detection | Dubbele beelden, bewerkte documenten, afwijkend biedgedrag. | Checksums, `ip` en `user_agent` per bod, auditlog. |
| Commerce API | Het platform als infrastructuur, met ERP- en white-labelkoppelingen. | Businesslogica in Actions, niet in controllers — dan is een API-laag erbij zetten werk van dagen, niet maanden. |

### Drie regels die overal blijven gelden

1. **Geen claim zonder dienst.** Escrow, betaalgarantie en technische keuring komen pas in de tekst als de juridische en operationele inrichting bestaat. Wat nog niet bestaat, staat zichtbaar als concept in beeld.
2. **Geen AI-uitkomst zonder zekerheid en bron.** Nooit als garantie, altijd navolgbaar. Eén keer een graafmachine van € 40.000 taxeren op € 25.000 kost meer vertrouwen dan honderd goede schattingen opleveren.
3. **Geen account vóór de eerste analyse.** De verkoper ziet eerst wat Ivy van zijn spullen maakt. Dat is geen marketingtruc maar het hele verschil met de rest.

---

## Als je één ding uit dit document onthoudt

Bouw de biedmotor eerst, bouw hem geïsoleerd, en laat hem pas los als de loadtest met vijfhonderd gelijktijdige bieders schoon door is. Al het andere in dit document is te repareren in een volgende sprint. **Een veiling die een bod verliest, is dat niet.**
