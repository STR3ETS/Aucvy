<?php

declare(strict_types=1);

namespace App\Distribution;

/**
 * De kanalen waarop een kavel gedeeld of geplaatst wordt. De deelkit werkt
 * voor elk kanaal zonder koppeling; een directe koppeling komt per kanaal
 * daarna (briefing nieuwe functies).
 */
enum Channel: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Story = 'story';
    case LinkedIn = 'linkedin';
    case Marktplaats = 'marktplaats';
    case WhatsApp = 'whatsapp';
    case Mail = 'mail';
    case Link = 'link';
    case System = 'system';

    /** De kanalen in de deelkit van de verkoper, in deze volgorde. */
    public const KIT = [self::Facebook, self::Instagram, self::LinkedIn, self::Marktplaats, self::WhatsApp, self::Mail];

    /** De kanalen waarmee een koper deelt. */
    public const BUYER = [self::WhatsApp, self::Mail, self::Link, self::System];

    /** Kanalen met een echte koppeling (fase 2); Marktplaats vraagt partnerschap (fase 3). */
    public const CONNECTABLE = [self::Facebook, self::Instagram, self::LinkedIn, self::Marktplaats];

    public function label(): string
    {
        return __('distribution.channels.'.$this->value);
    }

    /**
     * Het beeldformaat dat bij het kanaal past: vierkant, staand of liggend.
     */
    public function format(): string
    {
        return match ($this) {
            self::Instagram => ShareVisual::SQUARE,
            self::Story => ShareVisual::PORTRAIT,
            default => ShareVisual::LANDSCAPE,
        };
    }

    public function maxLength(): int
    {
        return match ($this) {
            self::Instagram, self::Story => 2200,
            self::LinkedIn => 3000,
            self::Marktplaats, self::Mail => 2000,
            self::WhatsApp => 1000,
            default => 5000,
        };
    }

    /**
     * Het kanaal waarvan de koppeling geldt: een story gaat via Instagram.
     */
    public function connection(): self
    {
        return $this === self::Story ? self::Instagram : $this;
    }

    /**
     * Zelf plaatsen: de link naar het deelvenster van het kanaal, met de
     * tekst waar dat kan. Null als er geen deelvenster is (Instagram wil een
     * app; de tekst en het beeld kopieer je).
     */
    public function openUrl(string $link, string $text): ?string
    {
        return match ($this) {
            self::Facebook => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($link).'&quote='.rawurlencode($text),
            self::LinkedIn => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($link),
            self::WhatsApp => 'https://wa.me/?text='.rawurlencode($text),
            self::Mail => 'mailto:?subject='.rawurlencode(mb_substr(strtok($text, "\n") ?: 'Kavel op Aucvy', 0, 120)).'&body='.rawurlencode($text),
            self::Marktplaats => 'https://www.marktplaats.nl/',
            default => null,
        };
    }
}
