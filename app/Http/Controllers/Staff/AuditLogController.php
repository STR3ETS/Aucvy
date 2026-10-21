<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het auditlog: elke kritieke overgang, met filters op actie, wie en wat.
 * Alleen lezen; de rijen zelf zijn bewijs en veranderen nooit.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'actor' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $entries = AuditLog::query()
            ->with('actor')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['subject'] ?? null, fn ($query, $subject) => $query->where('subject_type', $subject))
            ->when($filters['actor'] ?? null, fn ($query, $actor) => $query->whereHas('actor', fn ($users) => $users
                ->where('name', 'like', "%{$actor}%")
                ->orWhere('email', 'like', "%{$actor}%")))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<', now()->parse($to)->addDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.auditlog', [
            'entries' => $entries,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjects' => AuditLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'filters' => $filters,
        ]);
    }
}
