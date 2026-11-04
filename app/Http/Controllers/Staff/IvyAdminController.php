<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Ivy\Chat\IvyFlags;
use App\Ivy\Chat\KnowledgeBase;
use App\Models\AiRun;
use App\Models\IvyHandoff;
use App\Models\IvyLead;
use App\Models\KbArticle;
use App\Models\KbGap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ivy in beheer: de noodremmen per onderdeel, de kennisbank, onbekende
 * vragen, leads en overdrachten, en de kosten van vandaag.
 */
final class IvyAdminController extends Controller
{
    public function index(): View
    {
        KnowledgeBase::seed();

        return view('admin.ivy', [
            'flags' => IvyFlags::all(),
            'articles' => KbArticle::query()->orderBy('slug')->get(),
            'gaps' => KbGap::query()->whereNull('resolved_article_id')->orderByDesc('count')->limit(30)->get(),
            'leads' => IvyLead::query()->latest('id')->limit(20)->get(),
            'handoffs' => IvyHandoff::query()->with('conversation.user')->where('status', 'open')->latest('created_at')->limit(20)->get(),
            'today' => ['runs' => AiRun::query()->where('task', 'ivy_chat')->where('created_at', '>=', now()->startOfDay())->count(), 'cost_cents' => (int) AiRun::query()->where('task', 'ivy_chat')->where('created_at', '>=', now()->startOfDay())->sum('cost_cents')],
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate(['flag' => ['required', Rule::in(IvyFlags::KEYS)], 'value' => ['required', 'boolean']]);

        IvyFlags::set($validated['flag'], (bool) $validated['value']);

        return redirect()->route('admin.ivy')->with('status', $validated['flag'].' staat '.((bool) $validated['value'] ? 'aan' : 'uit').'.');
    }

    public function saveArticle(Request $request, ?KbArticle $article = null): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('kb_articles', 'slug')->ignore($article?->id)],
            'title' => ['required', 'string', 'max:160'],
            'audience' => ['required', Rule::in(['all', 'visitor', 'buyer', 'seller'])],
            'body' => ['required', 'string', 'max:4000'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in([KbArticle::PUBLISHED, KbArticle::DRAFT])],
        ]);

        $article ??= new KbArticle;
        $article->fill($validated + ['updated_by' => $request->user()->id, 'current_version' => ($article->current_version ?? 0) + 1])->save();

        if ($request->filled('gap_id')) {
            KbGap::query()->whereKey($request->input('gap_id'))->update(['resolved_article_id' => $article->id]);
        }

        return redirect()->route('admin.ivy')->with('status', 'Artikel "'.$article->title.'" opgeslagen als versie '.$article->current_version.'.');
    }

    public function closeHandoff(IvyHandoff $handoff, Request $request): RedirectResponse
    {
        $handoff->forceFill(['status' => 'closed', 'closed_at' => now(), 'assigned_to' => $request->user()->id])->save();
        $handoff->conversation?->forceFill(['status' => 'closed'])->save();

        return redirect()->route('admin.ivy')->with('status', 'Overdracht afgerond.');
    }
}
