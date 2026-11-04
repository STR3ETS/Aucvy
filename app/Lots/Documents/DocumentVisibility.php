<?php

declare(strict_types=1);

namespace App\Lots\Documents;

use App\Enums\DocumentKind;

/**
 * De drie niveaus van een document (briefing nieuwe functies) en het
 * standaardniveau per soort.
 */
final class DocumentVisibility
{
    /** Iedereen op de kavelpagina. */
    public const string PUBLIC = 'public';

    /** Winnaar, verkoper en medewerkers, zodra de veiling gesloten is. */
    public const string AFTER_SALE = 'after_sale';

    /** Verkoper, Lex en medewerkers. */
    public const string PRIVATE = 'private';

    /** @var list<string> */
    public const ALL = [self::PUBLIC, self::AFTER_SALE, self::PRIVATE];

    public static function defaultFor(DocumentKind $kind): string
    {
        return match ($kind) {
            DocumentKind::Invoice, DocumentKind::Ownership => self::PRIVATE,
            DocumentKind::Warranty, DocumentKind::Nameplate => self::AFTER_SALE,
            default => self::PUBLIC,
        };
    }

    public static function label(string $visibility): string
    {
        return __('documents.visibility.'.$visibility);
    }
}
