<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Http\Controllers\Controller;
use App\Lots\DraftAccess;
use App\Lots\DraftPayload;
use App\Lots\PublishLot;
use App\Models\Category;
use App\Models\Lot;
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
            'public_id' => $this->freshPublicId(),
            'seller_id' => $request->user()?->id,
            'company_id' => $request->user()?->company_id,
            'status' => LotStatus::Draft,
            'draft_token' => $token,
            'quantity' => 1,
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
            'pickup_only' => ['sometimes', 'boolean'],
            'viewing_available' => ['sometimes', 'boolean'],
            'transport_from_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],

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
        ]);

        $metaKeys = [
            'sale_type', 'start_price_cents', 'reserve_price_cents',
            'buy_now_price_cents', 'dutch_start_cents', 'dutch_floor_cents',
            'dutch_step_cents', 'dutch_interval_s', 'duration_days',
        ];

        $meta = $lot->draft_meta ?? [];

        foreach ($metaKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $meta[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }

        $lot->fill($validated);
        $lot->draft_meta = $meta;
        $lot->save();

        return response()->json($this->payload($lot->fresh()));
    }

    /**
     * Publiceren. Hier - en niet eerder - is een account nodig (hfst. 13.4).
     */
    public function publish(Request $request, Lot $lot, PublishLot $publishLot): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => __('funnel.errors.login_required'),
                'login_url' => route('login'),
            ], 401);
        }

        $published = $publishLot->handle($lot, $user);

        return response()->json([
            'published' => true,
            'url' => route('lots.show', $published),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Lot $lot): array
    {
        return $this->draftPayload->for($lot, auth()->check());
    }

    /**
     * Een kavelnummer dat de gebruiker ziet - nooit het autonummer (hfst. 5).
     */
    private function freshPublicId(): string
    {
        do {
            $publicId = (string) random_int(1000, 99999);
        } while (Lot::where('public_id', $publicId)->exists());

        return $publicId;
    }
}
