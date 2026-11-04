<?php

declare(strict_types=1);

namespace App\Lots\Documents;

use App\Lots\Dossier\Dossier;
use App\Lots\Interest\Interest;
use App\Models\LotActivity;
use App\Models\LotDocument;
use App\Models\LotSignal;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\URL;

/**
 * Wie een document mag openen, bepaald op de server, nooit alleen in de
 * browser. Het bestand zelf blijft privé op de opslag; wie mag kijken
 * krijgt een getekende URL van vijf minuten.
 */
final class DocumentAccess
{
    public const int SIGNED_MINUTES = 5;

    public function canView(?User $user, LotDocument $document): bool
    {
        $lot = $document->lot;

        if ($user !== null && ($user->is_staff || $lot->isManagedBy($user))) {
            return true;
        }

        return match ($document->visibility) {
            DocumentVisibility::PUBLIC => $lot->status->isPublic() || $lot->status->isFinished(),
            DocumentVisibility::AFTER_SALE => $user !== null && $lot->order !== null && $lot->order->buyer_id === $user->id,
            default => false,
        };
    }

    /**
     * Waarom de lezer het niet mag zien, voor de slotkaart.
     *
     * @return 'after_sale'|'private'|'login'|null
     */
    public function lockReason(?User $user, LotDocument $document): ?string
    {
        if ($this->canView($user, $document)) {
            return null;
        }

        return match ($document->visibility) {
            DocumentVisibility::AFTER_SALE => 'after_sale',
            DocumentVisibility::PRIVATE => 'private',
            default => 'login',
        };
    }

    public function signedFileUrl(LotDocument $document): string
    {
        return URL::temporarySignedRoute('documents.file', now()->addMinutes(self::SIGNED_MINUTES), ['document' => $document->id]);
    }

    /**
     * Bekeken: teller op het document, een regel in het dossier, en bij een
     * medewerker op een privé document ook het auditlog.
     */
    public function recordView(?User $user, LotDocument $document, ?string $ip = null): void
    {
        $document->increment('view_count');
        $document->forceFill(['last_viewed_at' => now()])->save();

        Interest::record($document->lot, LotSignal::DOCUMENT, $user !== null ? 'u'.$user->id : substr(hash('sha256', (string) $ip), 0, 32), $user);

        $isStaff = $user !== null && $user->is_staff && ! $document->lot->isManagedBy($user);

        if ($isStaff && $document->visibility === DocumentVisibility::PRIVATE) {
            Audit::record('document.viewed', $document, $user, after: ['kind' => $document->kind->value, 'lot_id' => $document->lot_id], ip: $ip);
        }

        Dossier::record(
            lot: $document->lot_id,
            source: LotActivity::SOURCE_DOCUMENT,
            type: 'viewed',
            visibility: LotActivity::SELLER,
            payload: ['kind' => $document->kind->label(), 'document_id' => $document->id, 'by' => $isStaff ? 'staff' : ($user === null ? 'visitor' : 'user')],
            actor: $user,
        );
    }
}
