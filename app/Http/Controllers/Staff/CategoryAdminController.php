<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /** @var array<string, string> Veldtypes die de wizard kent (stap 4). */
    public const TYPES = [
        'string' => 'Tekst',
        'integer' => 'Geheel getal',
        'decimal' => 'Getal met decimalen',
        'select' => 'Keuzelijst',
        'boolean' => 'Ja of nee',
    ];

    /**
     * Attributenschema per categorie (briefing sectie 7): de velden die de
     * wizard in stap 4 toont en die Ivy probeert in te vullen.
     */
    public function schema(Category $category): View
    {
        $own = $category->specifications_schema;

        return view('admin.categories.schema', [
            'category' => $category->load('parent'),
            'types' => self::TYPES,
            'fields' => collect($own ?? [])->map(fn (array $field): array => [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'] === 'bool' ? 'boolean' : (isset($field['options']) ? 'select' : $field['type']),
                'unit' => $field['unit'] ?? '',
                'options' => implode(', ', $field['options'] ?? []),
                'required' => (bool) ($field['required'] ?? false),
            ])->values()->all(),
            'inherited' => $own === null && ! $category->isRoot() ? $category->parent?->specifications_schema : null,
        ]);
    }

    public function updateSchema(Request $request, Category $category): RedirectResponse
    {
        if ($request->boolean('inherit') && ! $category->isRoot()) {
            $category->update(['specifications_schema' => null]);

            return redirect()->route('admin.categories.schema', $category)->with('status', 'Deze subcategorie gebruikt nu het schema van '.$category->parent->name.'.');
        }

        $validated = $request->validate([
            'fields' => ['nullable', 'array', 'max:40'],
            'fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,39}$/'],
            'fields.*.label' => ['required', 'string', 'max:60'],
            'fields.*.type' => ['required', Rule::in(array_keys(self::TYPES))],
            'fields.*.unit' => ['nullable', 'string', 'max:12'],
            'fields.*.options' => ['nullable', 'string', 'max:500', 'required_if:fields.*.type,select'],
            'fields.*.required' => ['nullable', 'boolean'],
        ], [
            'fields.*.key.regex' => 'Een sleutel bestaat uit kleine letters, cijfers en underscores en begint met een letter.',
            'fields.*.options.required_if' => 'Een keuzelijst heeft opties nodig, gescheiden door komma\'s.',
        ]);

        $fields = collect($validated['fields'] ?? []);

        if ($fields->pluck('key')->duplicates()->isNotEmpty()) {
            return back()->withInput()->withErrors(['fields' => 'Elke sleutel mag maar één keer voorkomen.']);
        }

        $schema = $fields->map(fn (array $field): array => array_filter([
            'key' => $field['key'],
            'label' => trim($field['label']),
            'type' => $field['type'] === 'select' ? 'string' : $field['type'],
            'unit' => isset($field['unit']) && trim($field['unit']) !== '' ? trim($field['unit']) : null,
            'options' => $field['type'] === 'select'
                ? collect(explode(',', (string) ($field['options'] ?? '')))->map(fn (string $option): string => trim($option))->filter()->unique()->values()->all()
                : null,
            'required' => (bool) ($field['required'] ?? false),
        ], fn (mixed $value): bool => $value !== null && $value !== false))->values()->all();

        $category->update(['specifications_schema' => $schema === [] ? null : $schema]);

        return redirect()->route('admin.categories.schema', $category)->with('status', count($schema).' '.(count($schema) === 1 ? 'veld' : 'velden').' opgeslagen voor '.$category->name.'.');
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
