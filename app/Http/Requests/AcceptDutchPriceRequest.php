<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptDutchPriceRequest extends FormRequest
{
    use HasIdempotencyKey;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             | De prijs die de koper op zijn scherm zág. De server rekent zelf
             | opnieuw en dát bedrag geldt; dit veld dient om te bepalen of het
             | scherm nog actueel genoeg was (hfst. 7).
             */
            'seen_price_cents' => ['required', 'integer', 'min:1'],
        ];
    }

    public function seenPriceCents(): int
    {
        return $this->integer('seen_price_cents');
    }
}
