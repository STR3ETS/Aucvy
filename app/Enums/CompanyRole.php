<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Rol binnen een bedrijfsaccount (briefing sectie 2: organisatierollen).
 * De eigenaar is er altijd minstens één; de rest is wat het bedrijf zelf
 * uitdeelt.
 */
enum CompanyRole: string
{
    use HasLabel;

    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Finance = 'finance';

    /**
     * Teamleden uitnodigen, rollen wijzigen, verwijderen.
     */
    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Kavels van collega's bewerken, pauzeren, dupliceren en foto's beheren.
     */
    public function canEditLots(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor], true);
    }

    /**
     * Financiën en uitbetalingen van het hele bedrijf zien.
     */
    public function canSeeFinance(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance], true);
    }

    public function description(): string
    {
        return __('enums.company_role_description.'.$this->value);
    }
}
