<?php

declare(strict_types=1);

namespace App\Reviews;

/**
 * Waarom een review tegengehouden, gemeld of verwijderd wordt. Dezelfde
 * lijst voor de automatische controle, de meldknop en het besluit van
 * beheer, zodat de redenen overal hetzelfde heten.
 */
enum ReviewReason: string
{
    case Offensive = 'offensive';
    case Discrimination = 'discrimination';
    case Political = 'political';
    case PersonalData = 'personal_data';
    case NotATransaction = 'not_a_transaction';
    case Other = 'other';

    public function label(): string
    {
        return __('reviews.reasons.'.$this->value);
    }

    /**
     * De redenen die een bezoeker bij een melding kan kiezen.
     *
     * @return list<self>
     */
    public static function reportable(): array
    {
        return self::cases();
    }
}
