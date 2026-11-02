<?php

declare(strict_types=1);

/*
 | Tweede factor via een authenticator-app (besluit Raph, 2 november 2026).
 */
return [
    'enabled' => 'Je authenticator-app is gekoppeld. Bewaar de herstelcodes hieronder op een veilige plek.',
    'disabled' => 'De authenticator-app is ontkoppeld.',
    'recovery_regenerated' => 'Je hebt nieuwe herstelcodes. De oude werken niet meer.',
    'mail_sent' => 'We hebben een code naar je mail gestuurd. Hij is tien minuten geldig.',

    'errors' => [
        'code_invalid' => 'Die code klopt niet of is verlopen.',
        'recovery_invalid' => 'Die herstelcode klopt niet of is al gebruikt.',
    ],
];
