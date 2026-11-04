<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Distribution\ShareVisual;
use App\Models\Lot;
use App\Models\LotDistribution;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Het deelbeeld van een kavel: de Open Graph-afbeelding en de drie
 * formaten voor de deelkit. Alleen voor kavels die publiek zijn.
 */
final class ShareImageController extends Controller
{
    public function __invoke(Request $request, ShareVisual $visuals, Lot $lot, string $format): BinaryFileResponse
    {
        if (! $lot->status->isPublic() || ! in_array($format, ShareVisual::FORMATS, true)) {
            throw new NotFoundHttpException;
        }

        $moment = (string) $request->query('moment', LotDistribution::KIT);
        $moment = in_array($moment, LotDistribution::MOMENTS, true) ? $moment : LotDistribution::KIT;

        $path = $visuals->render($lot, $format, $moment);

        return response()->file($visuals->absolutePath($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=900',
            'Content-Disposition' => $request->boolean('download') ? 'attachment; filename="aucvy-'.$lot->public_id.'-'.$format.'.jpg"' : 'inline',
        ]);
    }
}
