# Vragen voor Raph: wat we nodig hebben om Aucvy af te bouwen

Datum: 28 oktober 2026. Van: Boyd. Antwoorden kun je achter elke vraag invullen, of het nummer plus je keuze terugmailen.

Alles uit beide briefings (16 oktober en de masterbriefing van 28 oktober) dat zonder jouw antwoord kon, is gebouwd en getest. Wat hieronder staat kan niet zonder jouw keuze, je akkoord of door jou aangeleverd materiaal. Bij elke vraag staat wat we doen als je niets kiest, zodat de bouw nooit stilstaat.

De drie belangrijkste: vraag 1 (betaalprovider), vraag 2 (factuurmodel) en vraag 24 (juridische teksten). Zonder die drie kan het platform niet live.

---

## A. Geld

**1. Welke betaalprovider, en met of zonder derdengeldenrekening (escrow)?**
De adapter en de webhook zijn gebouwd en draaien nu op een nepprovider. Mollie is voorbereid.
Opties: (a) Mollie met marketplace-uitbetaling, (b) andere PSP, (c) Mollie plus een aparte derdengeldenconstructie.
Standaard als je niets kiest: (a), en we beloven nergens escrow tot het er is.
Nodig van jou: account met sandbox- en livesleutels.
Antwoord:

**2. Wie verkoopt en wie factureert, juridisch?**
Dit bepaalt elk factuurdocument, de btw en de margeregeling.
Opties: (a) koper betaalt aan het platform, platform factureert de succesfee aan de verkoper, verkoper factureert de kavel aan de koper (platform maakt dat document namens de verkoper), (b) koper betaalt rechtstreeks aan de verkoper, (c) platform verkoopt in eigen naam.
Standaard: (a).
Antwoord:

**3. Btw en bijzondere regelingen**
Welke btw-behandeling per situatie: margegoederen (nu standaard), btw-verlegging bij zakelijke kopers, leveringen buiten Nederland. Graag een regel per geval of een verwijzing naar je accountant.
Standaard: alleen Nederland, margeregeling waar de verkoper dat aangeeft, anders 21%.
Antwoord:

**4. Refunds en chargebacks**
Nu meldt het systeem elke terugbetaling (prijscorrectie, terugdraaien na geschil) per mail aan support; de terugbetaling zelf loopt handmatig via de provider.
Opties: (a) handmatig via support blijft, (b) automatisch via de PSP zodra die gekoppeld is.
Standaard: (a) tot de PSP er is, daarna (b).
Antwoord:

**5. Boekhoudkoppeling**
Opties: (a) csv-export (bestaat), (b) Moneybird, (c) Jortt, (d) anders.
Standaard: (a).
Antwoord:

**6. Prijzen**
Nodig: succesfee per pakket (nu Start 7,5%; Pro en Enterprise hebben geen tarief), maandprijs van Pro en Enterprise, prijs van Ivy Boost, opgeld voor de koper (nu 0%), borgdrempel (nu € 2.500).
Standaard: alles blijft zoals het nu staat; Pro en Enterprise worden niet aangeboden tot er een prijs is.
Antwoord:

**7. Termijnen**
Meldtermijn na levering: 48 uur (zoals gebouwd) of 3 werkdagen (zoals in de preview). Betaaltermijn 48 uur, reactietermijn verkoper 72 uur, houdtermijn voor uitbetaling 7 dagen.
Standaard: zoals gebouwd.
Antwoord:

---

## B. Identiteit en toegang

**8. Welke verificatieprovider voor KYC (identiteit) en KYB (bedrijf)?**
De statusmachine, de compliance-queue en de permission engine staan; nu beoordeelt een medewerker handmatig, meestal binnen een werkdag.
Opties: bijvoorbeeld Veriff, Onfido, iDIN; plus een sleutel voor de KvK-API.
Standaard: handmatige beoordeling blijft.
Nodig van jou: account bij de provider en de KvK-API-sleutel.
Antwoord:

