<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Enums\OrderStatus;
use App\Lots\Dossier\Dossier;
use App\Messaging\Rules\MessageRules;
use App\Messaging\SendMessage;
use App\Messaging\StartConversation;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotPublicAnswer;
use App\Models\Order;
use App\Models\User;
use App\Notifications\MessageHeld;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| Berichten (briefing nieuwe functies, 3 november 2026): korte vraag en
| gesprek per kavel, de moderator-bot met vaste regels, en de operator in
| /admin. Een koper kan nooit het gesprek van een andere koper openen; een
| chatbericht wijzigt nooit een prijs of status.
*/

function lotForMessages(): Lot
{
    $lot = Lot::factory()->live()->create(['seller_id' => User::factory()->verified()->business()->create()->id]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDays(2)]);

    return $lot->fresh(['auction', 'seller']);
}

function buyer(): User
{
    return User::factory()->verified()->create();
}

it('laat een koper een korte vraag stellen die bij de verkoper en in het dossier landt', function () {
    $lot = lotForMessages();
    $buyer = buyer();

    $this->actingAs($buyer)
        ->post(route('lots.ask', $lot), ['kind' => 'question', 'lot_part' => 'staat', 'body' => 'Is de stoomketel recent ontkalkt?'])
        ->assertRedirect();

    $conversation = Conversation::query()->sole();

    expect($conversation->kind)->toBe('question')
        ->and($conversation->lot_part)->toBe('staat')
        ->and($conversation->buyer_id)->toBe($buyer->id)
        ->and($conversation->seller_id)->toBe($lot->seller_id)
        ->and($conversation->messages()->count())->toBe(1)
        ->and(LotActivity::query()->where('source', 'message')->where('type', 'question')->where('user_id', $buyer->id)->exists())->toBeTrue();

    // De verkoper ziet de biednaam, niet de echte naam.
    $this->actingAs($lot->seller)->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee($buyer->publicName())
        ->assertDontSee($buyer->email)
        ->assertSee('Is de stoomketel recent ontkalkt?');

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', [$lot, 'tab' => 'berichten']))->assertOk()->assertSee('Korte vraag');
});

it('weigert een vraag zonder bevestigd telefoonnummer en op een eigen kavel', function () {
    $lot = lotForMessages();
    $unverified = User::factory()->create(['email_verified_at' => now(), 'phone_verified_at' => null]);

    $this->actingAs($unverified)->post(route('lots.ask', $lot), ['kind' => 'question', 'body' => 'Werkt hij nog?'])->assertSessionHasErrors('verification');
    $this->actingAs($lot->seller)->post(route('lots.ask', $lot), ['kind' => 'chat', 'body' => 'Hallo ik'])->assertSessionHasErrors('lot');

    expect(Conversation::query()->count())->toBe(0);
});

it('laat de verkoper een antwoord bij de kavel zetten zonder de naam van de vrager', function () {
    $lot = lotForMessages();
    $buyer = buyer();

    $conversation = app(StartConversation::class)->asBuyer($lot, $buyer, 'question', 'Kan hij door een deur van 80 cm?', 'ophalen');
    $answer = app(SendMessage::class)->handle($conversation, $lot->seller, 'Nee, hij is 90 cm breed. Er is een achterdeur.');

    $this->actingAs($lot->seller)->post(route('messages.publish', [$conversation, $answer]))->assertRedirect();

    $published = LotPublicAnswer::query()->sole();

    expect($published->question)->toBe('Kan hij door een deur van 80 cm?')
        ->and($published->lot_part)->toBe('ophalen');

    $this->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('Vragen en antwoorden')
        ->assertSee('Er is een achterdeur')
        ->assertDontSee($buyer->publicName());

    // De koper mag dat niet.
    $this->actingAs($buyer)->post(route('messages.publish', [$conversation, $answer]))->assertSessionHasErrors('answer');
});

it('maskeert telefoon en mail vóór betaling, ook met spaties en punten', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Hoi, interesse.');

    $message = app(SendMessage::class)->handle($conversation, $conversation->buyer, 'Bel me op 06 12 34 56 78 of mail jan.de.vries@voorbeeld.nl, dan app ik je.');

    expect($message->state)->toBe(ConversationMessage::MASKED)
        ->and($message->body_masked)->not->toContain('06 12 34 56 78')
        ->and($message->body_masked)->not->toContain('voorbeeld.nl')
        ->and($message->bodyFor($conversation->seller))->toContain('afgeschermd')
        ->and($message->bodyFor($conversation->buyer))->toContain('06 12 34 56 78');

    expect(app(MessageRules::class)->maskContact('0612345678 of 06-1234.5678 of +31 6 12345678'))->not->toMatch('/\d{6}/');
});

