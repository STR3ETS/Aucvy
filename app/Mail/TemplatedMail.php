<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Een mail uit de builder: de gerenderde html en tekst, de metadata voor
 * het afleverlog en het dossier, en de afmeldkoppen voor marketing.
 */
final class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|int|null>  $meta
     */
    public function __construct(
        public readonly RenderedMail $rendered,
        public readonly string $stream,
        public readonly array $meta = [],
        public readonly ?string $unsubscribeUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $metadata = [];

        foreach ($this->meta as $key => $value) {
            if ($value !== null && $value !== '') {
                $metadata[$key] = (string) $value;
            }
        }

        return new Envelope(subject: $this->rendered->subject, metadata: $metadata);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->rendered->html, text: 'mail.plain', with: ['body' => $this->rendered->text]);
    }

    public function headers(): Headers
    {
        $text = [];

        if ($this->unsubscribeUrl !== null && $this->stream !== MailTemplate::SERVICE) {
            $text['List-Unsubscribe'] = '<'.$this->unsubscribeUrl.'>';
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $text);
    }

    public function build(): static
    {
        $mailer = $this->stream === MailTemplate::SERVICE ? null : config('aucvy.mail.marketing_mailer');

        if (is_string($mailer) && $mailer !== '') {
            $this->mailer($mailer);
        }

        return $this;
    }
}
