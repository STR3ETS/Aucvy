<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validatie hoort hier, niet in de controller (hfst. 3).
 */
final class PlaceBidRequest extends FormRequest
{
    use HasIdempotencyKey;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Altijd centen, altijd een int. Een bod met een komma erin hoort
            // hier te stranden en niet bij de biedmotor aan te komen.
            'amount_cents' => ['required', 'integer', 'min:1'],
        ];
    }

    public function amountCents(): int
    {
        return $this->integer('amount_cents');
    }
}
