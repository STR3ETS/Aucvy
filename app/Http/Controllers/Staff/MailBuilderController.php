<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Mail\Automations\AutomationCatalog;
use App\Mail\MailCatalog;
use App\Mail\MailFields;
use App\Mail\MailRenderer;
use App\Mail\TemplatedMail;
use App\Models\AutomationRun;
use App\Models\Category;
use App\Models\MailAutomation;
use App\Models\MailCampaign;
use App\Models\MailDelivery;
use App\Models\MailTemplate;
use App\Models\MailTemplateVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De Mail Builder (/admin/mail): de 46 mails met versies, dezelfde
 * blokken voor losse mails en reeksen, preview voor desktop en mobiel,
 * een testmail naar jezelf, campagnes en de acht reeksen.
 */
final class MailBuilderController extends Controller
{
    public function __construct(private readonly MailRenderer $renderer) {}

    public function index(): View
    {
        MailCatalog::seed();
        AutomationCatalog::seed();

        $templates = MailTemplate::query()->with('currentVersion')->orderBy('number')->get();
        $deliveries = MailDelivery::query()->whereNotNull('template_key')->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('template_key, count(*) as sent, sum(case when clicked_at is not null then 1 else 0 end) as clicked')
            ->groupBy('template_key')->get()->keyBy('template_key');

        return view('admin.mail.index', [
            'groups' => $templates->groupBy('group'),
            'stats' => $deliveries,
            'automations' => MailAutomation::query()->withCount(['runs', 'runs as running_count' => fn ($query) => $query->where('status', AutomationRun::RUNNING)])->orderBy('number')->get(),
            'campaigns' => MailCampaign::query()->with('version.template')->latest('id')->limit(20)->get(),
            'campaignTemplates' => $templates->where('stream', MailTemplate::CAMPAIGN)->values(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'streams' => MailTemplate::STREAMS,
        ]);
    }

    public function edit(MailTemplate $template): View
    {
        $template->load(['currentVersion', 'versions.author']);

        return view('admin.mail.edit', [
            'template' => $template,
            'version' => $template->currentVersion,
            'blockTypes' => MailRenderer::BLOCKS,
            'sample' => MailFields::sample(),
        ]);
    }

    /**
     * Opslaan maakt altijd een nieuwe versie; een verstuurde mail houdt de
     * versie waarmee hij is verstuurd.
     */
    public function update(Request $request, MailTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'preheader' => ['nullable', 'string', 'max:200'],
            'sender' => ['required', Rule::in([MailCatalog::IVY, MailCatalog::AUCVY])],
            'blocks' => ['required', 'json'],
        ]);

        $blocks = $this->normaliseBlocks((array) json_decode($validated['blocks'], true));

        if ($blocks === []) {
            return back()->withErrors(['blocks' => 'Een mail heeft minstens één blok nodig.'])->withInput();
        }

