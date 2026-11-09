<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\CategoryCounts;
use App\Lots\FeaturedLots;
use App\Models\Category;
use App\Support\Experiments\HomeExperiment;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HomeController extends Controller
{
    /**
     * "Live op Aucvy - de handel van dit moment."
     *
     * Vier uitgelichte kavels, aflopend op eindtijd, met minimaal één per
     * verkoopvorm (zie FeaturedLots). De eindtijden lopen client-side door
     * (hfst. 13.1).
     *
     * Bewust géén websocket op de home: dat zijn honderd verbindingen voor
     * niets. De kaarten kloppen weer bij de volgende paginaweergave.
     *
     * De A/B-test bepaalt welk ontwerp rendert: 'home' (het huidige) of
     * 'home-v4' (preview v4). Beide krijgen dezelfde data; alleen de
     * presentatie verschilt, want dat is wat de test vergelijkt.
     */
    public function __invoke(Request $request, HomeExperiment $experiment, CategoryCounts $categoryCounts, FeaturedLots $featured): View
    {
        $variant = $experiment->resolve($request);
        $categories = Category::roots()->with('children')->get();

        return view($variant->view(), [
            'variant' => $variant,
            // In de vergelijkingspagina (iframe) blijft de wisselknop weg.
            'demo' => $request->boolean(HomeExperiment::DEMO),
            'featured' => $featured->get(),
            // Acht rijen plus hun subcategorieën. Cachen levert hier niets op en
            // zou een nieuwe categorie tien minuten onzichtbaar maken.
            'categories' => $categories,
            // Het aantal zichtbare kavels per hoofdcategorie, voor de kaarten.
            'categoryTotals' => $categoryCounts->perRoot($categories),
        ]);
    }
}