it('laat contactgegevens door na betaling', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    $order->buyer->forceFill(['phone_verified_at' => now(), 'email_verified_at' => now()])->save();

    $conversation = app(StartConversation::class)->asBuyer($order->lot->fresh(['order']), $order->buyer->fresh(), 'chat', 'Ik kom donderdag, bel me op 0612345678.');

    expect($conversation->order_id)->toBe($order->id)
        ->and($conversation->messages->first()->state)->toBe(ConversationMessage::VISIBLE);
});

it('houdt betalen buiten het platform om tegen, legt het uit en zet het bij de operator', function () {
    Notification::fake();
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Hoi.');

    $held = app(SendMessage::class)->handle($conversation, $lot->seller, 'Als je contant betaalt doe ik er 200 euro af. Maak over naar NL91 ABNA 0417 1643 00.');

    expect($held->state)->toBe(ConversationMessage::HELD)
        ->and($held->bodyFor($conversation->buyer))->toBeNull()
        ->and($held->moderations()->sole()->signal)->toBe('off_platform')
        ->and($conversation->fresh()->flag)->toBe('off_platform')
        ->and(AuditLog::query()->where('action', 'message.hold')->exists())->toBeTrue();

    Notification::assertSentTo($lot->seller, MessageHeld::class);

    // De koper ziet het bericht niet, de verkoper ziet de uitleg.
    $this->actingAs($conversation->buyer)->get(route('messages.show', $conversation))->assertOk()->assertDontSee('contant betaalt');
    $this->actingAs($lot->seller)->get(route('messages.show', $conversation))->assertOk()->assertSee('contant betaalt')->assertSee('Vastgehouden');

    // Operator: in de wachtrij, openen zonder reden (er is een vlag), vrijgeven.
    $staff = User::factory()->create(['is_staff' => true]);
    $this->actingAs($staff)->get(route('admin.conversations'))->assertOk()->assertSee('Buiten Aucvy betalen')->assertSee($lot->title);
    $this->actingAs($staff)->get(route('admin.conversations.show', $conversation))->assertOk()->assertSee('NL91 ABNA 0417 1643 00')->assertSee('Toch vrijgeven');
    $this->actingAs($staff)->post(route('admin.conversations.release', [$conversation, $held]))->assertRedirect();

    expect($held->fresh()->state)->toBe(ConversationMessage::VISIBLE)
        ->and($conversation->fresh()->flag)->toBeNull()
        ->and($held->moderations()->sole()->review_action)->toBe('released');

    $this->actingAs($conversation->buyer)->get(route('messages.show', $conversation))->assertOk()->assertSee('contant betaalt');
});

it('weigert grove taal en phishing meteen met uitleg', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Hoi.');

    $rejected = app(SendMessage::class)->handle($conversation, $conversation->buyer, 'Log in via bit.ly/x9 om je account te verifiëren');

    expect($rejected->state)->toBe(ConversationMessage::REJECTED)
        ->and($rejected->moderations()->sole()->signal)->toBe('phishing');
});

it('laat een andere afspraak staan en stelt de juiste knop voor', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Hoi.');

    $message = app(SendMessage::class)->handle($conversation, $conversation->buyer, 'Kan ik hem donderdag kom ophalen na 14:00?');

    expect($message->state)->toBe(ConversationMessage::VISIBLE)
        ->and($message->hint)->toBe('deal_pickup')
        ->and($lot->auction->fresh()->current_price_cents)->toBe(100_000);
});

it('laat een koper nooit het gesprek van een andere koper openen', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'question', 'Vraag van de eerste koper.');
    $other = buyer();

    $this->actingAs($other)->get(route('messages.show', $conversation))->assertNotFound();
    $this->actingAs($other)->post(route('messages.store', $conversation), ['body' => 'Hoi'])->assertNotFound();
    $this->actingAs($other)->get(route('messages.index'))->assertOk()->assertDontSee('Vraag van de eerste koper');

    // Ook in het dossier ziet een andere koper niets van dit gesprek.
    expect(Dossier::timeline($lot, 'buyer', $other)->where('source', 'message'))->toHaveCount(0);
});

