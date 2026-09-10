<?php

declare(strict_types=1);

namespace App\Ivy\Contracts;

use App\Ivy\AiResult;

/**
 * De provider achter Ivy.
 *
 * Eén interface, zodat wisselen van model of leverancier één klasse is
 * (hfst. 14). De provider is bewust dom: tekst en beelden erin, ruwe tekst
 * eruit. Valideren, filteren en toepassen gebeurt erbuiten - in de guards en
 * de job - zodat die stappen niet per provider kunnen verschillen.
 */
interface LlmProvider
{
    /**
     * @param  string  $prompt  De opdracht; bij een schema-herkansing de
     *                          strengere variant met de foutmelding erbij.
     */
    public function complete(AiTask $task, string $prompt): AiResult;
}
