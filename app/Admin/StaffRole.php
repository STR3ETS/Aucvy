<?php

declare(strict_types=1);

namespace App\Admin;

use App\Models\User;

/**
 * De vijf rollen in /admin (briefing nieuwe functies, Rechten) en wat elke
 * rol per onderdeel mag: volledig, alleen lezen, of niets. Een beheerder
 * zonder rol telt als eigenaar, zodat bestaande accounts blijven werken.
 */
enum StaffRole: string
{
    case Owner = 'owner';
    case Finance = 'finance';
    case Support = 'support';
    case Moderator = 'moderator';
    case Marketing = 'marketing';

    public const string FULL = 'full';

    public const string READ = 'read';

    /** @var list<string> De onderdelen van het control center. */
    public const AREAS = ['money', 'work', 'finance', 'transactions', 'lots', 'customers', 'service', 'conversations', 'reports', 'mail', 'system'];

    public static function of(User $user): ?self
    {
        if (! $user->is_staff) {
            return null;
        }

        return self::tryFrom((string) ($user->getAttributes()['staff_role'] ?? '')) ?? self::Owner;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Eigenaar',
            self::Finance => 'Financiën',
            self::Support => 'Klantenservice',
            self::Moderator => 'Moderator',
            self::Marketing => 'Marketing',
        };
    }

    /**
     * De rechtenmatrix uit de briefing.
     *
     * @return 'full'|'read'|null
     */
    public function access(string $area): ?string
    {
        if ($this === self::Owner) {
            return self::FULL;
        }

        return match ($area) {
            'money' => in_array($this, [self::Finance], true) ? self::FULL : null,
            'work' => self::FULL,
            'finance' => $this === self::Finance ? self::FULL : null,
            'transactions' => match ($this) {
                self::Finance => self::FULL, self::Support => self::READ, default => null
            },
            'lots' => match ($this) {
                self::Moderator => self::FULL, default => self::READ
            },
            'customers' => match ($this) {
                self::Support => self::FULL, default => self::READ
            },
            'service' => match ($this) {
                self::Support => self::FULL, self::Marketing => null, default => self::READ
            },
            'conversations' => match ($this) {
                self::Support, self::Moderator => self::FULL, default => null
            },
            'reports' => self::FULL,
            'mail' => $this === self::Marketing ? self::FULL : null,
            'system' => null,
            default => null,
        };
    }

    public function can(string $area, bool $write = false): bool
    {
        $access = $this->access($area);

        return $write ? $access === self::FULL : $access !== null;
    }

    /** Marketing ziet de klantkaart zonder notities. */
    public function seesCustomerNotes(): bool
    {
        return $this !== self::Marketing;
    }
}
