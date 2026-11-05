<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\Auction;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| Organisatierollen (briefing sectie 2): eigenaar, beheerder, redacteur en
| financieel. Uitnodigen per mail, rollen wijzigen, en de poorten op kavels
| en financiën.
*/

beforeEach(function () {
    Queue::fake();
    $this->company = Company::factory()->verified()->create(['name' => 'Keukenhuis Utrecht']);
    $this->owner = User::factory()->verified()->create(['company_id' => $this->company->id, 'company_role' => CompanyRole::Owner, 'type' => 'business']);
});

function colleague(Company $company, CompanyRole $role): User
{
    return User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => $role, 'type' => 'business']);
}

function companyLot(User $seller): Lot
{
    $lot = Lot::factory()->for($seller, 'seller')->create(['status' => LotStatus::Live, 'company_id' => $seller->company_id]);
    Auction::factory()->for($lot)->create();

    return $lot;
}

it('laat de eigenaar een collega uitnodigen en stuurt de maillink', function () {
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('team.invite'), ['email' => 'Collega@Keukenhuis.nl', 'role' => 'editor'])
        ->assertRedirect(route('team.index'))
        ->assertSessionHasNoErrors();

    $invitation = CompanyInvitation::query()->sole();

    expect($invitation->email)->toBe('collega@keukenhuis.nl')
        ->and($invitation->role)->toBe(CompanyRole::Editor)
        ->and($invitation->isOpen())->toBeTrue();

    Notification::assertSentOnDemand(TeamInvitation::class, fn (TeamInvitation $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'collega@keukenhuis.nl' && $mail->invitation->is($invitation));

    $this->actingAs($this->owner)->get(route('team.index'))
        ->assertOk()
        ->assertSee('collega@keukenhuis.nl')
        ->assertSee('Open uitnodigingen');
});

it('neemt de uitnodiging aan met het juiste account en weigert een ander adres', function () {
    $invitation = CompanyInvitation::factory()->create(['company_id' => $this->company->id, 'invited_by' => $this->owner->id, 'email' => 'nieuw@keukenhuis.nl', 'role' => CompanyRole::Finance]);

    $stranger = User::factory()->create(['email' => 'iemand@anders.nl']);
    $this->actingAs($stranger)->get(route('team.invitation', $invitation))->assertOk()->assertSee('Deze uitnodiging is voor nieuw@keukenhuis.nl');
    $this->actingAs($stranger)->post(route('team.accept', $invitation))->assertSessionHasErrors('invitation');

    $invitee = User::factory()->create(['email' => 'nieuw@keukenhuis.nl']);
    $this->actingAs($invitee)->get(route('team.invitation', $invitation))->assertOk()->assertSee('Uitnodiging aannemen');
    $this->actingAs($invitee)->post(route('team.accept', $invitation))->assertRedirect(route('team.index'));

    $invitee->refresh();

    expect($invitee->company_id)->toBe($this->company->id)
        ->and($invitee->company_role)->toBe(CompanyRole::Finance)
        ->and($invitee->type->value)->toBe('business')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    // Een tweede keer kan niet.
    $this->actingAs($invitee)->post(route('team.accept', $invitation))->assertSessionHasErrors('invitation');
});

it('geeft redacteuren de kavels van het bedrijf en houdt ze weg bij financiën', function () {
    $editor = colleague($this->company, CompanyRole::Editor);
    $lot = companyLot($this->owner);

    $this->actingAs($editor)->get(route('dashboard.lots'))->assertOk()->assertSee($lot->title);
    $this->actingAs($editor)->get(route('dashboard.lots.edit', $lot))->assertOk();
    $this->actingAs($editor)
        ->patch(route('dashboard.lots.update', $lot), ['title' => 'Aangepast door redacteur', 'description' => $lot->description, 'condition' => $lot->condition->value, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    expect($lot->fresh()->title)->toBe('Aangepast door redacteur');

    $this->actingAs($editor)->get(route('dashboard.finance'))->assertForbidden();
    $this->actingAs($editor)->get(route('dashboard.payouts'))->assertForbidden();
    $this->actingAs($editor)->get(route('team.index'))->assertOk()->assertDontSee('Collega uitnodigen');
    $this->actingAs($editor)->post(route('team.invite'), ['email' => 'x@y.nl', 'role' => 'editor'])->assertForbidden();
});

it('geeft de financiële rol de cijfers van het hele bedrijf maar niet de kavels', function () {
    $finance = colleague($this->company, CompanyRole::Finance);
    $lot = companyLot($this->owner);
    $order = Order::factory()->create(['seller_id' => $this->owner->id, 'status' => OrderStatus::Paid, 'hammer_cents' => 250_000]);

    $this->actingAs($finance)->get(route('dashboard.finance'))->assertOk()->assertSee($order->order_number);
    $this->actingAs($finance)->get(route('dashboard.payouts'))->assertOk();
    $this->actingAs($finance)->get(route('dashboard.lots.edit', $lot))->assertNotFound();
    $this->actingAs($finance)->get(route('dashboard.lots'))->assertOk()->assertDontSee($lot->title);
});

it('laat een eigenaar rollen wijzigen en leden verwijderen, maar nooit de laatste eigenaar', function () {
    $admin = colleague($this->company, CompanyRole::Admin);

    $this->actingAs($this->owner)->patch(route('team.role', $admin), ['role' => 'owner'])->assertSessionHasNoErrors();
    expect($admin->fresh()->company_role)->toBe(CompanyRole::Owner);

    // Nu zijn er twee eigenaren: de eerste mag worden teruggezet.
    $this->actingAs($admin)->patch(route('team.role', $this->owner), ['role' => 'editor'])->assertSessionHasNoErrors();
    expect($this->owner->fresh()->company_role)->toBe(CompanyRole::Editor);

    // De laatste eigenaar kan niet weg of omlaag.
    $this->actingAs($admin)->patch(route('team.role', $admin), ['role' => 'editor'])->assertSessionHasErrors('role');
    $this->actingAs($admin)->delete(route('team.remove', $admin))->assertSessionHasErrors('member');

    $this->actingAs($admin)->delete(route('team.remove', $this->owner))->assertSessionHasNoErrors();
    $removed = $this->owner->fresh();
    expect($removed->company_id)->toBeNull()->and($removed->company_role)->toBeNull();

    // Buitenstaanders komen er niet in.
    $other = User::factory()->business()->create();
    $this->actingAs($other)->patch(route('team.role', $admin), ['role' => 'editor'])->assertNotFound();
});

it('toont Team bij de instellingen voor bedrijfsaccounts en niet voor particulieren', function () {
    // Team is een tegel op de instellingenpagina (ontwerp van november), geen los menu-item meer.
    $this->actingAs($this->owner)->get(route('account.settings'))->assertOk()->assertSee(route('team.index'));
    $private = User::factory()->verified()->create();
    $this->actingAs($private)->get(route('account.settings'))->assertOk()->assertDontSee(route('team.index'));
    $this->actingAs($private)->get(route('dashboard'))->assertOk()->assertSee(route('dashboard.finance'));

    $editor = colleague($this->company, CompanyRole::Editor);
    $this->actingAs($editor)->get(route('dashboard'))->assertOk()->assertDontSee(route('dashboard.finance'));

    $this->actingAs($this->owner)->get(route('team.index'))->assertOk()->assertSee('Keukenhuis Utrecht')->assertSee('Collega uitnodigen');
});
