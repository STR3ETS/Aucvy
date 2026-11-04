<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Admin\StaffRole;
use App\Enums\OrderStatus;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Controller;
use App\Models\AdminAccessLog;
use App\Models\CustomerNote;
use App\Models\CustomerTask;
use App\Models\LotActivity;
use App\Models\MailDelivery;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Klanten en de klantkaart (control center): een CRM-lijst met filters en
 * zoeken, en per klant de tijdlijn, notities, taken, mailvoorkeuren en
 * accountacties. De klantkaart kopieert niets: de tijdlijn leest het
 * dossier, de mail, de tickets en de bestellingen. Elke geopende klantkaart
 * staat in admin_access_logs.
 */
final class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('soort', '');

        return view('admin.customers.index', [
            'filter' => $filter,
            'customers' => User::query()
                ->with('company')
                ->withCount(['lots', 'bids'])
                ->whereNotNull('email_verified_at')
                ->when($filter === 'zakelijk', fn ($query) => $query->whereNotNull('company_id'))
                ->when($filter === 'particulier', fn ($query) => $query->whereNull('company_id'))
                ->when($filter === 'nieuw', fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
                ->when($filter === 'beheer', fn ($query) => $query->where('is_staff', true))
                ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$term}%"))))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, User $customer): View
    {
        AdminAccessLog::record($request->user(), 'customer', $customer->id, null, $request->ip());

        $customer->load('company')->loadCount(['lots', 'bids']);
        $role = StaffRole::of($request->user());

        $orders = Order::query()->with('lot')->where(fn ($query) => $query->where('buyer_id', $customer->id)->orWhere('seller_id', $customer->id))->latest('id')->limit(10)->get();
        $tickets = SupportTicket::query()->where('user_id', $customer->id)->orWhere('email', $customer->email)->latest('id')->limit(10)->get();
        $mails = MailDelivery::query()->where('user_id', $customer->id)->latest('id')->limit(10)->get();
        $activity = LotActivity::query()->where(fn ($query) => $query->where('actor_id', $customer->id)->orWhere('user_id', $customer->id))->with('lot')->latest('occurred_at')->limit(20)->get();

        $timeline = collect()
            ->concat($orders->map(fn (Order $order) => ['at' => $order->created_at, 'kind' => 'order', 'text' => 'Bestelling '.$order->order_number.' · '.$order->lot?->title.' · '.$order->status->label(), 'url' => route('admin.orders.show', $order)]))
            ->concat($tickets->map(fn (SupportTicket $ticket) => ['at' => $ticket->created_at, 'kind' => 'ticket', 'text' => 'Ticket '.$ticket->number.' · '.$ticket->subject, 'url' => route('admin.service.show', $ticket)]))
            ->concat($mails->map(fn (MailDelivery $mail) => ['at' => $mail->created_at, 'kind' => 'mail', 'text' => 'Mail: '.$mail->subject.' · '.$mail->status, 'url' => null]))
            ->concat($activity->map(fn (LotActivity $row) => ['at' => $row->occurred_at, 'kind' => $row->source, 'text' => $row->label().($row->lot ? ' · '.$row->lot->title : ''), 'url' => $row->lot ? route('admin.lots.dossier', $row->lot) : null]))
            ->sortByDesc('at')->take(40)->values();

        return view('admin.customers.show', [
            'customer' => $customer,
            'role' => $role,
            'timeline' => $timeline,
            'notes' => $role?->seesCustomerNotes() ? CustomerNote::query()->with('author')->where('user_id', $customer->id)->latest('id')->get() : collect(),
            'tasks' => CustomerTask::query()->with('assignee')->where('user_id', $customer->id)->orderByRaw('done_at is not null, due_at')->get(),
            'bought' => (int) Order::query()->where('buyer_id', $customer->id)->whereIn('status', [OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed])->sum('total_cents'),
            'sold' => (int) Order::query()->where('seller_id', $customer->id)->whereIn('status', [OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed])->sum('hammer_cents'),
            'preferences' => collect(AccountSettingsController::PREFERENCES)->mapWithKeys(fn (string $key) => [$key => $customer->prefers($key)]),
            'accessLogs' => AdminAccessLog::query()->with('staff')->where('subject_type', 'customer')->where('subject_id', (string) $customer->id)->latest('opened_at')->limit(5)->get(),
        ]);
    }

    public function storeNote(Request $request, User $customer): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);

        CustomerNote::create(['user_id' => $customer->id, 'company_id' => $customer->company_id, 'body' => trim($validated['body']), 'created_by' => $request->user()->id, 'created_at' => now()]);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Notitie opgeslagen.');
    }

    public function storeTask(Request $request, User $customer): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        CustomerTask::create([
            'user_id' => $customer->id,
            'company_id' => $customer->company_id,
            'title' => trim($validated['title']),
            'due_at' => $validated['due_at'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Taak aangemaakt.');
    }

    public function completeTask(Request $request, User $customer, CustomerTask $task): RedirectResponse
    {
        abort_unless($task->user_id === $customer->id, 404);

        $task->forceFill(['done_at' => now(), 'done_by' => $request->user()->id])->save();

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Taak afgevinkt.');
    }
}