        $version = MailTemplateVersion::create([
            'template_id' => $template->id,
            'version' => ((int) $template->versions()->max('version')) + 1,
            'subject' => $validated['subject'],
            'preheader' => $validated['preheader'] ?? null,
            'sender' => $validated['sender'],
            'blocks' => $blocks,
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        $template->forceFill(['current_version_id' => $version->id])->save();

        return redirect()->route('admin.mail.edit', $template)->with('status', "Versie {$version->version} opgeslagen en actief.");
    }

    public function restore(Request $request, MailTemplate $template, MailTemplateVersion $version): RedirectResponse
    {
        abort_unless($version->template_id === $template->id, 404);

        $template->forceFill(['current_version_id' => $version->id])->save();

        return redirect()->route('admin.mail.edit', $template)->with('status', "Versie {$version->version} is weer actief.");
    }

    /**
     * De preview met voorbeelddata, als los document voor het iframe.
     */
    public function preview(Request $request, MailTemplate $template): Response
    {
        $version = $template->currentVersion;

        if ($request->filled('blocks')) {
            $version = new MailTemplateVersion([
                'subject' => (string) $request->input('subject', $version?->subject),
                'preheader' => (string) $request->input('preheader', $version?->preheader),
                'sender' => (string) $request->input('sender', $version?->sender ?? MailCatalog::AUCVY),
                'blocks' => $this->normaliseBlocks((array) json_decode((string) $request->input('blocks'), true)),
            ]);
            $version->setRelation('template', $template);
        }

        abort_if($version === null, 404);

        $rendered = $this->renderer->render($version, MailFields::sample(), null, $template->isMarketing() ? '#afmelden' : null);

        return response($rendered->html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test(Request $request, MailTemplate $template): RedirectResponse
    {
        $version = $template->currentVersion;
        abort_if($version === null, 404);

        $rendered = $this->renderer->render($version, MailFields::sample(), null, $template->isMarketing() ? '#afmelden' : null);

        Mail::to($request->user()->email, $request->user()->name)->send(new TemplatedMail($rendered, MailTemplate::SERVICE, ['template_key' => $template->key, 'template_version_id' => $version->id, 'stream' => 'test']));

        return back()->with('status', 'Testmail verstuurd naar '.$request->user()->email.'.');
    }

    public function toggleAutomation(MailAutomation $automation): RedirectResponse
    {
        abort_if($automation->status === MailAutomation::BUILT_IN, 404);

        $automation->forceFill(['status' => $automation->status === MailAutomation::ACTIVE ? MailAutomation::PAUSED : MailAutomation::ACTIVE])->save();

        return redirect()->route('admin.mail')->with('status', $automation->name.' staat op '.$automation->statusLabel().'.');
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_id' => ['required', Rule::exists('mail_templates', 'id')->where('stream', MailTemplate::CAMPAIGN)],
            'audience' => ['required', Rule::in(MailCampaign::AUDIENCES)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'scheduled_at' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'url', 'max:500'],
        ]);

        $template = MailTemplate::query()->findOrFail($validated['template_id']);

        MailCampaign::create([
            'template_version_id' => $template->current_version_id,
            'name' => $validated['name'],
            'segment' => array_filter([
                'audience' => $validated['audience'],
                'category_id' => $validated['category_id'] ?? null,
                'label' => isset($validated['category_id']) ? Category::query()->find($validated['category_id'])?->name : null,
                'title' => $validated['title'] ?? null,
                'intro' => $validated['intro'] ?? null,
                'link' => $validated['link'] ?? null,
            ]),
            'scheduled_at' => $validated['scheduled_at'] ?? now(),
            'status' => MailCampaign::SCHEDULED,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.mail')->with('status', 'Campagne ingepland. Toestemming, suppressielijst en weekmaximum worden bij het versturen opnieuw getoetst.');
    }

    public function cancelCampaign(MailCampaign $campaign): RedirectResponse
    {
        if ($campaign->status === MailCampaign::SCHEDULED) {
            $campaign->forceFill(['status' => MailCampaign::CANCELLED])->save();
        }

        return redirect()->route('admin.mail')->with('status', 'Campagne geannuleerd.');
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    private function normaliseBlocks(array $blocks): array
    {
        $clean = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, MailRenderer::BLOCKS, true)) {
                continue;
            }

            $row = ['type' => $block['type']];

            foreach (['text', 'title', 'label', 'url', 'value', 'note', 'alt', 'source'] as $field) {
                if (isset($block[$field]) && is_string($block[$field])) {
                    $row[$field] = trim(strip_tags($block[$field]));
                }
            }

            if (isset($block['secondary'])) {
                $row['secondary'] = (bool) $block['secondary'];
            }

            if (isset($block['limit'])) {
                $row['limit'] = max(1, min(6, (int) $block['limit']));
            }

            if (isset($block['items']) && is_array($block['items'])) {
                $row['items'] = array_values(array_map(fn ($item) => is_array($item)
                    ? ['label' => trim(strip_tags((string) ($item['label'] ?? ''))), 'value' => trim(strip_tags((string) ($item['value'] ?? '')))]
                    : trim(strip_tags((string) $item)), $block['items']));
            }

            $clean[] = $row;
        }

        return $clean;
    }
}
