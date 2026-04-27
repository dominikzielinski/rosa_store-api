<?php

declare(strict_types=1);

namespace Modules\Contact\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Contact\Models\ContactSubmission;

class TeamNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> exponential backoff in seconds */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly ContactSubmission $submission,
    ) {}

    public function envelope(): Envelope
    {
        $typeLabel = $this->submission->type->getData()['name'];

        return new Envelope(
            subject: "[Rosa D'oro] Nowe zgłoszenie {$typeLabel}: {$this->submission->full_name}",
            replyTo: [$this->submission->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'contact::emails.team-notification',
            with: ['submission' => $this->submission],
        );
    }
}
