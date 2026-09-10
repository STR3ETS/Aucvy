<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class BuyNowRequest extends FormRequest
{
    use HasIdempotencyKey;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Zonder expliciete bevestiging wordt er alleen gerekend, niet
            // gekocht. Zie BuyNowController.
            'confirm' => ['sometimes', 'boolean'],
            'transport_cents' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->boolean('confirm');
    }

    public function transportCents(): int
    {
        return $this->integer('transport_cents');
    }
}
