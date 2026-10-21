<?php

declare(strict_types=1);

namespace App\Compliance;

/**
 * De drie handelingen die de Permission Engine beslist (briefing sectie 7,
 * flow E): bieden, publiceren en uitbetaald worden. De UI verbergt alleen;
 * de server blokkeert.
 */
enum Permission: string
{
    case Bid = 'bid';
    case Publish = 'publish';
    case Payout = 'payout';
}
