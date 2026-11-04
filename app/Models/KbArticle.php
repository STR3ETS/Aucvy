<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * De kennisbank: feiten waar Ivy uit antwoordt, met bron en versie. De
 * bedragen komen bij het renderen uit PlatformFacts, nooit uit de tekst.
 */
#[Fillable(['slug', 'title', 'audience', 'body', 'source_url', 'status', 'current_version', 'updated_by'])]
class KbArticle extends Model
{
    public const string PUBLISHED = 'published';

    public const string DRAFT = 'draft';
}
