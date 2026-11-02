<?php

declare(strict_types=1);

use App\Lots\DraftAccess;
use App\Support\Experiments\HomeExperiment;
use App\Support\Privacy\CookieConsent;

/*
| Cookiebanner en juridische pagina's (besluit Raph, 2 november 2026).
| Zonder keuze staan statistiek en marketing uit; weigeren is even makkelijk
| als accepteren.
*/

function consentCookie(bool $statistics, bool $marketing = false): string
{
    return json_encode(['v' => CookieConsent::VERSION, 'statistics' => $statistics, 'marketing' => $marketing, 'at' => now()->toIso8601String()]);
}

it('toont de banner tot er een keuze is', function () {
    $this->get(route('faq'))->assertOk()->assertSee('Cookies op Aucvy')->assertSee('Alleen noodzakelijk');

    $this->withCookie(CookieConsent::COOKIE, consentCookie(false))
        ->get(route('faq'))->assertOk()->assertDontSee('Cookies op Aucvy');
});

it('slaat alles accepteren en alleen noodzakelijk op', function (string $choice, bool $expected) {
    $response = $this->postJson(route('cookies.consent'), ['choice' => $choice])
        ->assertOk()
        ->assertJson(['statistics' => $expected, 'marketing' => $expected]);

    $stored = json_decode((string) $response->getCookie(CookieConsent::COOKIE, decrypt: true)?->getValue(), true);

    expect($stored['statistics'])->toBe($expected)
        ->and($stored['v'])->toBe(CookieConsent::VERSION);
})->with([
    'alles' => ['all', true],
    'noodzakelijk' => ['necessary', false],
]);

it('slaat een eigen keuze op via het gewone formulier', function () {
    $this->from(route('legal.cookies'))
        ->post(route('cookies.consent'), ['choice' => 'custom', 'statistics' => '1'])
        ->assertRedirect(route('legal.cookies'))
        ->assertCookie(CookieConsent::COOKIE);
});

it('vergeet de testgroep van de homepage als statistiek uitgaat', function () {
    $this->withCookie(HomeExperiment::COOKIE, 'b')
        ->postJson(route('cookies.consent'), ['choice' => 'necessary'])
        ->assertCookieExpired(HomeExperiment::COOKIE);
});

it('vraagt opnieuw na een nieuwe versie van de uitleg', function () {
    $this->withCookie(CookieConsent::COOKIE, json_encode(['v' => CookieConsent::VERSION - 1, 'statistics' => true]))
        ->get(route('faq'))->assertSee('Cookies op Aucvy');
});

it('noemt op de cookiepagina de cookies die de code echt zet', function () {
    $this->get(route('legal.cookies'))
        ->assertOk()
        ->assertSee(config('session.cookie'))
        ->assertSee(DraftAccess::COOKIE)
        ->assertSee(HomeExperiment::COOKIE)
        ->assertDontSee('Cookies op Aucvy');
});

it('toont tijdelijke voorwaarden en privacy die niet geïndexeerd worden', function (string $route, string $title) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee($title)
        ->assertSee('noindex', escape: false);
})->with([
    ['legal.terms', 'Algemene voorwaarden'],
    ['legal.privacy', 'Privacyverklaring'],
]);
