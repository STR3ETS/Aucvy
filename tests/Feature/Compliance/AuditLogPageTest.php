<?php

declare(strict_types=1);

use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;

/*
| Het auditlog in de backoffice: alles terug te vinden op actie, wie en wat.
*/

it('toont de regels met filters op actie en wie', function () {
    // Vaste namen: een willekeurige "Annabel" als medewerker zou het filter op "Anna" vervuilen.
    $staff = User::factory()->create(['is_staff' => true, 'name' => 'Bert Beheer']);
    $anna = User::factory()->create(['name' => 'Anna Bakker']);
    $lot = Lot::factory()->live()->create(['title' => 'Compressor GA11']);

    Audit::record('lot.approved', $lot, $staff, after: ['note' => 'in orde']);
    Audit::record('user.blocked', $anna, $staff, after: ['reason' => 'test']);
    Audit::record('bid.placed', $lot, $anna, after: ['amount_cents' => 1000]);

    $this->actingAs($staff)->get('/admin/auditlog')
        ->assertOk()
        ->assertSee('lot.approved')
        ->assertSee('user.blocked')
        ->assertSee('bid.placed')
        ->assertSee(route('admin.lots.show', $lot->id), escape: false);

    // De actiefilter staat ook als keuzelijst op de pagina; de rijen herken je aan hun inhoud.
    $this->actingAs($staff)->get('/admin/auditlog?action=user.blocked')
        ->assertOk()
        ->assertSee('reason: test')
        ->assertDontSee('note: in orde')
        ->assertDontSee('amount_cents');

    $this->actingAs($staff)->get('/admin/auditlog?actor=Anna')
        ->assertOk()
        ->assertSee('amount_cents: 1000')
        ->assertDontSee('note: in orde');
});

it('is alleen voor beheer', function () {
    $this->actingAs(User::factory()->create())->get('/admin/auditlog')->assertForbidden();
});
