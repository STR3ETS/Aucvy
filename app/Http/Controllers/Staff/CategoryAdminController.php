<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Categoriebeheer voor de backoffice: aanmaken, hernoemen en verwijderen.
 * Verwijderen kan alleen als er niets aan hangt - een categorie met kavels
 * of subcategorieën verdwijnt niet stilletjes.
 */
final class CategoryAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.categories', [
            'roots' => Category::query()->roots()
                ->with(['children' => fn ($query) => $query->withCount('lots')])
                ->withCount('lots')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $parent = isset($validated['parent_id'])
            ? Category::query()->find($validated['parent_id'])
            : null;

        // Subcategorieën van subcategorieën bestaan niet in de taxonomie.
        if ($parent !== null && ! $parent->isRoot()) {
            return back()->withErrors(['parent_id' => 'Alleen een hoofdcategorie kan subcategorieën hebben.']);
        }

        $slug = Str::slug(($parent !== null ? $parent->slug.' ' : '').$validated['name']);

        if (Category::query()->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'Er bestaat al een categorie met deze naam.']);
        }

        Category::create([
            'parent_id' => $parent?->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'position' => (int) Category::query()
                ->where('parent_id', $parent?->id)
                ->max('position') + 1,
            'specifications_schema' => null,
        ]);

        return back()->with('status', "Categorie \"{$validated['name']}\" aangemaakt.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        // De slug blijft staan: die zit in gedeelde URL's.
        $category->update(['name' => $validated['name']]);

        return back()->with('status', 'Categorie hernoemd.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->lots()->exists()) {
            return back()->withErrors(['category' => "\"{$category->name}\" heeft nog kavels en kan niet weg."]);
        }

        if ($category->children()->exists()) {
            return back()->withErrors(['category' => "\"{$category->name}\" heeft nog subcategorieën en kan niet weg."]);
        }

        $category->delete();

        return back()->with('status', "Categorie \"{$category->name}\" verwijderd.");
    }
}
