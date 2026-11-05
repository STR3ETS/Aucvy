<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Auctions\Support\BidGuards;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Lots\Interest\Interest;
use App\Lots\LotFilters;
use App\Lots\LotSearch;
use App\Lots\Preflight;
use App\Messaging\StartConversation;
use App\Models\Conversation;
use App\Models\IvyActionProposal;
use App\Models\IvyHandoff;
use App\Models\IvyLead;
use App\Models\IvyLotMode;
use App\Models\Lot;
use App\Models\Order;
use App\Models\SavedSearch;
use App\Models\WatchlistItem;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Elke tool heeft een JSON-schema, een policy (welke rol, lees, schrijf of
 * voorstel) en een handler die met de rechten van de klant draait. "Door
 * de hele database" betekent alle open kavels en publieke eindprijzen;
 * concepten, ingetrokken kavels en gegevens van anderen vallen er altijd
 * buiten. Een voorstel doet niets tot de klant bevestigt.
 */
final class ToolRegistry
{
    public const string READ = 'read';

    public const string WRITE = 'write';

    public const string PROPOSAL = 'proposal';

    /** @var array<string, array{roles: list<string>, type: string, description: string, schema: array<string, mixed>, flag?: string}> */
    private const TOOLS = [
        'search_lots' => ['roles' => ['visitor', 'buyer', 'seller'], 'type' => self::READ, 'description' => 'Zoek open kavels op tekst, categorie, locatie, prijs en vorm. Geeft ook de interpretatie terug.', 'schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'max_cents' => ['type' => 'integer'], 'category' => ['type' => 'string']], 'required' => ['query']]],
        'get_lot' => ['roles' => ['visitor', 'buyer', 'seller'], 'type' => self::READ, 'description' => 'Publieke kavelvelden plus of de reserve is gehaald. De eigenaar krijgt ook reserve en concept.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'compare_lots' => ['roles' => ['visitor', 'buyer'], 'type' => self::READ, 'description' => 'Maximaal vier kavels naast elkaar op vaste velden.', 'schema' => ['type' => 'object', 'properties' => ['lot_ids' => ['type' => 'array', 'items' => ['type' => 'integer']]], 'required' => ['lot_ids']]],
        'calc_buyer_total' => ['roles' => ['visitor', 'buyer'], 'type' => self::READ, 'description' => 'Bod, opgeld, administratie, transport en btw voor een kavel, via dezelfde rekenregel als de order.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'bid_cents' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'calc_seller_net' => ['roles' => ['visitor', 'seller'], 'type' => self::READ, 'description' => 'Bedrag, staffel, fee en netto voor de verkoper. Een gast rekent met het tarief voor een nieuwe verkoper.', 'schema' => ['type' => 'object', 'properties' => ['amount_cents' => ['type' => 'integer']], 'required' => ['amount_cents']]],
        'get_value_estimate' => ['roles' => ['buyer', 'seller'], 'type' => self::READ, 'description' => 'Waarderange met bron, aantal, datum en zekerheid; kan "te weinig data" teruggeven.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'kb_search' => ['roles' => ['visitor', 'buyer', 'seller'], 'type' => self::READ, 'description' => 'Kennisbank en platformfeiten. Geeft artikel, versie en bronlink.', 'schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']]],
        'get_my_activity' => ['roles' => ['buyer', 'seller'], 'type' => self::READ, 'description' => 'Eigen biedingen, volglijst, kavels, orders, taken en uitbetalingen.', 'schema' => ['type' => 'object', 'properties' => []]],
        'get_lot_stats' => ['roles' => ['seller'], 'type' => self::READ, 'description' => 'Views, volgers, bieders op biednaam en vragen, alleen voor eigen kavels.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'get_next_steps' => ['roles' => ['buyer', 'seller'], 'type' => self::READ, 'description' => 'De regelgestuurde takenlijst van "Wacht op jou" voor een concept.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']]]],
        'run_listing_check' => ['roles' => ['seller'], 'type' => self::READ, 'description' => 'De bestaande Ivy-toets op een concept; geeft ontbrekende punten terug.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'save_search' => ['roles' => ['buyer'], 'type' => self::WRITE, 'description' => 'Bewaar een zoekvraag als alert. Alert-voorkeuren blijven van de klant.', 'schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'max_cents' => ['type' => 'integer']], 'required' => ['query']]],
        'follow_lot' => ['roles' => ['buyer'], 'type' => self::WRITE, 'description' => 'Volgen of ontvolgen.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'follow' => ['type' => 'boolean']], 'required' => ['lot_id']]],
        'set_lot_mode' => ['roles' => ['buyer'], 'type' => self::WRITE, 'description' => 'Alleen melden of eerst vragen voor een kavel. Automatisch tot limiet loopt apart, als voorstel.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'mode' => ['type' => 'string', 'enum' => ['notify', 'ask']]], 'required' => ['lot_id', 'mode']]],
        'ask_seller' => ['roles' => ['buyer'], 'type' => self::WRITE, 'description' => 'Zet een vraag bij de kavel via de bestaande berichten. Filtert contactgegevens.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'question' => ['type' => 'string']], 'required' => ['lot_id', 'question']]],
        'propose_bid' => ['roles' => ['buyer'], 'type' => self::PROPOSAL, 'flag' => 'ivy.proposals', 'description' => 'Zet een bod klaar; opent het bevestigingsscherm. Biedt nooit zelf.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'amount_cents' => ['type' => 'integer']], 'required' => ['lot_id', 'amount_cents']]],
        'propose_auto_bid' => ['roles' => ['buyer'], 'type' => self::PROPOSAL, 'flag' => 'ivy.proposals', 'description' => 'Stel automatisch bieden tot een limiet voor; nooit hoger dan wat de klant noemt.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'limit_cents' => ['type' => 'integer']], 'required' => ['lot_id', 'limit_cents']]],
        'propose_dutch_accept' => ['roles' => ['buyer'], 'type' => self::PROPOSAL, 'flag' => 'auctions.dutch', 'description' => 'Afmijnen op de actuele prijs, met dezelfde bevestiging.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'propose_buy_now' => ['roles' => ['buyer'], 'type' => self::PROPOSAL, 'flag' => 'ivy.proposals', 'description' => 'Koop direct, met dezelfde bevestiging.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'propose_reserve_change' => ['roles' => ['seller'], 'type' => self::PROPOSAL, 'flag' => 'ivy.reserve_change', 'description' => 'Reserve omlaag, alleen omlaag; achter een vlag.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer'], 'reserve_cents' => ['type' => 'integer']], 'required' => ['lot_id', 'reserve_cents']]],
        'propose_publish' => ['roles' => ['seller'], 'type' => self::PROPOSAL, 'flag' => 'ivy.proposals', 'description' => 'Publiceren na voorbeeld en geslaagde toets.', 'schema' => ['type' => 'object', 'properties' => ['lot_id' => ['type' => 'integer']], 'required' => ['lot_id']]],
        'capture_lead' => ['roles' => ['visitor'], 'type' => self::WRITE, 'description' => 'Naam en e-mail, alleen als de bezoeker die geeft, met toestemmingstekst.', 'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'email' => ['type' => 'string'], 'phone' => ['type' => 'string'], 'intent' => ['type' => 'string'], 'temperature' => ['type' => 'string', 'enum' => ['warm', 'groot', 'lauw']]], 'required' => ['email']]],
        'create_handoff' => ['roles' => ['visitor', 'buyer', 'seller'], 'type' => self::WRITE, 'description' => 'Dossier met samenvatting naar het team: geschil, echtheid, verboden waar, betaalprobleem, juridisch of op verzoek.', 'schema' => ['type' => 'object', 'properties' => ['reason' => ['type' => 'string'], 'summary' => ['type' => 'string']], 'required' => ['reason', 'summary']]],
        'open_page' => ['roles' => ['visitor', 'buyer', 'seller'], 'type' => self::READ, 'description' => 'Geef een knop naar een pagina uit de lijst "Pagina\'s" in je context, of naar een kavel. Met direct=true gaat de klant er meteen heen; gebruik dat alleen als hij daar zelf om vraagt ("ga naar", "open").', 'schema' => ['type' => 'object', 'properties' => ['page' => ['type' => 'string', 'description' => 'Sleutel uit de lijst, of "kavel" samen met lot_id.'], 'lot_id' => ['type' => 'integer'], 'direct' => ['type' => 'boolean']], 'required' => ['page']]],
    ];

    /** Wat de klant ziet terwijl een tool loopt. */
    private const STEPS = [
        'search_lots' => 'Kavels zoeken',
        'get_lot' => 'Kavel bekijken',
        'compare_lots' => 'Kavels vergelijken',
        'calc_buyer_total' => 'Kosten uitrekenen',
        'calc_seller_net' => 'Opbrengst uitrekenen',
        'get_value_estimate' => 'Waarde-indicatie opzoeken',
        'kb_search' => 'Kennisbank raadplegen',
        'get_my_activity' => 'Je biedingen en bestellingen bekijken',
        'get_lot_stats' => 'Interesse in je kavel bekijken',
        'get_next_steps' => 'Nagaan wat nog moet',
        'run_listing_check' => 'Je concept toetsen',
        'save_search' => 'Zoekvraag bewaren',
        'follow_lot' => 'Volglijst bijwerken',
        'set_lot_mode' => 'Meldniveau instellen',
        'ask_seller' => 'Vraag bij de kavel zetten',
        'propose_bid' => 'Bod klaarzetten',
        'propose_auto_bid' => 'Automatisch bieden klaarzetten',
        'propose_dutch_accept' => 'Afmijnen klaarzetten',
        'propose_buy_now' => 'Koop direct klaarzetten',
        'propose_reserve_change' => 'Reservewijziging klaarzetten',
        'propose_publish' => 'Publicatie klaarzetten',
        'capture_lead' => 'Gegevens noteren',
        'create_handoff' => 'Doorgeven aan het team',
        'open_page' => 'Pagina opzoeken',
    ];

    public function __construct(
        private readonly KnowledgeBase $kb,
        private readonly Proposals $proposals,
        private readonly LotSearch $search,
        private readonly BidGuards $guards,
        private readonly Pages $pages,
    ) {}

    /**
     * De regel die in het paneel verschijnt terwijl deze tool loopt.
     *
     * @param  array<string, mixed>  $input
     */
    public function step(string $name, array $input): string
    {
        if ($name === 'search_lots' && trim((string) ($input['query'] ?? '')) !== '') {
            return 'Zoeken naar "'.Str::limit(trim((string) $input['query']), 40).'"';
        }

        return self::STEPS[$name] ?? 'Even kijken';
    }

    /**
     * De tools voor deze rol, in het formaat van de API.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function toolsFor(string $role): array
    {
        $tools = [];

        foreach (self::TOOLS as $name => $tool) {
            if ($this->allowed($name, $role)) {
                $schema = $tool['schema'];

                // Een tool zonder invoer: PHP maakt van een lege array een lijst, de API wil een object.
                if (($schema['properties'] ?? null) === []) {
                    $schema['properties'] = new stdClass;
                }

                $tools[] = ['name' => $name, 'description' => $tool['description'], 'input_schema' => $schema];
            }
        }

        return $tools;
    }

    public function allowed(string $name, string $role): bool
    {
        $tool = self::TOOLS[$name] ?? null;

        if ($tool === null || ! in_array($role, $tool['roles'], true)) {
            return false;
        }

        return ! isset($tool['flag']) || IvyFlags::on($tool['flag']);
    }

    public function type(string $name): string
    {
        return self::TOOLS[$name]['type'] ?? self::READ;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function call(string $name, array $input, ToolContext $context): array
    {
        if (! $this->allowed($name, $context->role)) {
            return ['error' => 'Dit kan ik in deze rol niet doen.'];
        }

        try {
            return match ($name) {
                'search_lots' => $this->searchLots($input, $context),
                'get_lot' => $this->getLot($input, $context),
                'compare_lots' => $this->compareLots($input),
                'calc_buyer_total' => $this->calcBuyerTotal($input, $context),
                'calc_seller_net' => $this->calcSellerNet($input, $context),
                'get_value_estimate' => $this->valueEstimate($input),
                'kb_search' => $this->kbSearch($input, $context),
                'get_my_activity' => $this->myActivity($context),
                'get_lot_stats' => $this->lotStats($input, $context),
                'get_next_steps' => $this->nextSteps($input, $context),
                'run_listing_check' => $this->nextSteps($input, $context),
                'save_search' => $this->saveSearch($input, $context),
                'follow_lot' => $this->followLot($input, $context),
                'set_lot_mode' => $this->setLotMode($input, $context),
                'ask_seller' => $this->askSeller($input, $context),
                'propose_bid' => $this->propose(IvyActionProposal::BID, $input, $context),
                'propose_auto_bid' => $this->propose(IvyActionProposal::AUTO_BID, $input, $context),
                'propose_dutch_accept' => $this->propose(IvyActionProposal::DUTCH_ACCEPT, $input, $context),
                'propose_buy_now' => $this->propose(IvyActionProposal::BUY_NOW, $input, $context),
                'propose_reserve_change' => $this->propose(IvyActionProposal::RESERVE_CHANGE, $input, $context),
                'propose_publish' => $this->propose(IvyActionProposal::PUBLISH, $input, $context),
                'capture_lead' => $this->captureLead($input, $context),
                'create_handoff' => $this->createHandoff($input, $context),
                'open_page' => $this->openPage($input, $context),
                default => ['error' => 'Onbekende tool.'],
            };
        } catch (Throwable $exception) {
            report($exception);

            return ['error' => 'Dat lukte nu niet: '.Str::limit($exception->getMessage(), 160)];
        }
    }

    // Lezen ----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function searchLots(array $input, ToolContext $context): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $max = isset($input['max_cents']) ? (int) $input['max_cents'] : (preg_match('/(?:max(?:imaal)?|tot|onder)\s*€?\s*([\d.]{3,})/iu', $query, $match) === 1 ? (int) str_replace('.', '', $match[1]) * 100 : null);
        $terms = trim((string) preg_replace('/(?:max(?:imaal)?|tot|onder)\s*€?\s*[\d.]{3,}(?:\s*euro)?|\b\d+\s*(?:stuks|x)\b|\brond\s+\w+\b|\bbinnen\s+\d+\s*km\b/iu', '', $query));

        $filters = new LotFilters(query: $terms !== '' ? $terms : null, category: isset($input['category']) ? (string) $input['category'] : null, priceMaxCents: $max);
        $results = $this->search->results($filters);

        $chips = array_values(array_filter([
            $terms !== '' ? $terms : null,
            $max !== null ? 'max '.Money::format($max) : null,
            preg_match('/\b(\d+)\s*(?:stuks|x)\b/iu', $query, $count) === 1 ? $count[1].' stuks' : null,
        ]));

        return [
            'count' => $results->total(),
            'interpretation' => $chips === [] ? '' : 'Ik lees: '.implode(', ', $chips).'.',
            'chips' => $chips,
            'lots' => collect($results->items())->take(5)->map(fn (Lot $lot) => $this->lotCard($lot))->values()->all(),
            'amounts' => collect($results->items())->take(5)->map(fn (Lot $lot) => $lot->currentPriceCents())->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function getLot(array $input, ToolContext $context): array
    {
        $lot = $this->publicLot((int) ($input['lot_id'] ?? 0), $context);

        if ($lot === null) {
            return ['error' => 'Die kavel ken ik niet of is niet openbaar.'];
        }

        $lot->loadMissing(['auction', 'category', 'location']);
        $owner = $context->user !== null && $lot->isManagedBy($context->user);
        $auction = $lot->auction;
        $reserve = $auction?->reserve_price_cents;

        $summary = sprintf('%s (%s): %s. Status %s.', $lot->title, $lot->public_id, $lot->category?->name ?? 'categorie onbekend', $lot->status->label());

        if ($auction !== null) {
            $summary .= ' Huidig bod '.Money::format($lot->currentPriceCents()).', '.$auction->bid_count.' biedingen, sluit '.$auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm').'.';
            $summary .= $reserve === null ? ' Geen reserveprijs.' : ' Er is een reserveprijs, die is '.($auction->current_price_cents >= $reserve ? 'gehaald' : 'nog niet gehaald').'. Hoe hoog die is mag ik niet zeggen.';
        }

        return [
            'summary' => $summary,
            'card' => $this->lotCard($lot),
            'reserve_met' => $reserve !== null && $auction !== null ? $auction->current_price_cents >= $reserve : null,
            'reserve_cents' => $owner ? $reserve : null,
            'amounts' => [$lot->currentPriceCents()],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function compareLots(array $input): array
    {
        $ids = array_slice(array_map('intval', (array) ($input['lot_ids'] ?? [])), 0, 4);
        $lots = Lot::query()->public()->whereIn('id', $ids)->with(['auction', 'category', 'location'])->get();

        return ['lots' => $lots->map(fn (Lot $lot) => $this->lotCard($lot) + ['condition' => $lot->condition?->label(), 'year' => $lot->year, 'quantity' => $lot->quantity, 'warranty' => $lot->warranty])->values()->all(), 'amounts' => $lots->map(fn (Lot $lot) => $lot->currentPriceCents())->all()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calcBuyerTotal(array $input, ToolContext $context): array
    {
        $lot = $this->publicLot((int) ($input['lot_id'] ?? 0), $context);

        if ($lot === null) {
            return ['error' => 'Die kavel ken ik niet of is niet openbaar.'];
        }

        $bid = (int) ($input['bid_cents'] ?? 0) > 0 ? (int) $input['bid_cents'] : max($lot->currentPriceCents(), $lot->auction ? $this->guards->minimumBid($lot->auction) : 0);
        $costs = Money::forOrder($lot, $bid, $lot->transport_from_cents ?? 0);
        $lines = collect($costs->lines())->map(fn ($line) => $line->label.' '.Money::format($line->amountCents));

        return [
            'bid' => Money::format($bid),
            'total' => Money::format($costs->totalCents),
            'lines' => $lines->all(),
            'lines_text' => $lines->implode(', ').'.',
            'amounts' => [$bid, $costs->totalCents, $costs->adminFeeCents, $costs->transportCents, $costs->vatCents, $costs->buyerPremiumCents],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calcSellerNet(array $input, ToolContext $context): array
    {
        $amount = max(0, (int) ($input['amount_cents'] ?? 0));
        $company = $context->user?->company;
        $proceeds = Money::payoutFor($amount, $company);
        $percentage = PlatformFacts::feePercentageFor($company);

        return [
            'amount' => Money::format($amount),
            'fee_percentage' => rtrim(rtrim(number_format($percentage, 1, ',', '.'), '0'), ',').'%',
            'fee' => Money::format($proceeds->feeCents),
            'net' => Money::format($proceeds->payoutCents),
            'note' => $company === null ? 'Gerekend met het tarief voor een nieuwe verkoper.' : 'Gerekend met jouw staffel op basis van je omzet van 12 maanden.',
            'amounts' => [$amount, $proceeds->feeCents, $proceeds->payoutCents],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function valueEstimate(array $input): array
    {
        $lot = Lot::query()->find((int) ($input['lot_id'] ?? 0));

        if ($lot === null || ! $lot->hasValueEstimate()) {
            return ['text' => 'Te weinig data voor een indicatie van de waarde.', 'amounts' => []];
        }

        return [
            'text' => sprintf('Indicatie %s tot %s, zekerheid %s. Bron: eigen eindprijzen van vergelijkbare kavels. Een indicatie, geen garantie.', Money::format($lot->value_min_cents), Money::format($lot->value_max_cents), $lot->value_confidence->label()),
            'amounts' => [$lot->value_min_cents, $lot->value_max_cents],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function kbSearch(array $input, ToolContext $context): array
    {
        $query = (string) ($input['query'] ?? '');

        if (Str::lower($query) === 'pitch') {
            if ($context->conversation->pitched) {
                return ['answer' => 'De korte versie heb je al gehad. Zeg maar waar je naar zoekt of wat je wilt verkopen, dan help ik meteen.'];
            }

            $context->conversation->forceFill(['pitched' => true])->save();

            return ['answer' => $this->kb->pitch('pitch'), 'source' => 'kennisbank/pitch', 'version' => KnowledgeBase::PITCH_VERSION];
        }

        $hit = $this->kb->search($query, $context->role);

        if ($hit === null) {
            return ['answer' => 'Dat weet ik niet zeker. Ik zoek het uit en geef het door aan het team; wil je intussen iets anders weten?', 'unknown' => true];
        }

        return $hit;
    }

    /**
     * @return array<string, mixed>
     */
    private function myActivity(ToolContext $context): array
    {
        $user = $context->user;

        if ($user === null) {
            return ['error' => 'Daarvoor moet je ingelogd zijn.'];
        }

        $bids = $user->bids()->with('auction.lot')->latest('placed_at')->limit(5)->get();
        $orders = Order::query()->where(fn ($query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))->with('lot')->latest('id')->limit(5)->get();
        $watching = WatchlistItem::query()->where('user_id', $user->id)->count();
        $lots = Lot::query()->where('seller_id', $user->id)->whereNotIn('status', [LotStatus::Cancelled])->count();

        $text = sprintf('Je hebt %d recente biedingen, %d bestellingen, %d kavels op je volglijst en %d eigen kavels.', $bids->count(), $orders->count(), $watching, $lots);

        foreach ($bids as $bid) {
            $text .= ' Bod '.Money::format($bid->amount_cents).' op '.($bid->auction?->lot?->title ?? 'kavel').' ('.$bid->status->value.').';
        }

        foreach ($orders as $order) {
            $text .= ' Bestelling '.$order->order_number.' ('.$order->lot?->title.'): '.$order->status->label().'.';
        }

        return ['text' => $text, 'amounts' => $bids->map(fn ($bid) => $bid->amount_cents)->all()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function lotStats(array $input, ToolContext $context): array
    {
        $lot = Lot::query()->find((int) ($input['lot_id'] ?? 0));

        if ($lot === null || $context->user === null || ! $lot->isManagedBy($context->user)) {
            return ['error' => 'Alleen voor je eigen kavels.'];
        }

        $stats = Interest::forSeller($lot);
        $questions = Conversation::query()->where('lot_id', $lot->id)->count();

        return [
            'text' => sprintf('%s: %d kijkers, %d weergaven, %d volgers, %d bieders, %d gedeeld, %d vragen van kopers. Hoogste bod %s.', $lot->title, $stats['viewers'], $stats['views'], $stats['saved'], $stats['bidders'], $stats['shared'], $questions, $lot->auction ? Money::format($lot->currentPriceCents()) : 'geen veiling'),
            'amounts' => [$lot->currentPriceCents()],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function nextSteps(array $input, ToolContext $context): array
    {
        $lot = Lot::query()->find((int) ($input['lot_id'] ?? 0)) ?? $context->lot;

        if ($lot === null || $context->user === null || ! $lot->isManagedBy($context->user)) {
            return ['text' => 'Ik heb geen concept van jou gevonden om door te lopen.', 'items' => []];
        }

        $preflight = app(Preflight::class)->for($lot, $context->user);
        $open = collect($preflight['checks'] ?? [])->reject(fn ($check) => (bool) ($check['ok'] ?? false))->map(fn ($check) => (string) ($check['label'] ?? $check['key'] ?? ''))->filter()->values();

        return [
            'text' => $open->isEmpty() ? 'Alles staat klaar; je kunt publiceren.' : 'Nog te doen: '.$open->implode(', ').'.',
            'items' => $open->all(),
            'ok' => (bool) ($preflight['ok'] ?? false),
        ];
    }

    // Schrijven -------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function saveSearch(array $input, ToolContext $context): array
    {
        if ($context->user === null) {
            return ['error' => 'Een zoekvraag bewaren kan met een account.'];
        }

        $query = trim((string) ($input['query'] ?? ''));
        $params = array_filter(['q' => $query, 'tot' => isset($input['max_cents']) ? intdiv((int) $input['max_cents'], 100) : null]);
        $filters = LotFilters::fromRequest(Request::create('/veilingen', 'GET', $params));

        if (! $filters->hasAny()) {
            return ['error' => 'Een zoekvraag heeft minstens een zoekterm nodig.'];
        }

        $saved = SavedSearch::create(['user_id' => $context->user->id, 'name' => Str::limit($query, 80, ''), 'query' => $filters->toQuery(), 'interpreted' => null]);

        return ['saved_search_id' => $saved->id, 'text' => 'Zoekvraag "'.$saved->name.'" bewaard.', 'done' => ['text' => 'Zoekvraag "'.$saved->name.'" bewaard als alert', 'url' => route('dashboard.alerts', [], false)]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function followLot(array $input, ToolContext $context): array
    {
        $lot = $this->publicLot((int) ($input['lot_id'] ?? 0), $context);

        if ($lot === null || $context->user === null) {
            return ['error' => 'Volgen kan met een account op een openbare kavel.'];
        }

        if (($input['follow'] ?? true) === false) {
            WatchlistItem::query()->where('user_id', $context->user->id)->where('lot_id', $lot->id)->delete();

            return ['text' => 'Je volgt "'.$lot->title.'" niet meer.', 'done' => ['text' => '"'.$lot->title.'" van je volglijst gehaald', 'url' => route('dashboard.watchlist', [], false)]];
        }

        WatchlistItem::firstOrCreate(['user_id' => $context->user->id, 'lot_id' => $lot->id]);

        return ['text' => 'Je volgt "'.$lot->title.'" nu.', 'done' => ['text' => '"'.$lot->title.'" staat op je volglijst', 'url' => route('dashboard.watchlist', [], false)]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function setLotMode(array $input, ToolContext $context): array
    {
        $lot = $this->publicLot((int) ($input['lot_id'] ?? 0), $context);
        $mode = (string) ($input['mode'] ?? IvyLotMode::NOTIFY);

        if ($lot === null || $context->user === null || ! in_array($mode, [IvyLotMode::NOTIFY, IvyLotMode::ASK], true)) {
            return ['error' => 'Dat niveau kan ik hier niet zetten.'];
        }

        app(LotModes::class)->set($context->user, $lot, $mode);

        return ['text' => 'Voor "'.$lot->title.'" '.($mode === IvyLotMode::ASK ? 'zet ik bij elk moment een voorstel klaar en vraag ik om een ja.' : 'meld ik alleen wat er speelt; jij doet de rest.'), 'done' => ['text' => 'Meldniveau voor "'.$lot->title.'" aangepast', 'url' => route('ivy.inbox', [], false)]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function askSeller(array $input, ToolContext $context): array
    {
        $lot = $this->publicLot((int) ($input['lot_id'] ?? 0), $context);

        if ($lot === null || $context->user === null) {
            return ['error' => 'Een vraag stellen kan met een account op een openbare kavel.'];
        }

        $conversation = app(StartConversation::class)->asBuyer($lot, $context->user, Conversation::KIND_QUESTION, (string) ($input['question'] ?? ''), null, $context->ip);

        return ['text' => 'Je vraag staat bij de kavel; de verkoper krijgt bericht.', 'conversation_id' => $conversation->id, 'done' => ['text' => 'Vraag gesteld bij "'.$lot->title.'"', 'url' => route('messages.show', $conversation, false)]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function propose(string $type, array $input, ToolContext $context): array
    {
        if ($context->user === null) {
            return ['error' => 'Daarvoor moet je ingelogd zijn.'];
        }

        $lot = Lot::query()->find((int) ($input['lot_id'] ?? 0)) ?? $context->lot;

        if ($lot === null) {
            return ['error' => 'Ik weet niet om welke kavel het gaat.'];
        }

        $proposal = $this->proposals->create($type, $context->user, $lot, $input, $context->conversation);

        return [
            'proposal_id' => $proposal->id,
            'url' => route('ivy.proposals.show', $proposal),
            'text' => $this->proposals->describe($proposal).' Bevestig het op het scherm; ik voer niets uit zonder jouw ja.',
            'card' => ['type' => 'proposal', 'id' => $proposal->id, 'title' => $proposal->typeLabel(), 'amount' => $proposal->amount_cents !== null ? Money::format($proposal->amount_cents) : null, 'url' => route('ivy.proposals.show', $proposal)],
            'amounts' => array_values(array_filter([$proposal->amount_cents, $proposal->expected_current_cents])),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function captureLead(array $input, ToolContext $context): array
    {
        $email = Str::lower(trim((string) ($input['email'] ?? '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Dat e-mailadres klopt niet.'];
        }

        $lead = IvyLead::create([
            'conversation_id' => $context->conversation->id,
            'name' => Str::limit((string) ($input['name'] ?? 'Bezoeker'), 120, ''),
            'email' => $email,
            'phone' => isset($input['phone']) ? Str::limit((string) $input['phone'], 40, '') : null,
            'intent' => isset($input['intent']) ? Str::limit((string) $input['intent'], 40, '') : null,
            'temperature' => in_array($input['temperature'] ?? null, ['warm', 'groot', 'lauw'], true) ? $input['temperature'] : 'warm',
            'summary' => $context->conversation->summary,
            'consent_at' => now(),
            'consent_version' => 'v1',
        ]);

        return ['lead_id' => $lead->id, 'text' => 'Gegevens genoteerd.'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createHandoff(array $input, ToolContext $context): array
    {
        $handoff = IvyHandoff::create([
            'conversation_id' => $context->conversation->id,
            'reason' => Str::limit((string) ($input['reason'] ?? 'customer_request'), 60, ''),
            'summary' => Str::limit((string) ($input['summary'] ?? ''), 2000, ''),
            'created_at' => now(),
        ]);

        $context->conversation->forceFill(['status' => 'handed_off', 'handoff_at' => now()])->save();

        return ['handoff_id' => $handoff->id, 'text' => 'Doorgegeven aan het team.', 'done' => ['text' => 'Doorgegeven aan het team, met een samenvatting', 'url' => null]];
    }

    /**
     * Een knop naar een pagina uit de vaste lijst, of naar een kavel die
     * de klant mag zien. Nooit een adres dat het model zelf opgeeft.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function openPage(array $input, ToolContext $context): array
    {
        $key = Str::lower(trim((string) ($input['page'] ?? '')));
        $direct = ($input['direct'] ?? false) === true;

        if ($key === 'kavel' || isset($input['lot_id'])) {
            $lot = $this->publicLot((int) ($input['lot_id'] ?? 0), $context);

            if ($lot === null) {
                return ['error' => 'Die kavel kan ik niet openen.'];
            }

            $url = $lot->status->isPublic() ? route('lots.show', $lot, false) : route('dashboard.lots.show', $lot, false);

            return ['ok' => true, 'url' => $url, 'label' => $lot->title, 'text' => 'Knop naar "'.$lot->title.'" staat in het gesprek. Houd je antwoord kort.', 'link' => ['url' => $url, 'label' => Str::limit($lot->title, 60), 'direct' => $direct]];
        }

        $page = $this->pages->for($context->user)[$key] ?? null;

        if ($page === null) {
            return ['error' => 'Die pagina ken ik niet of is niet voor deze klant. Kies een sleutel uit de lijst in je context.'];
        }

        return ['ok' => true, 'url' => $page['url'], 'label' => $page['label'], 'text' => 'Knop naar "'.$page['label'].'" staat in het gesprek. Houd je antwoord kort.', 'link' => ['url' => $page['url'], 'label' => $page['label'], 'direct' => $direct]];
    }

    // Hulp ------------------------------------------------------------------

    private function publicLot(int $id, ToolContext $context): ?Lot
    {
        $lot = $id > 0 ? Lot::query()->find($id) : $context->lot;

        if ($lot === null) {
            return null;
        }

        if ($lot->status->isPublic() || ($context->user !== null && $lot->isManagedBy($context->user))) {
            return $lot;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function lotCard(Lot $lot): array
    {
        $lot->loadMissing(['auction', 'category', 'location', 'media']);

        return [
            'type' => 'lot',
            'id' => $lot->id,
            'title' => $lot->title,
            'meta' => trim(($lot->category?->name ?? '').($lot->location ? ' · '.$lot->location->city : '')),
            'price' => $lot->auction ? Money::format($lot->currentPriceCents()) : null,
            'price_label' => $lot->sale_type === SaleType::Dutch ? 'prijs nu' : (($lot->auction?->bid_count ?? 0) > 0 ? 'huidig bod' : 'startbod'),
            'ends' => $lot->auction && ! $lot->auction->isClosed() ? $lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : null,
            'badge' => $lot->warranty !== null && $lot->warranty !== 'none' ? 'Garantie vermeld' : null,
            'url' => route('lots.show', $lot),
            'image' => $lot->media->first()?->path,
        ];
    }
}
