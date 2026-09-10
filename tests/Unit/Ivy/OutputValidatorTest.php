<?php

declare(strict_types=1);

use App\Ivy\Guards\OutputValidator;

/*
| De poort tussen modeluitvoer en scherm. Prompts driften, deze validator
| niet - daarom pinnen deze tests het gedrag onafhankelijk van elke provider
| vast (hfst. 8).
*/

beforeEach(function () {
    $this->validator = new OutputValidator;
});

describe('json uitpakken', function () {
    it('haalt json uit een codehek of pratende tekst', function () {
        $text = "Hier is het resultaat:\n```json\n{\"title\": \"Keuken\"}\n```\nSucces!";

        expect($this->validator->extractJson($text))->toBe(['title' => 'Keuken']);
    });

    it('geeft null bij tekst zonder json', function () {
        expect($this->validator->extractJson('Sorry, dat weet ik niet.'))->toBeNull();
    });
});

describe('schemavalidatie', function () {
    $schema = [
        'type' => 'object',
        'required' => ['title', 'confidence'],
        'properties' => [
            'title' => ['type' => 'string', 'maxLength' => 10],
            'brand' => ['type' => ['string', 'null']],
            'condition' => ['type' => 'string', 'enum' => ['good', 'used']],
            'confidence' => ['type' => 'number'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ];

    it('keurt een geldig object goed', function () use ($schema) {
        $errors = $this->validator->schemaErrors([
            'title' => 'Keuken',
            'brand' => null,
            'condition' => 'good',
            'confidence' => 0.8,
            'tags' => ['rvs'],
        ], $schema);

        expect($errors)->toBe([]);
    });

    it('ziet ontbrekende verplichte velden', function () use ($schema) {
        expect($this->validator->schemaErrors(['title' => 'Keuken'], $schema))
            ->toContain('$.confidence: ontbreekt');
    });

    it('ziet een verkeerd type en een enum-overtreding', function () use ($schema) {
        $errors = $this->validator->schemaErrors([
            'title' => 12,
            'condition' => 'briljant',
            'confidence' => 0.5,
        ], $schema);

        expect($errors)->toContain('$.title: verwacht string')
            ->and(implode(' ', $errors))->toContain('$.condition');
    });

    it('bewaakt maxLength en array-items', function () use ($schema) {
        $errors = $this->validator->schemaErrors([
            'title' => 'Veel te lange titel hier',
            'confidence' => 1,
            'tags' => ['ok', 7],
        ], $schema);

        expect(implode(' ', $errors))->toContain('langer dan 10')
            ->and(implode(' ', $errors))->toContain('$.tags[1]');
    });
});

describe('verboden formuleringen', function () {
    it('vangt garanties en escrow-claims, ongeacht hoofdletters', function () {
        $phrases = $this->validator->forbiddenPhrases([
            'description' => 'Deze machine is GEGARANDEERD in topstaat, betaling via escrow.',
        ]);

        expect($phrases)->toContain('gegarandeerd', 'escrow');
    });

    it('laat eerlijke tekst door', function () {
        expect($this->validator->forbiddenPhrases([
            'description' => 'Gebruikssporen aanwezig; zie de foto\'s voor de staat.',
        ]))->toBe([]);
    });
});