it('laat een medewerker zonder vlag alleen met een reden meelezen, vastgelegd in het auditlog', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Gewoon een vraag.');
    $staff = User::factory()->create(['is_staff' => true]);

    $this->actingAs($staff)->get(route('admin.conversations.show', $conversation))->assertOk()->assertSee('Waarom open je dit gesprek?')->assertDontSee('Gewoon een vraag.');

    expect(AuditLog::query()->where('action', 'conversation.opened')->exists())->toBeFalse();

    $this->actingAs($staff)->get(route('admin.conversations.show', [$conversation, 'reden' => 'Koper belde de klantenservice']))->assertOk()->assertSee('Gewoon een vraag.');

    expect(AuditLog::query()->where('action', 'conversation.opened')->sole()->after['reason'])->toBe('Koper belde de klantenservice')
        ->and($conversation->accessLogs()->sole()->reason)->toBe('Koper belde de klantenservice');
});

it('laat een operator reageren, waarna beide partijen zien dat er iemand meeleest', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Hoi.');
    $staff = User::factory()->create(['is_staff' => true, 'name' => 'Sanne']);
    $conversation->forceFill(['flag' => 'reported', 'reported_at' => now(), 'reported_by' => $conversation->buyer_id])->save();

    $this->actingAs($staff)->post(route('admin.conversations.reply', $conversation), ['body' => 'Ik kijk even mee. Betaling loopt via de bestelling.'])->assertRedirect();
    $this->actingAs($staff)->post(route('admin.conversations.note', $conversation), ['body' => 'Verkoper eerder gewaarschuwd.'])->assertRedirect();

    expect($conversation->fresh()->hasOperator())->toBeTrue();

    $this->actingAs($conversation->buyer)->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('medewerker Aucvy')
        ->assertSee('Ik kijk even mee')
        ->assertDontSee('eerder gewaarschuwd');
});

it('zet een gesprek over een verkochte kavel om in een geschil met het gesprek als bewijs', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::ReadyForPickup);
    $order->buyer->forceFill(['phone_verified_at' => now(), 'email_verified_at' => now()])->save();
    $conversation = app(StartConversation::class)->asBuyer($order->lot->fresh(['order']), $order->buyer->fresh(), 'chat', 'De oven doet het niet.');
    $conversation->forceFill(['flag' => 'reported', 'reported_at' => now()])->save();
    $staff = User::factory()->create(['is_staff' => true]);

    $this->actingAs($staff)->post(route('admin.conversations.dispute', $conversation), ['reason' => 'Koper meldt een defect, verkoper reageert niet.'])->assertRedirect();

    $conversation->refresh();

    expect($conversation->dispute_id)->not->toBeNull()
        ->and($conversation->dispute->evidence['conversation_id'])->toBe($conversation->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Disputed);
});

it('laat de verkoper alleen een gesprek beginnen met een bieder of koper', function () {
    $lot = lotForMessages();
    $bidder = buyer();
    $stranger = buyer();
    app(PlaceBid::class)->handle($lot->auction, $bidder, 110_000, (string) Str::uuid());

    $this->actingAs($lot->seller)->post(route('dashboard.lots.message', [$lot, $stranger]), ['body' => 'Hallo?'])->assertSessionHasErrors('buyer');
    $this->actingAs($lot->seller)->post(route('dashboard.lots.message', [$lot, $bidder]), ['body' => 'Bedankt voor je bod, wil je nog iets weten?'])->assertRedirect();

    expect(Conversation::query()->where('buyer_id', $bidder->id)->exists())->toBeTrue();
});

it('geeft dezelfde client key maar één bericht en kan blokkeren', function () {
    $lot = lotForMessages();
    $conversation = app(StartConversation::class)->asBuyer($lot, buyer(), 'chat', 'Hoi.');
    $key = (string) Str::uuid();

    $this->actingAs($conversation->buyer)->post(route('messages.store', $conversation), ['body' => 'Dubbel?', 'client_key' => $key])->assertRedirect();
    $this->actingAs($conversation->buyer)->post(route('messages.store', $conversation), ['body' => 'Dubbel?', 'client_key' => $key])->assertRedirect();

    expect($conversation->messages()->count())->toBe(2);

    $this->actingAs($lot->seller)->post(route('messages.block', $conversation))->assertRedirect(route('messages.index'));
    $this->actingAs($conversation->buyer)->post(route('messages.store', $conversation), ['body' => 'Nog een'])->assertSessionHasErrors('body');
});
