<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\Import\LotImporter;
use App\Lots\Import\SpreadsheetReader;
use App\Models\LotImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Import van kavels (briefing sectie 4): csv of xlsx uploaden, kolommen
 * koppelen, voorbeeld met fouten, dan importeren als concepten. Een API
 * vraagt tokens (Sanctum) en is een aparte stap.
 */
final class LotImportController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.import', [
            'imports' => LotImport::query()->where('user_id', $request->user()->id)->latest('id')->limit(10)->get(),
            'fields' => LotImporter::FIELDS,
        ]);
    }

    public function store(Request $request, SpreadsheetReader $reader): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:4096'],
        ]);

        $file = $request->file('file');
        // De extensie blijft staan: daar kiest de lezer csv of xlsx op.
        $path = $file->storeAs('imports/'.$request->user()->id, Str::random(40).'.'.strtolower((string) $file->getClientOriginalExtension()), 'local');
        try {
            $csv = $reader->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        if ($csv['headers'] === [] || $csv['rows'] === []) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'Het bestand heeft geen kopregel met minstens één rij eronder.']);
        }

        $import = LotImport::create([
            'user_id' => $request->user()->id,
            'filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'path' => Storage::disk('local')->path($path),
            'headers' => $csv['headers'],
            'rows_total' => count($csv['rows']),
        ]);

        return redirect()->route('dashboard.import.show', $import);
    }

    public function show(Request $request, LotImport $import, LotImporter $importer): View
    {
        $this->own($request, $import);

        $mapping = $import->mapping ?? $importer->guessMapping($import->headers);
        $readError = null;

        try {
            $preview = $import->isDone() ? collect() : $importer->preview($import, $mapping, 5);
        } catch (RuntimeException $exception) {
            $preview = collect();
            $readError = $exception->getMessage();
        }

        return view('dashboard.import-show', [
            'import' => $import,
            'fields' => LotImporter::FIELDS,
            'mapping' => $mapping,
            'preview' => $preview,
            'readError' => $readError,
        ]);
    }

    public function run(Request $request, LotImport $import, LotImporter $importer): RedirectResponse
    {
        $this->own($request, $import);

        if ($import->isDone()) {
            return redirect()->route('dashboard.import.show', $import);
        }

        $validated = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.title' => ['required', 'integer', 'min:0'],
            'mapping.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $mapping = array_map(fn (mixed $index): ?int => $index === null || $index === '' ? null : (int) $index, $validated['mapping']);

        $importer->run($import, $mapping, $request->user());

        return redirect()->route('dashboard.import.show', $import)->with('status', $import->rows_created === 1 ? 'Eén kavel staat als concept klaar.' : $import->rows_created.' kavels staan als concept klaar.');
    }

    private function own(Request $request, LotImport $import): void
    {
        abort_unless($import->user_id === $request->user()->id, 404);
    }
}
