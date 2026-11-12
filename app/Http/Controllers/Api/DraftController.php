<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\VatRegime;
use App\Http\Controllers\Controller;
use App\Lots\DraftAccess;
use App\Lots\DraftPayload;
use App\Lots\Provenance;
use App\Lots\PublicId;
use App\Lots\PublishLot;
use App\Lots\SplitDraft;
use App\Models\Category;
use App\Models\Lot;
use App\Support\Idempotency\Idempotency;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class DraftController extends Controller
{
    public function __construct(
        private readonly DraftAccess $access,
        private readonly DraftPayload $draftPayload,
    ) {}

    /**
     * Start een concept. Werkt zonder account: het token gaat als cookie mee
     * en Laravel versleutelt die (hfst. 13.4).
     */
    public function store(Request $request): JsonResponse
    {
        $token = Str::random(48);

        $lot = Lot::create([
            'public_id' => PublicId::fresh(),
            'seller_id' => $request->user()?->id,
            'company_id' => $request->user()?->company_id,
            'status' => LotStatus::Draft,
            'draft_token' => $token,
            'quantity' => 1,
            'unit' => 'stuk',
            'vat_regime' => VatRegime::Margin,
            'pickup_only' => true,
        ]);

        return response()
            ->json($this->payload($lot), 201)
            // Twee weken: "later verder" moet ook echt later kunnen zijn.
            ->cookie(DraftAccess::COOKIE, $token, 60 * 24 * 14);
    }

    public function show(Request $request, Lot $lot): JsonResponse
    {
        return response()->json($this->payload($this->access->resolve($request, $lot)));
    }

    /**
     * Autosave. De client knijpt af tot één call per 800 ms; hier is elke call
     * een gedeeltelijke update - alleen wat meekomt wordt aangeraakt.
     */
    public function update(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'category_id' => ['sometimes', 'nullable', Rule::exists(Category::class, 'id')],
            'condition' => ['sometimes', 'nullable', Rule::enum(LotCondition::class)],
            'brand' => ['sometimes', 'nullable', 'string', 'max:80'],
            'model' => ['sometimes', 'nullable', 'string', 'max:80'],
            'year' => ['sometimes', 'nullable', 'integer', 'between:1900,'.now()->year],
            'quantity' => ['sometimes', 'integer', 'between:1,100000'],
            // Waar de hoeveelheid in geteld wordt (besluit Raph: "hoeveelheid
            // van een eenheid" is een publicatiecriterium).
            'unit' => ['sometimes', 'nullable', Rule::in(array_keys((array) config('listings.unit_options')))],
            // Marge of btw, per kavel gekozen door de verkoper (besluit Don, vraag 3).
            'vat_regime' => ['sometimes', Rule::enum(VatRegime::class)],
            'pickup_only' => ['sometimes', 'boolean'],
            'viewing_available' => ['sometimes', 'boolean'],
            'transport_from_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /*
             | Kenmerken: sleutel naar waarde, zoals de wizard ze stuurt. Ze
             | landen als {value, source}; wat de verkoper zet is bron seller,
             | wat Ivy zette en onveranderd bleef houdt zijn bron.
             */
            'specifications' => ['sometimes', 'nullable', 'array', 'max:40'],
            'specifications.*' => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_scalar($value)) {
                    $fail('Een kenmerk is een enkele waarde.');
                } elseif (is_string($value) && mb_strlen($value) > 120) {
                    $fail('Een kenmerk is maximaal 120 tekens.');
                }
            }],

            /*
             | De verkoopvorm en de bedragen wachten in draft_meta tot de
             | publicatie er een veiling van maakt. Altijd centen, altijd int.
             */
            'sale_type' => ['sometimes', 'nullable', Rule::enum(SaleType::class)],
            'start_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'reserve_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'buy_now_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dutch_start_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dutch_floor_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dutch_step_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dutch_interval_s' => ['sometimes', 'nullable', 'integer', 'between:5,600'],
            'duration_days' => ['sometimes', 'integer', 'between:1,30'],
            'ownership_declared' => ['sometimes', 'boolean'],
            // Garantie en de verklaring over de documenten (vooraf modereren).
            'warranty' => ['sometimes', 'nullable', Rule::in(array_keys((array) config('listings.warranty_options')))],
            'warranty_until' => ['sometimes', 'nullable', 'date', 'after:today'],
            'documents_authentic' => ['sometimes', 'boolean'],

            /*
             | Stap 1: wat de verkoper in eigen woorden vertelt en hoeveel hij
             | verkoopt. Dat is invoer voor Ivy, niet de advertentie zelf: de
             | omschrijving schrijft Nova, de hoeveelheid zet de verkoper in
             | stap 4.
             */
            'intake' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'scope' => ['sometimes', 'nullable', Rule::in(['single', 'multiple', 'inventory'])],
        ]);

        $metaKeys = [
            'sale_type', 'start_price_cents', 'reserve_price_cents',
            'buy_now_price_cents', 'dutch_start_cents', 'dutch_floor_cents',
            'dutch_step_cents', 'dutch_interval_s', 'duration_days', 'ownership_declared',
            'warranty', 'warranty_until', 'documents_authentic', 'intake', 'scope',
        ];

        $meta = $lot->draft_meta ?? [];

        foreach ($metaKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $meta[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }

        if (array_key_exists('specifications', $validated)) {
            $validated['specifications'] = $this->mergeSpecifications($lot->specifications ?? [], $validated['specifications'] ?? []);
        }

        $lot->fill($validated);
        $lot->draft_meta = $meta;
        $lot->save();

        // Wat de verkoper zelf zet is bron "seller": wint van Ivy en hoeft
        // nooit bevestigd te worden.
        Provenance::markSeller($lot, array_keys($validated));

        return response()->json($this->payload($lot->fresh()));
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, array<string, mixed>>
     */
    private function mergeSpecifications(array $current, array $incoming): array
    {
        $merged = [];

        foreach ($incoming as $key => $value) {
            $key = mb_substr(trim((string) $key), 0, 40);

            if ($key === '' || $value === null || $value === '') {
                continue;
            }

            $existing = $current[$key] ?? null;
            $existingValue = is_array($existing) ? ($existing['value'] ?? null) : $existing;

            $merged[$key] = is_array($existing) && $existingValue === $value
                ? $existing
                : ['value' => $value, 'source' => 'seller'];
        }

        return $merged;
    }

    /**
     * Foto's van verschillende producten worden losse kavels, één per groep
     * (het voorstel van Ivy, of zoals de verkoper het aanpaste).
     */
    public function split(Request $request, Lot $lot, SplitDraft $split): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $validated = $request->validate([
            'groups' => ['required', 'array', 'min:2', 'max:20'],
            'groups.*' => ['required', 'array', 'min:1'],
            'groups.*.*' => ['required', 'integer'],
        ]);

        $split->handle($lot, array_values(array_map(fn (array $group): array => array_values(array_map('intval', $group)), $validated['groups'])));

        return response()->json($this->payload($lot->fresh()));
    }

    /**
     * De verkoper bevestigt velden die Ivy met weinig zekerheid invulde.
     * Zonder die bevestiging weigert het publiceren (vertrouwensregel 4).
     */
    public function confirm(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $validated = $request->validate([
            'fields' => ['required_without:all', 'array'],
            'fields.*' => ['string', Rule::in(Provenance::FIELDS)],
            'all' => ['sometimes', 'boolean'],
        ]);

        Provenance::confirm($lot, ($validated['all'] ?? false) ? Provenance::unconfirmed($lot) : ($validated['fields'] ?? []));

        return response()->json($this->payload($lot->fresh()));
    }

    /**
     * Publiceren. Hier - en niet eerder - is een account nodig (hfst. 13.4).
     */
    public function publish(Request $request, Lot $lot, PublishLot $publishLot): JsonResponse
    {
        $user = $request->user();

        /*
         | Al gepubliceerd door deze verkoper: dezelfde uitkomst teruggeven.
         | Dat vangt een herhaalde aanvraag na een timeout op, ook zonder
         | sleutel, en zonder dat het concept-toegangspad 404 geeft.
         */
        if ($user !== null && $lot->status !== LotStatus::Draft && $lot->seller_id === $user->id && $lot->published_at !== null) {
            return response()->json(['published' => true, 'url' => route('lots.show', $lot)]);
        }

        // Al ingediend en nog in beoordeling: ook dan dezelfde uitkomst.
        if ($user !== null && $lot->status === LotStatus::PendingReview && $lot->isManagedBy($user)) {
            return response()->json(['published' => false, 'submitted' => true, 'status' => $lot->status->value, 'url' => route('dashboard.lots')]);
        }

        $lot = $this->access->resolve($request, $lot);

        if ($user === null) {
            return response()->json([
                'message' => __('funnel.errors.login_required'),
                'login_url' => route('login'),
            ], 401);
        }

        /*
         | Indienen: Ivy controleert vóór het live gaat. Is hij meteen live
         | (snelle controle, of vooraf modereren staat uit), dan de
         | kavelpagina; anders het overzicht met de status "in beoordeling".
         */
        $publish = function () use ($publishLot, $lot, $user, $request): array {
            // Vers ophalen: een snelle controle kan hem al live gezet hebben.
            $result = $publishLot->handle($lot, $user, $request->ip())->fresh() ?? $lot;

            return $result->status === LotStatus::PendingReview || $result->status === LotStatus::Draft
                ? ['published' => false, 'submitted' => true, 'status' => $result->status->value, 'url' => route('dashboard.lots')]
                : ['published' => true, 'url' => route('lots.show', $result)];
        };

        $key = $request->header(Idempotency::HEADER) ?: $request->input('idempotency_key');

        return response()->json(is_string($key) && $key !== ''
            ? Idempotency::once("publish:{$lot->id}", $key, $publish)
            : $publish());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Lot $lot): array
    {
        return $this->draftPayload->for($lot, auth()->user());
    }
}