**9. Landen en drempels**
Welke landen mogen kopen en verkopen, en vanaf welk bedrag is verificatie verplicht vóór een bod?
Standaard: alleen Nederland; verificatie vóór het eerste bod, ongeacht bedrag.
Antwoord:

**10. Doelgroep**
Alleen zakelijk, of ook particulier? Dit raakt de registratie, de btw en welke verificatie wie doorloopt.
Standaard: zakelijk.
Antwoord:

**11. Tweede factor**
De masterbriefing vraagt TOTP via een authenticator-app met recoverycodes, en geen sms. Dat vraagt een extra pakket.
Opties: (a) akkoord voor het pakket, mailcode blijft als terugval, (b) alleen de mailcode.
Standaard: (a).
Antwoord:

**12. Telefoonnummer**
Nu wordt een nummer alleen opgeslagen, niet gecontroleerd.
Opties: (a) sms-dienst koppelen, (b) de stap schrappen.
Standaard: (b).
Antwoord:

---

## C. Verkopers, reviews en reputatie

**13. Zijn gerealiseerde verkoopprijzen openbaar?**
Het verkopersprofiel toont verkochte kavels zonder prijs.
Opties: (a) nooit, (b) altijd, (c) alleen bij veilingen, niet bij koop direct.
Standaard: (a).
Antwoord:

**14. Reviewbeleid**
Nu: alleen de koper van een afgeronde order, direct publiek, reactie van de verkoper, nooit verwijderd op verzoek. Te bouwen zonder besluit: melden, moderatie, bezwaar.
Vragen: wat is een geldige reden om een review te verwijderen, en wie beslist (beheer, of een tweede persoon)?
Standaard: beheer verwijdert alleen bij beledigingen, persoonsgegevens of een aantoonbaar niet-plaatsgevonden transactie, met motivering in het auditlog.
Antwoord:

**15. Reputatiescore**
Twee gescheiden waarden bestaan: sterren en de Trust Index. De masterbriefing vraagt tijdvensters, minimumaantallen en een beroepsproces.
Vragen: vanaf hoeveel transacties tonen we een score, en over welke periode telt het?
Standaard: vanaf 10 afgeronde transacties, laatste 12 maanden.
Antwoord:

**16. Publicatierechten: vooraf of achteraf modereren?**
Nu: een kavel gaat direct live en beheer beoordeelt daarna, met een risicoscan.
Opties: (a) zo laten, (b) eerst goedkeuren, dan live.
Standaard: (a).
Antwoord:

**17. Verboden categorieën**
De lijst is leeg; de wizard blokkeert dus niets.
Nodig van jou: de lijst (bijvoorbeeld wapens, levende dieren, geneesmiddelen).
Antwoord:

**18. Biedregel bij de reserveprijs**
Als een automatisch maximum de reserveprijs haalt: de prijs tot de reserve tillen (gangbaar) of op het hoogste tegenbod laten?
Standaard: tillen tot de reserve.
Antwoord:

---

## D. Mail en CMS

**19. Eazyonline Mail Builder: hergebruiken, en onder welke afspraak?**
De masterbriefing vraagt eerst een vergelijking. Die code zit niet in de Aucvy-repository.
Nodig van jou: toegang tot de Mail Builder-code en de afspraak over IP en licentie.
Opties: (a) hergebruiken, (b) eigen templates in Aucvy bouwen.
Standaard: (b) voor de transactionele mails; marketingmails pas na jouw keuze.
Antwoord:

**20. Mailprovider en afzenderdomein**
Nodig: welke provider (bijvoorbeeld Postmark, Mailgun, Resend), welk afzenderadres, en SPF, DKIM en DMARC op het domein.
Standaard: geen; lokaal gaat alles naar het logbestand.
Antwoord:

