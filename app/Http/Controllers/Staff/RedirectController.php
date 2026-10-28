<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Support\Audit\Audit;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Doorverwijzingen beheren (masterbriefing 7.2): oud pad, nieuw adres,
 * 301 of 302, met een teller zodat je ziet welke nog gebruikt worden.
 */
final class RedirectController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.redirects', [
            'redirects' => Redirect::query()
                ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($search) => $search
                    ->where('from_path', 'like', "%{$term}%")
                    ->orWhere('to_url', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_path' => ['required', 'string', 'max:255', 'regex:#^/#', $this->notAnExistingRoute()],
            'to_url' => ['required', 'string', 'max:2000', 'regex:#^(/|https?://)#'],
            'status' => ['required', Rule::in([301, 302])],
        ], [
            'from_path.regex' => 'Het oude pad begint met een slash, bijvoorbeeld /oude-pagina.',
            'to_url.regex' => 'Het nieuwe adres is een pad (/veilingen) of een volledige https-URL.',
        ]);

        if (Redirect::normalise($validated['from_path']) === Redirect::normalise($validated['to_url'])) {
            return back()->withInput()->withErrors(['to_url' => 'Een pad dat naar zichzelf verwijst is een lus.']);
        }

        $redirect = Redirect::updateOrCreate(
            ['from_path' => Redirect::normalise($validated['from_path'])],
            ['to_url' => $validated['to_url'], 'status' => (int) $validated['status']],
        );

        Audit::record('redirect.saved', $redirect, $request->user(), after: $redirect->only(['from_path', 'to_url', 'status']), ip: $request->ip());

        return redirect()->route('admin.redirects')->with('status', "Doorverwijzing van {$redirect->from_path} opgeslagen.");
    }

    public function destroy(Request $request, Redirect $redirect): RedirectResponse
    {
        Audit::record('redirect.deleted', $redirect, $request->user(), before: $redirect->only(['from_path', 'to_url', 'status']), ip: $request->ip());

        $redirect->delete();

        return redirect()->route('admin.redirects')->with('status', 'Doorverwijzing verwijderd.');
    }

    /**
     * Een pad met een eigen route wint altijd van een doorverwijzing; zo'n
     * regel zou nooit werken en alleen verwarren.
     */
    private function notAnExistingRoute(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $path = Redirect::normalise((string) $value);

            foreach (app('router')->getRoutes()->get('GET') as $route) {
                if ($route->getName() === 'fallback') {
                    continue;
                }

                if ($route->matches(Request::create($path, 'GET'), false)) {
                    $fail("Op {$path} staat al een pagina; een doorverwijzing zou nooit gebruikt worden.");

                    return;
                }
            }
        };
    }
}
