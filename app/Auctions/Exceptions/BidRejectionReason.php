<?php

declare(strict_types=1);

namespace App\Auctions\Exceptions;

use App\Enums\Concerns\HasLabel;

/**
 * Elk faalgeval krijgt een eigen reden, want de UI toont ze los.
 *
 * Een generieke "er ging iets mis" op het biedpaneel is hier niet goed genoeg:
 * "je bent nog niet geverifieerd" heeft een knop naar de verificatiestap,
 * "je bod is te laag" heeft een bedrag, en "de veiling is gesloten" heeft geen
 * van beide (briefing hfst. 6 en 13.3).
 */
enum BidRejectionReason: string
{
    use HasLabel;

    /** De veiling loopt niet (meer): concept, ingepland, of al gesloten. */
    case AuctionNotOpen = 'auction_not_open';

    /** De eindtijd is verstreken terwijl het bod onderweg was. */
    case AuctionEnded = 'auction_ended';

    /** Bieden op je eigen kavel. */
    case OwnLot = 'own_lot';

    /** Een account van hetzelfde bedrijf als de verkoper - shill bidding. */
    case SameCompany = 'same_company';

    /** Verificatie is nog niet rond. */
    case NotVerified = 'not_verified';

    /** Het account is door beheer geblokkeerd. */
    case Blocked = 'blocked';

    /** Boven de drempel is een borg nodig vóór het eerste bod. */
    case DepositRequired = 'deposit_required';

    /** Onder het minimum van de biedstaffel. */
    case TooLow = 'too_low';

    /** Je bent al de hoogste bieder. */
    case AlreadyHighest = 'already_highest';

    /** Een maximum verlagen kan niet - dat zou onder een bestaand bod liggen. */
    case AutoBidNotIncreasing = 'auto_bid_not_increasing';

    /** De afslagprijs is verder gezakt dan wat de client op het scherm had. */
    case PriceMoved = 'price_moved';

    /** Koop-direct is vervallen doordat er boven de vaste prijs geboden is. */
    case BuyNowUnavailable = 'buy_now_unavailable';

    /** Deze handeling hoort niet bij de verkoopvorm van deze kavel. */
    case SaleTypeMismatch = 'sale_type_mismatch';

    /**
     * De HTTP-status die hierbij hoort. 409 waar de stand van de veiling
     * verschoven is, 422 waar de invoer niet klopt, 403 waar het mag noch kan.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::AuctionNotOpen, self::AuctionEnded,
            self::PriceMoved, self::BuyNowUnavailable => 409,
            self::OwnLot, self::SameCompany, self::NotVerified, self::Blocked, self::DepositRequired => 403,
            self::TooLow, self::AlreadyHighest,
            self::AutoBidNotIncreasing, self::SaleTypeMismatch => 422,
        };
    }

    /**
     * Waar de UI de gebruiker naartoe stuurt om het op te lossen. Null betekent:
     * hier valt niets te doen behalve een ander bedrag proberen.
     */
    public function resolutionRoute(): ?string
    {
        return match ($this) {
            self::NotVerified => 'account.verification',
            self::DepositRequired => 'account.deposit',
            default => null,
        };
    }
}