**21. Eazyonline CMS: hergebruiken, en onder welke afspraak?**
Zelfde vraag als bij de Mail Builder. Zonder CMS blijven de uitlegpagina's code en komt er geen blog.
Opties: (a) hergebruiken, (b) klein eigen CMS in Aucvy (pagina's, blog, redirects), (c) voorlopig geen CMS.
Standaard: (c); redirects en noindex zijn al gebouwd.
Antwoord:

**22. Tenants (white-label omgevingen): ja of nee?**
De masterbriefing gaat uit van tenants. Dat raakt elke tabel, query, cache en mailtemplate; het is de grootste verbouwing van alles en moet vóór de rest besloten worden.
Standaard: nee, één omgeving; branding en rechten per organisatie.
Antwoord:

**23. AI-regio**
Anthropic via het EU-endpoint zodra beschikbaar, of standaard?
Standaard: EU zodra beschikbaar.
Antwoord:

---

## E. Juridisch en marketing

**24. Juridische teksten**
Nodig van jou: algemene voorwaarden, veilingvoorwaarden, privacyverklaring, cookieverklaring. En: komt er een cookiebanner?
Standaard: geen livegang zonder deze teksten; de pagina's staan er binnen een dag na ontvangst.
Antwoord:

**25. De home**
De nieuwe home toont een demo met fictieve bedragen en functies die nog in ontwikkeling zijn (promotie op andere kanalen, kopers uitnodigen, biedplannen), nu met een disclaimer. En de drie gezichten in de hero zijn stockfoto's.
Opties: (a) zo laten met disclaimer, (b) beperken tot wat werkt, (c) eigen foto's aanleveren.
Standaard: (a).
Antwoord:

**26. Huisstijl voor de deel-visual**
De gedeelde advertentie (briefing onderdeel 7) vraagt formaten en stijl.
Nodig van jou: logo's, kleuren, gewenste formaten.
Antwoord:

---

## F. Pakketten en infrastructuur (één ja per regel)

**27. Beveiligingsupdates** van league/commonmark en symfony/http-foundation (composer) en vier ontwikkeltools (npm). Eén commando per kant, daarna de tests.
Standaard: wachten op akkoord, want het raakt de afhankelijkheden.
Antwoord:

**28. Sanctum** voor de import-API (kavels aanleveren via ERP of koppeling).
Antwoord:

**29. Larastan en Deptrac** (statische analyse in de CI).
Antwoord:

**30. Redis en Horizon** (queues en cache; nodig voor de kijkersteller op grote veilingen).
Antwoord:

**31. Meilisearch** (zoeken op volume; nu MySQL, goed tot enkele duizenden kavels).
Antwoord:

**32. Sentry** (foutmonitoring; nu de systeempagina en het logbestand).
Antwoord:

**33. Staging-omgeving op Plesk** met sandbox-sleutels.
Nodig van jou: toegang tot de hosting.
Antwoord:

**34. Marktdatabron met licentie** voor vergelijkbare aanbiedingen met URL en datum in de waarde-indicatie.
Antwoord:

**35. Vervoerder of transportpartner met API** (nu een nepindicatie).
Antwoord:

---

## G. Team

**36. Werkverdeling met Yaël**
De masterbriefing noemt Yaël naast Boyd. Welke modules doet wie, en wie beslist bij overlap?
Antwoord:

---

## Wat al klaar staat en niet op jou wacht

Veilingen, bieden, afslag, koop direct, orders, uitbetalingsstatus, geschilroute, verificatiestatussen met handmatige beoordeling, 2FA per mail, organisatierollen, Auction Studio, import, Ivy (herkennen, schrijven, waarderen, documenten, biedadvies, vertalen), verkopersprofiel met reviews en Trust Index, meldingen, volglijst, alerts, backoffice, systeempagina, AVG-export en -verwijdering, doorverwijzingen, SEO. In totaal 873 geautomatiseerde tests.
